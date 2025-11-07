<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QrisWebhookController extends Controller
{
    /**
     * Handle QRIS webhook notification
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Log incoming webhook for debugging
            Log::info('QRIS Webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ]);

            // Validate webhook data
            $body = $request->input('body');

            if (!$body || !isset($body['data'])) {
                Log::warning('Invalid webhook payload - missing body.data');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook payload',
                ], 400);
            }

            $data = $body['data'];

            // Check if required fields exist
            if (!isset($data['amount']) || !isset($data['type'])) {
                Log::warning('Invalid webhook data - missing required fields', ['data' => $data]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook data',
                ], 400);
            }

            // Only process credit (CR) transactions
            if ($data['type'] !== 'CR') {
                Log::info('Skipping non-credit transaction', ['type' => $data['type']]);
                return response()->json([
                    'success' => true,
                    'message' => 'Transaction type not processed',
                ]);
            }

            $amount = (float) $data['amount'];
            $timestamp = $request->input('timestamp');

            // Find matching pending deposit by total_amount
            $deposit = Deposit::where('status', 'pending')
                ->where('total_amount', $amount)
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$deposit) {
                Log::info('No matching deposit found for amount', ['amount' => $amount]);
                return response()->json([
                    'success' => true,
                    'message' => 'No matching deposit found',
                ]);
            }

            // Process the payment
            DB::beginTransaction();

            try {
                // Update deposit status
                $deposit->update([
                    'status' => 'success',
                    'paid_at' => $timestamp ? date('Y-m-d H:i:s', strtotime($timestamp)) : now(),
                ]);

                // Add balance to user (including the random amount)
                $user = $deposit->user;
                $user->increment('balance', $deposit->total_amount);

                DB::commit();

                Log::info('Deposit processed successfully', [
                    'deposit_id' => $deposit->id,
                    'user_id' => $user->id,
                    'amount' => $deposit->total_amount,
                    'new_balance' => $user->fresh()->balance,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'data' => [
                        'deposit_id' => $deposit->id,
                        'amount' => $deposit->total_amount,
                    ],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();

                Log::error('Failed to process deposit', [
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process payment',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
