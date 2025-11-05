<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\OkeconnectProductClient;
use Illuminate\Http\JsonResponse;
use Throwable;

class ProductSyncController extends Controller
{
    public function __construct(
        private readonly OkeconnectProductClient $client
    ) {
    }

    /**
     * Fetch all products from OkeConnect and upsert them locally.
     */
    public function import(): JsonResponse
    {
        try {
            $products = $this->client->fetch();
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Failed to import products from OkeConnect.',
                'details' => $exception->getMessage(),
            ], 502);
        }

        $normalizedRows = $this->normalizeProducts($products, includeCreatedAt: true);

        if (empty($normalizedRows)) {
            return response()->json([
                'message' => 'No products were imported from OkeConnect.',
            ]);
        }

        $incomingCodes = array_column($normalizedRows, 'kode');

        $existingCodes = Product::query()
            ->whereIn('kode', $incomingCodes)
            ->pluck('kode')
            ->all();

        $existingLookup = array_flip($existingCodes);

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($normalizedRows as $row) {
            if (isset($existingLookup[$row['kode']])) {
                $updatedCount++;
            } else {
                $createdCount++;
            }
        }

        Product::upsert(
            $normalizedRows,
            ['kode'],
            ['nama', 'kategori', 'keterangan', 'harga', 'status', 'updated_at']
        );

        return response()->json([
            'message' => 'Products imported successfully.',
            'fetched' => count($normalizedRows),
            'created' => $createdCount,
            'updated' => $updatedCount,
        ]);
    }

    /**
     * Refresh metadata for products that already exist locally.
     */
    public function refresh(): JsonResponse
    {
        try {
            $products = $this->client->fetch();
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Failed to refresh products from OkeConnect.',
                'details' => $exception->getMessage(),
            ], 502);
        }

        $normalizedRows = $this->normalizeProducts($products, includeCreatedAt: false);

        if (empty($normalizedRows)) {
            return response()->json([
                'message' => 'No matching products were found to refresh.',
            ]);
        }

        $incomingCodes = array_column($normalizedRows, 'kode');

        $existingCodes = Product::query()
            ->whereIn('kode', $incomingCodes)
            ->pluck('kode')
            ->all();

        if (empty($existingCodes)) {
            return response()->json([
                'message' => 'No matching products were found to refresh.',
            ]);
        }

        $existingLookup = array_flip($existingCodes);

        $rowsToUpdate = array_values(array_filter(
            $normalizedRows,
            static fn (array $row): bool => isset($existingLookup[$row['kode']])
        ));

        if (empty($rowsToUpdate)) {
            return response()->json([
                'message' => 'No matching products were found to refresh.',
            ]);
        }

        Product::upsert(
            $rowsToUpdate,
            ['kode'],
            ['nama', 'kategori', 'keterangan', 'harga', 'status', 'updated_at']
        );

        return response()->json([
            'message' => 'Products refreshed successfully.',
            'updated' => count($rowsToUpdate),
        ]);
    }

    /**
     * Normalize remote payloads into product attributes ready for persistence.
     *
     * @param list<array<string, mixed>> $products
     * @return list<array<string, mixed>>
     */
    private function normalizeProducts(array $products, bool $includeCreatedAt): array
    {
        $now = now();
        $rows = [];
        $seen = [];

        foreach ($products as $product) {
            $normalized = $this->normalizeProduct($product);

            if ($normalized === null) {
                continue;
            }

            if (isset($seen[$normalized['kode']])) {
                continue;
            }

            $seen[$normalized['kode']] = true;

            $payload = [
                ...$normalized,
                'updated_at' => $now,
            ];

            if ($includeCreatedAt) {
                $payload['created_at'] = $now;
            }

            $rows[] = $payload;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function normalizeProduct(array $product): ?array
    {
        $kode = $this->stringValue($product, 'kode');
        $nama = $this->stringValue($product, 'produk');

        if ($kode === null || $nama === null) {
            return null;
        }

        $kategori = $this->stringValue($product, 'kategori') ?? 'UNKNOWN';
        $keterangan = $this->stringValue($product, 'keterangan');
        $harga = $this->normalizePrice($product['harga'] ?? null);

        if ($harga === null) {
            return null;
        }

        return [
            'kode' => $kode,
            'nama' => $nama,
            'kategori' => $kategori,
            'keterangan' => $keterangan,
            'harga' => $harga,
            'status' => $this->normalizeStatus($product['status'] ?? null),
        ];
    }

    private function stringValue(array $product, string $key): ?string
    {
        if (!array_key_exists($key, $product)) {
            return null;
        }

        $value = $product[$key];

        if (is_string($value)) {
            $value = trim($value);
        } elseif (is_numeric($value)) {
            $value = (string) $value;
        } else {
            return null;
        }

        return $value !== '' ? $value : null;
    }

    private function normalizePrice(mixed $price): ?string
    {
        if ($price === null) {
            return null;
        }

        if (is_int($price) || is_float($price)) {
            $numeric = (float) $price;
        } elseif (is_string($price)) {
            $sanitized = preg_replace('/[^\d.,-]/', '', trim($price));

            if ($sanitized === '' || $sanitized === '-') {
                return null;
            }

            $commaPos = strrpos($sanitized, ',');
            $dotPos = strrpos($sanitized, '.');

            if ($commaPos !== false && $dotPos !== false) {
                $decimalSeparator = $commaPos > $dotPos ? ',' : '.';
                $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
                $sanitized = str_replace($thousandSeparator, '', $sanitized);
                $sanitized = str_replace($decimalSeparator, '.', $sanitized);
            } elseif ($commaPos !== false) {
                $sanitized = str_replace('.', '', $sanitized);
                $sanitized = str_replace(',', '.', $sanitized);
            } else {
                $sanitized = str_replace(',', '', $sanitized);
            }

            if (!is_numeric($sanitized)) {
                return null;
            }

            $numeric = (float) $sanitized;
        } else {
            return null;
        }

        return number_format($numeric, 2, '.', '');
    }

    private function normalizeStatus(mixed $status): bool
    {
        // Provider uses numeric flags where 0 = active and 1 = inactive.
        if (is_bool($status)) {
            return $status;
        }

        if (is_numeric($status)) {
            return (int) $status === 0;
        }

        if (is_string($status)) {
            $value = strtolower(trim($status));

            if ($value === '') {
                return false;
            }

            if (is_numeric($value)) {
                return (int) $value === 0;
            }

            return in_array($value, ['true', 'available', 'aktif', 'active'], true);
        }

        return false;
    }
}
