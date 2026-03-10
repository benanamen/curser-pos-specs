<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Item\Item;
use CurserPos\Domain\Item\ItemRepository;
use CurserPos\Domain\Sale\PaymentRepository;
use CurserPos\Domain\Sale\Sale;
use CurserPos\Domain\Sale\SaleRepository;
use CurserPos\Domain\Sale\HeldSaleRepository;
use CurserPos\Infrastructure\Payment\PaymentProcessorInterface;

final class PosService
{
    /**
     * @param array<int, array{item_id: string, quantity: int}> $cart
     * @param array<int, array{method: string, amount: float, reference?: string|null}> $payments
     */
    public function __construct(
        private readonly SaleRepository $saleRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly ItemRepository $itemRepository,
        private readonly ConsignorService $consignorService,
        private readonly PaymentProcessorInterface $paymentProcessor,
        private readonly HeldSaleRepository $heldSaleRepository,
        private readonly \CurserPos\Domain\Sale\StoreCreditRepository $storeCreditRepository,
        private readonly \CurserPos\Domain\Sale\GiftCardRepository $giftCardRepository
    ) {
    }

    /**
     * Complete a sale: create sale record, sale items, payments; mark items sold; update consignor balances.
     *
     * @param array<int, array{item_id: string, quantity: int}> $cart
     * @param array<int, array{method: string, amount: float, reference?: string|null}> $payments
     */
    public function checkout(
        string $userId,
        ?string $registerId,
        ?string $locationId,
        array $cart,
        array $payments,
        float $discountAmount = 0.0,
        float $taxAmount = 0.0,
        bool $taxExempt = false
    ): array {
        if ($cart === []) {
            throw new \InvalidArgumentException('Cart cannot be empty');
        }

        $subtotal = 0.0;
        $lineItems = [];

        foreach ($cart as $entry) {
            $itemId = $entry['item_id'] ?? '';
            $quantity = (int) ($entry['quantity'] ?? 1);
            if ($itemId === '' || $quantity < 1) {
                continue;
            }
            $item = $this->itemRepository->findById($itemId);
            if ($item === null) {
                throw new \InvalidArgumentException("Item not found: {$itemId}");
            }
            if ($item->status !== Item::STATUS_AVAILABLE) {
                throw new \InvalidArgumentException("Item {$item->sku} is not available for sale");
            }
            $lineTotal = $item->price * $quantity;
            $storeShare = $lineTotal * ($item->storeSharePct / 100);
            $consignorShare = $lineTotal * ($item->consignorSharePct / 100);
            $subtotal += $lineTotal;
            $lineItems[] = [
                'item' => $item,
                'quantity' => $quantity,
                'unit_price' => $item->price,
                'store_share' => $storeShare,
                'consignor_share' => $consignorShare,
            ];
        }

        $tax = $taxExempt ? 0.0 : $taxAmount;
        $total = $subtotal - $discountAmount + $tax;
        $paymentTotal = 0.0;
        foreach ($payments as $p) {
            $paymentTotal += (float) ($p['amount'] ?? 0);
        }
        if (abs($paymentTotal - $total) > 0.01) {
            throw new \InvalidArgumentException('Payment total does not match sale total');
        }

        $saleId = $this->saleRepository->create($registerId, $locationId, $userId, $subtotal, $discountAmount, $tax, $total);

        foreach ($lineItems as $line) {
            $item = $line['item'];
            $this->saleRepository->addSaleItem($saleId, $item->id, $item->consignorId, $line['quantity'], $line['unit_price'], 0, 0, $line['store_share'], $line['consignor_share']);
            $this->itemRepository->updateStatus($item->id, Item::STATUS_SOLD);
            if ($item->consignorId !== null) {
                $this->consignorService->recordManualAdjustment($item->consignorId, $line['consignor_share'], 'Sale ' . $saleId);
            }
        }

        foreach ($payments as $p) {
            $method = (string) ($p['method'] ?? 'cash');
            $amount = (float) ($p['amount'] ?? 0);
            $reference = isset($p['reference']) && $p['reference'] !== '' ? (string) $p['reference'] : null;
            if ($amount <= 0) {
                continue;
            }
            if ($method === 'store_credit' && isset($p['store_credit_id']) && $p['store_credit_id'] !== '') {
                $this->storeCreditRepository->deduct((string) $p['store_credit_id'], $amount);
            }
            if ($method === 'gift_card' && isset($p['gift_card_id']) && $p['gift_card_id'] !== '') {
                $this->giftCardRepository->deduct((string) $p['gift_card_id'], $amount);
            }
            $this->paymentRepository->addPayment($saleId, $method, $amount, $reference);
        }

        $sale = $this->saleRepository->findById($saleId);
        return [
            'sale_id' => $saleId,
            'sale_number' => $sale?->saleNumber ?? '',
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    /**
     * Virtual terminal: keyed card sale (phone/remote). Creates a sale with no inventory line and one card payment.
     *
     * @return array{sale_id: string, sale_number: string, total: float, reference: string}
     */
    public function chargeCardKeyed(
        string $userId,
        ?string $registerId,
        ?string $locationId,
        float $amount,
        string $paymentMethodId,
        string $description = 'Keyed sale'
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than 0');
        }
        $amountCents = (int) round($amount * 100);
        if ($amountCents < 1) {
            throw new \InvalidArgumentException('Amount too small');
        }
        $reference = $this->paymentProcessor->charge($amountCents, $paymentMethodId, $description);
        $saleId = $this->saleRepository->create($registerId, $locationId, $userId, $amount, 0.0, 0.0, $amount);
        $this->saleRepository->addSaleItem($saleId, null, null, 1, $amount, 0, 0, $amount, 0.0);
        $this->paymentRepository->addPayment($saleId, 'card', $amount, $reference);
        $sale = $this->saleRepository->findById($saleId);
        return [
            'sale_id' => $saleId,
            'sale_number' => $sale?->saleNumber ?? '',
            'total' => $amount,
            'reference' => $reference,
        ];
    }

    public function voidSale(string $saleId, string $userId): void
    {
        $sale = $this->saleRepository->findById($saleId);
        if ($sale === null) {
            throw new \InvalidArgumentException('Sale not found');
        }
        if ($sale->status !== 'completed') {
            throw new \InvalidArgumentException('Sale cannot be voided');
        }
        $this->saleRepository->voidSale($saleId);
    }

    public function holdCart(string $userId, array $cart, array $payments): string
    {
        return $this->heldSaleRepository->create($userId, ['cart' => $cart, 'payments' => $payments]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listHeld(string $userId): array
    {
        return $this->heldSaleRepository->listByUser($userId);
    }

    public function getHeld(string $heldId): ?array
    {
        return $this->heldSaleRepository->findById($heldId);
    }

    /**
     * Checkout using a previously held cart. Returns same shape as checkout(). Deletes the hold on success.
     */
    public function checkoutFromHold(string $heldId, string $userId, ?string $registerId, ?string $locationId): array
    {
        $held = $this->heldSaleRepository->findById($heldId);
        if ($held === null) {
            throw new \InvalidArgumentException('Held sale not found');
        }
        $cart = $held['cart_data']['cart'] ?? [];
        $payments = $held['cart_data']['payments'] ?? [];
        if ($cart === []) {
            throw new \InvalidArgumentException('Held cart is empty');
        }
        $result = $this->checkout($userId, $registerId, $locationId, $cart, $payments);
        $this->heldSaleRepository->delete($heldId);
        return $result;
    }

    /**
     * Full refund: reverse consignor share, set items back to available, record refund payment, mark sale refunded.
     */
    public function refund(string $saleId, string $userId): array
    {
        $sale = $this->saleRepository->findById($saleId);
        if ($sale === null) {
            throw new \InvalidArgumentException('Sale not found');
        }
        if ($sale->status !== Sale::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Sale cannot be refunded');
        }
        $items = $this->saleRepository->getSaleItems($saleId);
        foreach ($items as $row) {
            if (isset($row['item_id']) && $row['item_id'] !== null && $row['item_id'] !== '') {
                $this->itemRepository->updateStatus($row['item_id'], Item::STATUS_AVAILABLE);
            }
            if (isset($row['consignor_id']) && $row['consignor_id'] !== null && $row['consignor_id'] !== '') {
                $share = (float) ($row['consignor_share'] ?? 0);
                if ($share > 0) {
                    $this->consignorService->recordManualAdjustment($row['consignor_id'], -$share, 'Refund ' . $saleId);
                }
            }
        }
        $this->paymentRepository->addPayment($saleId, 'refund', -$sale->total, null);
        $this->saleRepository->markRefunded($saleId);
        return ['sale_id' => $saleId, 'refunded_amount' => $sale->total];
    }
}
