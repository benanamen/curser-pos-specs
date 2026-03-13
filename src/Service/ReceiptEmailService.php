<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Item\ItemRepository;
use CurserPos\Domain\Sale\PaymentRepository;
use CurserPos\Domain\Sale\Sale;
use CurserPos\Domain\Sale\SaleRepository;
use CurserPos\Http\RequestContextHolder;

final class ReceiptEmailService
{
    public function __construct(
        private readonly SaleRepository $saleRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly ItemRepository $itemRepository
    ) {
    }

    public function sendReceipt(string $saleId, string $toEmail): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '') {
            return false;
        }
        $context = RequestContextHolder::get();
        $tenant = $context?->tenant;
        if ($tenant === null) {
            return false;
        }
        $settings = $tenant->settings ?? [];
        $fromEmail = isset($settings['store_email']) && (string) $settings['store_email'] !== ''
            ? (string) $settings['store_email']
            : 'noreply@' . ($tenant->slug ?? 'store') . '.local';
        $storeName = (string) ($tenant->name ?? 'Store');

        $sale = $this->saleRepository->findById($saleId);
        if ($sale === null) {
            return false;
        }
        $items = $this->saleRepository->getSaleItems($saleId);
        $payments = $this->paymentRepository->getBySaleId($saleId);

        $subject = 'Your receipt - Sale ' . $sale->saleNumber . ' - ' . $storeName;
        $body = $this->buildBody($storeName, $settings, $sale, $items, $payments);
        $headers = "From: " . $fromEmail . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";

        return @mail($toEmail, $subject, $body, $headers);
    }

    /**
     * @param array<string, mixed> $settings
     * @param list<array<string, mixed>> $items
     * @param list<array{id: string, method: string, amount: float, reference: string|null}> $payments
     */
    private function buildBody(
        string $storeName,
        array $settings,
        Sale $sale,
        array $items,
        array $payments
    ): string {
        $lines = [];
        $lines[] = $storeName;
        $storeAddress = $settings['store_address'] ?? null;
        if ($storeAddress !== null && (string) $storeAddress !== '') {
            $lines[] = (string) $storeAddress;
        }
        $storePhone = $settings['store_phone'] ?? null;
        if ($storePhone !== null && (string) $storePhone !== '') {
            $lines[] = (string) $storePhone;
        }
        $lines[] = '';
        $lines[] = 'Sale #' . $sale->saleNumber;
        $lines[] = 'Date: ' . $sale->createdAt->format('Y-m-d H:i');
        $lines[] = '';
        $lines[] = '--- Items ---';
        foreach ($items as $row) {
            $qty = (int) ($row['quantity'] ?? 1);
            $unitPrice = (float) ($row['unit_price'] ?? 0);
            $lineTotal = $unitPrice * $qty;
            $description = 'Item';
            $itemId = $row['item_id'] ?? null;
            if ($itemId !== null && $itemId !== '') {
                $item = $this->itemRepository->findById((string) $itemId);
                if ($item !== null) {
                    $desc = trim($item->description ?? '');
                    $sku = trim($item->sku ?? '');
                    $description = $desc !== '' ? $desc : ($sku !== '' ? $sku : 'Item');
                }
            }
            $lines[] = sprintf('%s  x%d  %s  %s', $description, $qty, number_format($unitPrice, 2), number_format($lineTotal, 2));
        }
        $lines[] = '';
        $lines[] = 'Subtotal: ' . number_format($sale->subtotal, 2);
        $lines[] = 'Discount: ' . number_format($sale->discountAmount, 2);
        $lines[] = 'Tax: ' . number_format($sale->taxAmount, 2);
        $lines[] = 'Total: ' . number_format($sale->total, 2);
        if ($payments !== []) {
            $lines[] = '';
            $lines[] = '--- Payments ---';
            foreach ($payments as $p) {
                $lines[] = ucfirst($p['method']) . ': ' . number_format($p['amount'], 2);
            }
        }
        $lines[] = '';
        $lines[] = 'Thank you for your purchase.';
        $footerText = $settings['receipt_footer_text'] ?? null;
        if ($footerText !== null && trim((string) $footerText) !== '') {
            $lines[] = '';
            $lines[] = trim((string) $footerText);
        }
        return implode("\r\n", $lines);
    }
}
