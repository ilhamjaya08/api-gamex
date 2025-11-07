<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;
use Kodinus\DynamicGenQris\DynamicQRISGenerator;
use App\Models\Deposit;

class QrisService
{
    protected DynamicQRISGenerator $qrisGenerator;

    public function __construct()
    {
        $this->qrisGenerator = new DynamicQRISGenerator();
    }

    /**
     * Generate random amount for binding (1-100, or next range if full)
     */
    public function generateRandomAmount(int $userId): int
    {
        $maxAttempts = 10; // Try up to 10 ranges (1-100, 101-200, etc.)

        for ($rangeStart = 1; $rangeStart <= $maxAttempts * 100; $rangeStart += 100) {
            $rangeEnd = $rangeStart + 99;

            // Get all used random amounts in this range for pending deposits
            $usedAmounts = Deposit::where('user_id', $userId)
                ->where('status', 'pending')
                ->whereBetween('random_amount', [$rangeStart, $rangeEnd])
                ->pluck('random_amount')
                ->toArray();

            // If all 100 numbers in this range are used, try next range
            if (count($usedAmounts) >= 100) {
                continue;
            }

            // Find an unused number in this range
            $availableNumbers = array_diff(range($rangeStart, $rangeEnd), $usedAmounts);

            if (!empty($availableNumbers)) {
                return $availableNumbers[array_rand($availableNumbers)];
            }
        }

        // Fallback: if somehow all ranges are full, use a high random number
        return rand(1001, 9999);
    }

    /**
     * Generate QR Code image (SVG) and save to public storage
     */
    public function generateQrImage(string $qrisCode, string $filename): string
    {
        // Create QR code using named parameters
        $qrCode = new QrCode(
            data: $qrisCode,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 400,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        // Create SVG writer (doesn't require GD extension)
        $writer = new SvgWriter();
        $result = $writer->write($qrCode);

        // Save to public/qris folder
        $directory = public_path('qris');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filepath = $directory . '/' . $filename;
        $result->saveToFile($filepath);

        // Return relative URL path
        return 'qris/' . $filename;
    }

    /**
     * Create QRIS for deposit
     */
    public function createDepositQris(int $userId, float $amount): array
    {
        $baseQris = config('services.qris.code');

        if (empty($baseQris)) {
            throw new \Exception('QRIS_CODE not configured in environment');
        }

        // Validate base QRIS
        if (!$this->qrisGenerator->validateQris($baseQris)) {
            throw new \Exception('Invalid base QRIS code in configuration');
        }

        // Generate random amount for binding
        $randomAmount = $this->generateRandomAmount($userId);
        $totalAmount = $amount + $randomAmount;

        // Extract merchant data from base QRIS (works for both static and dynamic)
        $merchantData = $this->qrisGenerator->extractMerchant($baseQris);

        // Add invoice ID as unique identifier
        $merchantData['invoice_id'] = 'DEP' . time() . $userId;

        // Generate new dynamic QRIS with the specified amount
        $dynamicQris = $this->qrisGenerator->generate(
            $merchantData,
            intval($totalAmount) // Amount must be integer (rupiah, no decimal)
        );

        // Generate QR image (SVG format, no GD extension required)
        $filename = 'qris_' . time() . '_' . $userId . '.svg';
        $qrisImage = $this->generateQrImage($dynamicQris, $filename);

        return [
            'qris_code' => $dynamicQris,
            'qris_image' => $qrisImage,
            'random_amount' => $randomAmount,
            'total_amount' => $totalAmount,
        ];
    }
}
