<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DepositController extends Controller
{
    protected QrisService $qrisService;

    public function __construct(QrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    /**
     * Get all deposits for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $deposits = $request->user()
            ->deposits()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $deposits,
        ]);
    }

    /**
     * Get single deposit
     */
    public function show(Request $request, Deposit $deposit): JsonResponse
    {
        // Check if deposit belongs to authenticated user
        if ($deposit->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $deposit,
        ]);
    }

    /**
     * Create new deposit request
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10|max:10000000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Check if user already has a pending deposit
        $pendingDeposit = Deposit::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($pendingDeposit) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending deposit. Please complete or cancel it first.',
                'data' => [
                    'pending_deposit' => $pendingDeposit,
                ],
            ], 400);
        }

        try {
            DB::beginTransaction();

            $amount = $request->amount;

            // Generate QRIS code with random amount
            $qrisData = $this->qrisService->createDepositQris($user->id, $amount);

            // Create deposit record
            $deposit = Deposit::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'random_amount' => $qrisData['random_amount'],
                'total_amount' => $qrisData['total_amount'],
                'qris_code' => $qrisData['qris_code'],
                'qris_image' => $qrisData['qris_image'],
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Deposit request created successfully',
                'data' => [
                    'deposit' => $deposit,
                    'qris_image_url' => url($qrisData['qris_image']),
                    'instructions' => [
                        'Please scan the QR code or use the QRIS code to complete the payment',
                        'Total amount to pay: Rp ' . number_format($qrisData['total_amount'], 0, ',', '.'),
                        'The extra amount (Rp ' . number_format($qrisData['random_amount'], 0, ',', '.') . ') will be added to your balance',
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create deposit request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel pending deposit
     */
    public function cancel(Request $request, Deposit $deposit): JsonResponse
    {
        // Check if deposit belongs to authenticated user
        if ($deposit->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check if deposit is still pending
        if ($deposit->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending deposits can be cancelled',
            ], 400);
        }

        try {
            $deposit->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Deposit cancelled successfully',
                'data' => $deposit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel deposit: ' . $e->getMessage(),
            ], 500);
        }
    }
}
