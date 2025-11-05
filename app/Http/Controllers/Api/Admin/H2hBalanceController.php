<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class H2hBalanceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        $credentials = config('services.h2h');

        if (
            empty($credentials['member_id']) ||
            empty($credentials['pin']) ||
            empty($credentials['password'])
        ) {
            return response()->json([
                'message' => 'H2H credentials are not configured.',
            ], 500);
        }

        $url = rtrim($credentials['base_url'] ?? '', '/') . '/trx/balance';

        try {
            $response = Http::timeout(10)->retry(1, 200)->get($url, [
                'memberID' => $credentials['member_id'],
                'pin' => $credentials['pin'],
                'password' => $credentials['password'],
            ]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'Unable to reach the H2H service.',
                'details' => $exception->getMessage(),
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => 'Failed to retrieve balance from H2H service.',
                'status' => $response->status(),
                'body' => $response->body(),
            ], 502);
        }

        $rawBalance = trim($response->body());
        $balance = $this->extractBalance($rawBalance);

        if ($balance === null) {
            return response()->json([
                'message' => 'Unable to parse balance from H2H response.',
                'body' => $rawBalance,
            ], 502);
        }

        return response()->json([
            'balance' => $balance,
            'raw_balance' => $rawBalance,
        ]);
    }

    private function extractBalance(string $rawBalance): ?float
    {
        if (preg_match('/-?\d[\d.,]*/', $rawBalance, $matches) !== 1) {
            return null;
        }

        $numeric = preg_replace('/[^\d.,-]/', '', $matches[0]);

        if ($numeric === '' || $numeric === '-' || $numeric === null) {
            return null;
        }

        $commaPos = strrpos($numeric, ',');
        $dotPos = strrpos($numeric, '.');

        if ($commaPos !== false && $dotPos !== false) {
            $decimalSeparator = $commaPos > $dotPos ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $numeric = str_replace($thousandSeparator, '', $numeric);
            $numeric = str_replace($decimalSeparator, '.', $numeric);
        } elseif ($commaPos !== false) {
            $numeric = str_replace('.', '', $numeric);
            $numeric = str_replace(',', '.', $numeric);
        } else {
            $numeric = str_replace(',', '', $numeric);
        }

        if (!is_numeric($numeric)) {
            return null;
        }

        return (float) $numeric;
    }
}
