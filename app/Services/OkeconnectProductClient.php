<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class OkeconnectProductClient
{
    /**
     * Fetch the product catalog from OkeConnect.
     *
     * @return list<array<string, mixed>>
     */
    public function fetch(): array
    {
        $config = config('services.okeconnect');

        if (empty($config['price_id']) || empty($config['price_products'])) {
            throw new RuntimeException('OkeConnect price feed credentials are not configured.');
        }

        $url = $config['price_url'] ?? '';

        if ($url === '') {
            throw new RuntimeException('OkeConnect price feed URL is not configured.');
        }

        try {
            $response = Http::timeout(15)
                ->retry(1, 250)
                ->acceptJson()
                ->get($url, [
                    'id' => $config['price_id'],
                    'produk' => $config['price_products'],
                ])
                ->throw();
        } catch (ConnectionException | RequestException $exception) {
            throw new RuntimeException('Unable to fetch products from OkeConnect.', 0, $exception);
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            throw new UnexpectedValueException('OkeConnect product response is not a JSON array.');
        }

        return array_values(array_filter($payload, static fn ($item) => is_array($item)));
    }
}
