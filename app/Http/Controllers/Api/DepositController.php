<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
     * Get current active deposit (pending status) for authenticated user
     */
    public function active(Request $request): JsonResponse
    {
        $activeDeposit = $request->user()
            ->deposits()
            ->where('status', 'pending')
            ->first();

        if (!$activeDeposit) {
            return response()->json([
                'success' => false,
                'message' => 'No active deposit found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Active deposit found',
            'data' => [
                'deposit' => $activeDeposit,
                'qris_image_url' => url($activeDeposit->qris_image),
            ],
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

    /**
     * Refresh deposit status by checking QRIS mutasi API
     * Public endpoint - no authentication required
     */
    public function refreshStatus(Deposit $deposit): JsonResponse
    {
        try {
            // Check current status
            if ($deposit->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Deposit has been cancelled',
                    'data' => [
                        'deposit' => $deposit,
                        'status' => 'cancelled',
                    ],
                ]);
            }

            if ($deposit->status === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Deposit already completed',
                    'data' => [
                        'deposit' => $deposit,
                        'status' => 'success',
                    ],
                ]);
            }

            // If pending, check mutasi API
            $merchantCode = config('services.qris.merchant_code');
            $apiKey = config('services.qris.api_key');
            $mutasiUrl = config('services.qris.mutasi_url');

            if (!$merchantCode || !$apiKey) {
                Log::error('QRIS credentials not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'QRIS service not configured',
                ], 500);
            }

            // Call mutasi API
            $url = "{$mutasiUrl}/{$merchantCode}/{$apiKey}";

            Log::info('Checking QRIS mutasi', [
                'deposit_id' => $deposit->id,
                'total_amount' => $deposit->total_amount,
            ]);

            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                Log::error('Failed to fetch QRIS mutasi', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to check payment status',
                ], 500);
            }

            $result = $response->json();

            // Check if response is successful
            if (!isset($result['status']) || $result['status'] !== 'success') {
                $errorMessage = $result['message'] ?? 'Unknown error';

                Log::warning('QRIS mutasi API error', [
                    'message' => $errorMessage,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                    'data' => [
                        'deposit' => $deposit,
                        'status' => 'pending',
                    ],
                ]);
            }

            // Check if data exists
            if (!isset($result['data']) || !is_array($result['data'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                    'data' => [
                        'deposit' => $deposit,
                        'status' => 'pending',
                    ],
                ]);
            }

            // Filter transactions from last 2 days
            $twoDaysAgo = Carbon::now()->subDays(2);
            $targetAmount = (float) $deposit->total_amount;

            $matchedTransaction = null;

            foreach ($result['data'] as $transaction) {
                // Parse transaction date (format: "2024-07-03 22:38:07")
                try {
                    $transactionDate = Carbon::createFromFormat('Y-m-d H:i:s', $transaction['date']);

                    // Skip if older than 2 days
                    if ($transactionDate->lt($twoDaysAgo)) {
                        continue;
                    }

                    // Check if amount matches and type is CR (credit)
                    $transactionAmount = (float) $transaction['amount'];

                    if ($transaction['type'] === 'CR' && $transactionAmount === $targetAmount) {
                        $matchedTransaction = $transaction;
                        break;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to parse transaction date', [
                        'date' => $transaction['date'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            // If match found, update deposit to success
            if ($matchedTransaction) {
                DB::beginTransaction();

                try {
                    // Update deposit status
                    $deposit->update([
                        'status' => 'success',
                        'paid_at' => $matchedTransaction['date'],
                    ]);

                    // Add balance to user
                    $user = $deposit->user;
                    $user->increment('balance', $deposit->total_amount);

                    DB::commit();

                    Log::info('Deposit status updated via refresh', [
                        'deposit_id' => $deposit->id,
                        'user_id' => $user->id,
                        'amount' => $deposit->total_amount,
                        'matched_transaction' => $matchedTransaction,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment confirmed! Deposit completed successfully',
                        'data' => [
                            'deposit' => $deposit->fresh(),
                            'status' => 'success',
                            'transaction' => $matchedTransaction,
                        ],
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();

                    Log::error('Failed to update deposit status', [
                        'deposit_id' => $deposit->id,
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to update deposit status',
                    ], 500);
                }
            }

            // No matching transaction found
            return response()->json([
                'success' => false,
                'message' => 'Payment not found. Please try again later or contact support.',
                'data' => [
                    'deposit' => $deposit,
                    'status' => 'pending',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Refresh status error', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh status: ' . $e->getMessage(),
            ], 500);
        }
    }
}
