<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OkeconnectTransactionService
{
    private string $baseUrl;
    private string $memberId;
    private string $pin;
    private string $password;

    public function __construct()
    {
        $this->baseUrl = config('services.h2h.url');
        $this->memberId = config('services.h2h.member_id');
        $this->pin = config('services.h2h.pin');
        $this->password = config('services.h2h.password');
    }

    /**
     * Create a new transaction
     *
     * @param string $productCode Product code (e.g., D1, T5)
     * @param string $destination Destination number/account
     * @param int $refId Reference ID from transactions table
     * @return array ['success' => bool, 'message' => string, 'raw_response' => string, 'transaction_id' => string|null]
     */
    public function createTransaction(string $productCode, string $destination, int $refId): array
    {
        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/trx', [
                'product' => $productCode,
                'dest' => $destination,
                'refID' => $refId,
                'memberID' => $this->memberId,
                'pin' => $this->pin,
                'password' => $this->password,
            ]);

            $rawResponse = $response->body();
            Log::info('OkeConnect Transaction Response', [
                'product' => $productCode,
                'dest' => $destination,
                'refID' => $refId,
                'response' => $rawResponse,
            ]);

            return $this->parseTransactionResponse($rawResponse, $refId);
        } catch (\Exception $e) {
            Log::error('OkeConnect Transaction Error', [
                'product' => $productCode,
                'dest' => $destination,
                'refID' => $refId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal menghubungi server: ' . $e->getMessage(),
                'raw_response' => '',
                'transaction_id' => null,
                'status' => 'failed',
            ];
        }
    }

    /**
     * Check transaction status
     *
     * @param string $productCode Product code
     * @param string $destination Destination number/account
     * @param int $refId Reference ID
     * @return array ['success' => bool, 'status' => string, 'message' => string, 'serial_number' => string|null]
     */
    public function checkStatus(string $productCode, string $destination, int $refId): array
    {
        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/trx', [
                'product' => $productCode,
                'dest' => $destination,
                'refID' => $refId,
                'memberID' => $this->memberId,
                'pin' => $this->pin,
                'password' => $this->password,
                'check' => 1,
            ]);

            $rawResponse = $response->body();
            Log::info('OkeConnect Check Status Response', [
                'product' => $productCode,
                'dest' => $destination,
                'refID' => $refId,
                'response' => $rawResponse,
            ]);

            return $this->parseStatusResponse($rawResponse);
        } catch (\Exception $e) {
            Log::error('OkeConnect Check Status Error', [
                'product' => $productCode,
                'dest' => $destination,
                'refID' => $refId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'Gagal mengecek status: ' . $e->getMessage(),
                'serial_number' => null,
            ];
        }
    }

    /**
     * Parse transaction response text
     * Format sukses: "T#210286229 R#113 Three 1.000 T1.089660522887 akan diproses. Saldo 279.655 - 1.321 = 278.334 @19:08"
     * Format gagal: "R#0 T1.089660522887 GAGAL. Pin Salah"
     */
    private function parseTransactionResponse(string $response, int $refId): array
    {
        // Check if response contains GAGAL
        if (stripos($response, 'GAGAL') !== false) {
            // Extract failure message
            preg_match('/GAGAL\.\s*(.+?)(?:\.|$)/i', $response, $matches);
            $failureReason = $matches[1] ?? 'Transaksi gagal';

            return [
                'success' => false,
                'message' => $failureReason,
                'raw_response' => $response,
                'transaction_id' => null,
                'status' => 'failed',
            ];
        }

        // Check if it's being processed (akan diproses)
        if (stripos($response, 'akan diproses') !== false) {
            // Extract T# (transaction ID from provider)
            preg_match('/T#(\d+)/', $response, $matches);
            $transactionId = $matches[1] ?? null;

            return [
                'success' => true,
                'message' => 'Transaksi sedang diproses',
                'raw_response' => $response,
                'transaction_id' => $transactionId,
                'status' => 'process',
            ];
        }

        // Unknown response format
        return [
            'success' => false,
            'message' => 'Format response tidak dikenal: ' . $response,
            'raw_response' => $response,
            'transaction_id' => null,
            'status' => 'failed',
        ];
    }

    /**
     * Parse status check response
     * Sukses: "R#113 Three 1.000 T1.089660522887 sudah pernah jam 19:08, status Sukses. SN: R230512.1908.2000FE..."
     * Gagal: "R#999 Three 5.000 T5.08980204060 sudah pernah jam 18:46, status Gagal. Mohon diperiksa..."
     * Pending: "Mhn tunggu trx sblmnya selesai: T#762221212 R#999 T5.08980204060 @18:46, status Menunggu Jawaban..."
     * Not found: "TIDAK ADA transaksi Tujuan 08980204060 pada tgl 22/04/2025. Tidak ada data..."
     */
    private function parseStatusResponse(string $response): array
    {
        // No data / not found
        if (stripos($response, 'TIDAK ADA') !== false || stripos($response, 'Tidak ada data') !== false) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'Transaksi tidak ditemukan',
                'serial_number' => null,
                'raw_response' => $response,
            ];
        }

        // Success
        if (stripos($response, 'status Sukses') !== false || stripos($response, 'SUKSES') !== false) {
            preg_match('/SN:\s*([A-Z0-9\.]+)/i', $response, $snMatches);
            $serialNumber = $snMatches[1] ?? null;

            return [
                'success' => true,
                'status' => 'success',
                'message' => 'Transaksi sukses',
                'serial_number' => $serialNumber,
                'raw_response' => $response,
            ];
        }

        // Failed
        if (stripos($response, 'status Gagal') !== false || stripos($response, 'GAGAL') !== false) {
            return [
                'success' => true,
                'status' => 'failed',
                'message' => 'Transaksi gagal',
                'serial_number' => null,
                'raw_response' => $response,
            ];
        }

        // Pending / Menunggu
        if (stripos($response, 'Menunggu') !== false || stripos($response, 'Mhn tunggu') !== false) {
            return [
                'success' => true,
                'status' => 'pending',
                'message' => 'Transaksi sedang diproses',
                'serial_number' => null,
                'raw_response' => $response,
            ];
        }

        // Unknown
        return [
            'success' => false,
            'status' => 'unknown',
            'message' => 'Status tidak dikenal: ' . $response,
            'serial_number' => null,
            'raw_response' => $response,
        ];
    }

    /**
     * Parse callback message
     * Sukses: "T#41168891 R#1234 Telkomsel 5.000 S5.082280004280 SUKSES. SN/Ref: R210630.2203.210045. Saldo..."
     * Gagal: "T#41169572 R#1235 Telkomsel 5.000 S5.082280004280 GAGAL. Nomor tujuan salah. Saldo..."
     */
    public function parseCallbackMessage(string $message): array
    {
        // Extract refID
        preg_match('/R#(\d+)/', $message, $refMatches);
        $refId = $refMatches[1] ?? null;

        // Extract transaction ID
        preg_match('/T#(\d+)/', $message, $tMatches);
        $transactionId = $tMatches[1] ?? null;

        // Check status
        if (stripos($message, 'SUKSES') !== false) {
            preg_match('/SN[\/:]?\s*([A-Z0-9\.]+)/i', $message, $snMatches);
            $serialNumber = $snMatches[1] ?? null;

            return [
                'ref_id' => $refId,
                'transaction_id' => $transactionId,
                'status' => 'success',
                'serial_number' => $serialNumber,
                'message' => $message,
            ];
        }

        if (stripos($message, 'GAGAL') !== false) {
            return [
                'ref_id' => $refId,
                'transaction_id' => $transactionId,
                'status' => 'failed',
                'serial_number' => null,
                'message' => $message,
            ];
        }

        return [
            'ref_id' => $refId,
            'transaction_id' => $transactionId,
            'status' => 'unknown',
            'serial_number' => null,
            'message' => $message,
        ];
    }
}
