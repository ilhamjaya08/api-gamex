<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\OkeconnectTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function __construct(
        private OkeconnectTransactionService $okeconnectService
    ) {}

    /**
     * Display a listing of user's transactions
     */
    public function index(Request $request): JsonResponse
    {
        $transactions = $request->user()
            ->transactions()
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
    }

    /**
     * Store a newly created transaction
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Load product with price
        $product = Product::findOrFail($validated['product_id']);

        // Check if product is active
        if ($product->status) {
            return response()->json([
                'message' => 'Produk sedang tidak tersedia'
            ], 422);
        }

        // Check user balance
        if ($user->balance < $product->harga) {
            return response()->json([
                'message' => 'Saldo tidak mencukupi',
                'required' => $product->harga,
                'current_balance' => $user->balance,
                'shortage' => $product->harga - $user->balance,
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create transaction record first to get ID for refID
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'target_id' => $validated['target_id'],
                'server_id' => $validated['server_id'] ?? null,
                'payment_method' => 'balance',
                'amount' => $product->harga,
                'status' => 'pending',
            ]);

            // Call OkeConnect API
            $result = $this->okeconnectService->createTransaction(
                $product->kode,
                $validated['target_id'],
                $transaction->id
            );

            if (!$result['success']) {
                // Transaction failed, rollback
                DB::rollBack();
                return response()->json([
                    'message' => 'Transaksi gagal: ' . $result['message'],
                    'details' => $result['raw_response'],
                ], 422);
            }

            // Update transaction with provider details
            $transaction->update([
                'provider_trx_id' => $result['transaction_id'],
                'status' => $result['status'], // 'process'
            ]);

            // Deduct user balance
            $user->decrement('balance', $product->harga);

            DB::commit();

            return response()->json([
                'message' => 'Transaksi berhasil dibuat',
                'transaction' => $transaction->load('product'),
                'new_balance' => $user->fresh()->balance,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transaction
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        // Ensure user can only see their own transaction
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'transaction' => $transaction->load('product'),
        ]);
    }

    /**
     * Refresh transaction status from OkeConnect
     */
    public function refreshStatus(Request $request, Transaction $transaction): JsonResponse
    {
        // Ensure user can only refresh their own transaction
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Don't refresh if already in final state
        if (in_array($transaction->status, ['success', 'failed', 'refund'])) {
            return response()->json([
                'message' => 'Transaksi sudah selesai',
                'transaction' => $transaction->load('product'),
            ]);
        }

        // Check status from OkeConnect
        $result = $this->okeconnectService->checkStatus(
            $transaction->product->kode,
            $transaction->target_id,
            $transaction->id
        );

        // Update transaction based on result
        $updatedStatus = match($result['status']) {
            'success' => 'success',
            'failed' => 'failed',
            'pending', 'process' => 'process',
            'not_found' => $transaction->status, // Keep current status
            default => $transaction->status,
        };

        $updateData = ['status' => $updatedStatus];

        // Add serial number if exists
        if ($result['serial_number']) {
            $updateData['provider_trx_id'] = $result['serial_number'];
        }

        // If transaction failed or refunded, refund the balance
        if ($updatedStatus === 'failed' && $transaction->status !== 'failed') {
            DB::beginTransaction();
            try {
                $transaction->update($updateData);

                // Refund to user balance
                $transaction->user->increment('balance', $transaction->amount);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal memproses refund: ' . $e->getMessage()
                ], 500);
            }
        } else {
            $transaction->update($updateData);
        }

        return response()->json([
            'message' => 'Status berhasil diperbarui',
            'transaction' => $transaction->fresh()->load('product'),
            'raw_response' => $result['raw_response'] ?? null,
        ]);
    }
}
