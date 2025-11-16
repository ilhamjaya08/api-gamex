<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\OkeconnectTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionCallbackController extends Controller
{
    public function __construct(
        private OkeconnectTransactionService $okeconnectService
    ) {}

    /**
     * Handle transaction callback from OkeConnect
     *
     * GET /api/webhook/transaction-callback?refid=114&message=T%23210288912%20R%23114%20Three%201.000...
     */
    public function __invoke(Request $request)
    {
        $refId = $request->get('refid');
        $message = $request->get('message');

        Log::info('Transaction Callback Received', [
            'refid' => $refId,
            'message' => $message,
            'all_params' => $request->all(),
        ]);

        // Validate required parameters
        if (!$refId || !$message) {
            Log::warning('Transaction Callback Missing Parameters', [
                'refid' => $refId,
                'message' => $message,
            ]);

            return response('Missing required parameters', 400);
        }

        // Find transaction by ID (refID is the transaction ID)
        $transaction = Transaction::find($refId);

        if (!$transaction) {
            Log::warning('Transaction Not Found', [
                'refid' => $refId,
            ]);

            return response('Transaction not found', 404);
        }

        // Parse callback message
        $parsed = $this->okeconnectService->parseCallbackMessage($message);

        Log::info('Parsed Callback Message', [
            'refid' => $refId,
            'parsed' => $parsed,
        ]);

        // Don't update if already in final state
        if (in_array($transaction->status, ['success', 'failed', 'refund'])) {
            Log::info('Transaction Already Finalized', [
                'transaction_id' => $transaction->id,
                'current_status' => $transaction->status,
            ]);

            return response('OK', 200);
        }

        DB::beginTransaction();
        try {
            $updateData = [
                'status' => $parsed['status'],
            ];

            // Add provider transaction ID if exists
            if ($parsed['transaction_id']) {
                $updateData['provider_trx_id'] = $parsed['transaction_id'];
            }

            // Update transaction
            $transaction->update($updateData);

            // If failed, refund to user balance
            if ($parsed['status'] === 'failed') {
                $transaction->user->increment('balance', $transaction->amount);

                Log::info('Transaction Failed - Refunded Balance', [
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'amount' => $transaction->amount,
                ]);
            }

            // If success, log it
            if ($parsed['status'] === 'success') {
                Log::info('Transaction Success', [
                    'transaction_id' => $transaction->id,
                    'serial_number' => $parsed['serial_number'],
                ]);

                // Update with serial number
                if ($parsed['serial_number']) {
                    $transaction->update([
                        'provider_trx_id' => $parsed['serial_number'],
                    ]);
                }
            }

            DB::commit();

            Log::info('Transaction Callback Processed Successfully', [
                'transaction_id' => $transaction->id,
                'new_status' => $parsed['status'],
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Transaction Callback Processing Error', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Internal Server Error', 500);
        }
    }
}
