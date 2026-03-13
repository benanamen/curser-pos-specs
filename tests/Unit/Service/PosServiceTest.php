<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Consignor\Consignor;
use CurserPos\Domain\Consignor\ConsignorRepository;
use CurserPos\Domain\Item\Item;
use CurserPos\Domain\Item\ItemRepository;
use CurserPos\Domain\Sale\GiftCardRepository;
use CurserPos\Domain\Sale\HeldSaleRepository;
use CurserPos\Domain\Sale\ItemHoldRepository;
use CurserPos\Domain\Sale\PaymentRepository;
use CurserPos\Domain\Sale\Sale;
use CurserPos\Domain\Sale\SaleRepository;
use CurserPos\Domain\Sale\StoreCreditRepository;
use CurserPos\Infrastructure\Payment\PaymentProcessorInterface;
use CurserPos\Service\ConsignorService;
use CurserPos\Service\PosService;
use PHPUnit\Framework\TestCase;

final class PosServiceTest extends TestCase
{
    private function createItem(
        string $id = 'item-1',
        string $sku = 'SKU001',
        ?string $consignorId = 'cons-1',
        float $price = 25.0,
        float $storeSharePct = 50.0,
        float $consignorSharePct = 50.0,
        string $status = Item::STATUS_AVAILABLE
    ): Item {
        $now = new \DateTimeImmutable();
        return new Item(
            $id,
            $sku,
            null,
            $consignorId,
            null,
            null,
            null,
            null,
            null,
            $price,
            $storeSharePct,
            $consignorSharePct,
            $status,
            $now,
            null,
            $now,
            $now
        );
    }

    private function createSale(string $id = 'sale-1', string $status = Sale::STATUS_COMPLETED, float $total = 50.0): Sale
    {
        $now = new \DateTimeImmutable();
        return new Sale($id, null, null, 'user-1', 'S001', 50.0, 0.0, 0.0, $total, $status, $now, $now);
    }

    public function testCheckoutThrowsWhenCartEmpty(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE item_holds (item_id TEXT, held_id TEXT, user_id TEXT, created_at TEXT)');
        $saleRepo = $this->createMock(SaleRepository::class);
        $paymentRepo = $this->createMock(PaymentRepository::class);
        $itemRepo = $this->createMock(ItemRepository::class);
        $consignorService = $this->createMock(ConsignorService::class);
        $processor = $this->createMock(PaymentProcessorInterface::class);
        $heldRepo = $this->createMock(HeldSaleRepository::class);
        $itemHoldRepo = new ItemHoldRepository($pdo);
        $storeCreditRepo = $this->createMock(StoreCreditRepository::class);
        $giftCardRepo = $this->createMock(GiftCardRepository::class);
        $consignorRepo = $this->createDefaultConsignorRepository();

        $service = new PosService($pdo, $saleRepo, $paymentRepo, $itemRepo, $consignorRepo, $consignorService, $processor, $heldRepo, $itemHoldRepo, $storeCreditRepo, $giftCardRepo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cart cannot be empty');
        $service->checkout('user-1', null, null, [], []);
    }

    public function testCheckoutThrowsWhenItemNotFound(): void
    {
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->with('bad-id')->willReturn(null);

        $service = $this->createPosService(['itemRepository' => $itemRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item not found: bad-id');
        $service->checkout('user-1', null, null, [['item_id' => 'bad-id', 'quantity' => 1]], [['method' => 'cash', 'amount' => 25.0]]);
    }

    public function testCheckoutThrowsWhenItemNotAvailable(): void
    {
        $item = $this->createItem(status: Item::STATUS_SOLD);
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);

        $service = $this->createPosService(['itemRepository' => $itemRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item SKU001 is not available for sale');
        $service->checkout('user-1', null, null, [['item_id' => 'item-1', 'quantity' => 1]], [['method' => 'cash', 'amount' => 25.0]]);
    }

    public function testCheckoutThrowsWhenPaymentTotalMismatch(): void
    {
        $item = $this->createItem();
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);

        $service = $this->createPosService(['itemRepository' => $itemRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment total does not match sale total');
        $service->checkout('user-1', null, null, [['item_id' => 'item-1', 'quantity' => 1]], [['method' => 'cash', 'amount' => 10.0]]);
    }

    public function testCheckoutSuccess(): void
    {
        $item = $this->createItem();
        $sale = $this->createSale();
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);
        $itemRepo->expects($this->once())->method('updateStatus')->with('item-1', Item::STATUS_SOLD);

        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('create')->willReturn('sale-1');
        $saleRepo->method('findById')->willReturn($sale);
        $saleRepo->expects($this->once())->method('addSaleItem');

        $paymentRepo = $this->createMock(PaymentRepository::class);
        $paymentRepo->expects($this->once())->method('addPayment')->with('sale-1', 'cash', 25.0, null);

        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->expects($this->once())->method('recordManualAdjustment')->with('cons-1', 12.5, 'Sale sale-1');

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'paymentRepository' => $paymentRepo,
            'itemRepository' => $itemRepo,
            'consignorService' => $consignorService,
        ]);
        $result = $service->checkout('user-1', null, null, [['item_id' => 'item-1', 'quantity' => 1]], [['method' => 'cash', 'amount' => 25.0]]);
        $this->assertSame('sale-1', $result['sale_id']);
        $this->assertSame(25.0, $result['subtotal']);
        $this->assertSame(25.0, $result['total']);
    }

    public function testCheckoutWithStoreCreditAndGiftCard(): void
    {
        $item = $this->createItem(price: 100.0);
        $sale = $this->createSale(total: 100.0);
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);

        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('create')->willReturn('sale-1');
        $saleRepo->method('findById')->willReturn($sale);
        $saleRepo->expects($this->once())->method('addSaleItem');

        $paymentRepo = $this->createMock(PaymentRepository::class);
        $paymentRepo->expects($this->exactly(2))->method('addPayment');

        $storeCreditRepo = $this->createMock(StoreCreditRepository::class);
        $storeCreditRepo->expects($this->once())->method('deduct')->with('sc-1', 50.0);

        $giftCardRepo = $this->createMock(GiftCardRepository::class);
        $giftCardRepo->expects($this->once())->method('deduct')->with('gc-1', 50.0);

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'paymentRepository' => $paymentRepo,
            'itemRepository' => $itemRepo,
            'storeCreditRepository' => $storeCreditRepo,
            'giftCardRepository' => $giftCardRepo,
        ]);
        $payments = [
            ['method' => 'store_credit', 'amount' => 50.0, 'store_credit_id' => 'sc-1'],
            ['method' => 'gift_card', 'amount' => 50.0, 'gift_card_id' => 'gc-1'],
        ];
        $result = $service->checkout('user-1', null, null, [['item_id' => 'item-1', 'quantity' => 1]], $payments);
        $this->assertSame('sale-1', $result['sale_id']);
    }

    public function testCheckoutSkipsConsignorAdjustmentWhenNoConsignor(): void
    {
        $item = $this->createItem(consignorId: null);
        $sale = $this->createSale();
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);

        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('create')->willReturn('sale-1');
        $saleRepo->method('findById')->willReturn($sale);

        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->expects($this->never())->method('recordManualAdjustment');

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'itemRepository' => $itemRepo,
            'consignorService' => $consignorService,
        ]);
        $service->checkout('user-1', null, null, [['item_id' => 'item-1', 'quantity' => 1]], [['method' => 'cash', 'amount' => 25.0]]);
    }

    public function testCheckoutSkipsInvalidCartEntries(): void
    {
        $item = $this->createItem();
        $sale = $this->createSale();
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);

        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('create')->willReturn('sale-1');
        $saleRepo->method('findById')->willReturn($sale);

        $service = $this->createPosService(['saleRepository' => $saleRepo, 'itemRepository' => $itemRepo]);
        $result = $service->checkout('user-1', null, null, [
            ['item_id' => '', 'quantity' => 1],
            ['item_id' => 'item-1', 'quantity' => 1],
        ], [['method' => 'cash', 'amount' => 25.0]]);
        $this->assertSame('sale-1', $result['sale_id']);
    }

    public function testChargeCardKeyedThrowsWhenAmountZero(): void
    {
        $service = $this->createPosService();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than 0');
        $service->chargeCardKeyed('user-1', null, null, 0.0, 'pm_123');
    }

    public function testChargeCardKeyedThrowsWhenAmountTooSmall(): void
    {
        $service = $this->createPosService();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount too small');
        $service->chargeCardKeyed('user-1', null, null, 0.001, 'pm_123');
    }

    public function testChargeCardKeyedSuccess(): void
    {
        $processor = $this->createMock(PaymentProcessorInterface::class);
        $processor->method('charge')->with(1000, 'pm_123', 'Keyed sale')->willReturn('ch_xyz');

        $sale = $this->createSale();
        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('create')->willReturn('sale-1');
        $saleRepo->method('findById')->willReturn($sale);
        $saleRepo->expects($this->once())->method('addSaleItem');

        $paymentRepo = $this->createMock(PaymentRepository::class);
        $paymentRepo->expects($this->once())->method('addPayment')->with('sale-1', 'card', 10.0, 'ch_xyz');

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'paymentRepository' => $paymentRepo,
            'paymentProcessor' => $processor,
        ]);
        $result = $service->chargeCardKeyed('user-1', null, null, 10.0, 'pm_123');
        $this->assertSame('sale-1', $result['sale_id']);
        $this->assertSame(10.0, $result['total']);
        $this->assertSame('ch_xyz', $result['reference']);
    }

    public function testVoidSaleThrowsWhenNotFound(): void
    {
        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('findById')->willReturn(null);

        $service = $this->createPosService(['saleRepository' => $saleRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sale not found');
        $service->voidSale('bad-id', 'user-1');
    }

    public function testVoidSaleThrowsWhenNotCompleted(): void
    {
        $sale = $this->createSale(status: Sale::STATUS_VOIDED);
        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('findById')->willReturn($sale);

        $service = $this->createPosService(['saleRepository' => $saleRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sale cannot be voided');
        $service->voidSale('sale-1', 'user-1');
    }

    public function testVoidSaleSuccess(): void
    {
        $sale = $this->createSale();
        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('findById')->willReturn($sale);
        $saleRepo->expects($this->once())->method('voidSale')->with('sale-1');

        $service = $this->createPosService(['saleRepository' => $saleRepo]);
        $service->voidSale('sale-1', 'user-1');
    }

    public function testHoldCartSuccess(): void
    {
        $item = $this->createItem(id: 'i1');
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->with('i1')->willReturn($item);

        $heldRepo = $this->createMock(HeldSaleRepository::class);
        $heldRepo->method('create')->willReturn('held-1');

        $service = $this->createPosService(['heldSaleRepository' => $heldRepo, 'itemRepository' => $itemRepo]);
        $result = $service->holdCart('user-1', [['item_id' => 'i1', 'quantity' => 1]], [['method' => 'cash', 'amount' => 10.0]]);
        $this->assertSame('held-1', $result);
    }

    public function testListHeldSuccess(): void
    {
        $heldRepo = $this->createMock(HeldSaleRepository::class);
        $heldRepo->method('listByUser')->with('user-1')->willReturn([['id' => 'held-1']]);

        $service = $this->createPosService(['heldSaleRepository' => $heldRepo]);
        $result = $service->listHeld('user-1');
        $this->assertSame([['id' => 'held-1']], $result);
    }

    public function testGetHeldSuccess(): void
    {
        $heldRepo = $this->createMock(HeldSaleRepository::class);
        $heldRepo->method('findById')->with('held-1')->willReturn(['id' => 'held-1', 'cart_data' => []]);

        $service = $this->createPosService(['heldSaleRepository' => $heldRepo]);
        $result = $service->getHeld('held-1');
        $this->assertNotNull($result);
        $this->assertSame('held-1', $result['id']);
    }

    public function testGetHeldReturnsNull(): void
    {
        $heldRepo = $this->createMock(HeldSaleRepository::class);
        $heldRepo->method('findById')->willReturn(null);

        $service = $this->createPosService(['heldSaleRepository' => $heldRepo]);
        $this->assertNull($service->getHeld('bad-id'));
    }

    public function testCheckoutFromHoldThrowsWhenNotFound(): void
    {
        $heldRepo = $this->createMock(HeldSaleRepository::class);
        $heldRepo->method('findById')->willReturn(null);

        $service = $this->createPosService(['heldSaleRepository' => $heldRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Held sale not found');
        $service->checkoutFromHold('bad-id', 'user-1', null, null);
    }

    public function testCheckoutFromHoldThrowsWhenCartEmpty(): void
    {
        $heldRepo = $this->createMock(HeldSaleRepository::class);
        $heldRepo->method('findById')->willReturn(['cart_data' => ['cart' => [], 'payments' => []]]);

        $service = $this->createPosService(['heldSaleRepository' => $heldRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Held cart is empty');
        $service->checkoutFromHold('held-1', 'user-1', null, null);
    }

    public function testCheckoutFromHoldSuccess(): void
    {
        $item = $this->createItem();
        $sale = $this->createSale();
        $held = ['cart_data' => ['cart' => [['item_id' => 'item-1', 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 25.0]]]];

        $heldRepo = $this->createMock(HeldSaleRepository::class);
        $heldRepo->method('findById')->willReturn($held);
        $heldRepo->expects($this->once())->method('delete')->with('held-1');

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);

        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('create')->willReturn('sale-1');
        $saleRepo->method('findById')->willReturn($sale);

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'itemRepository' => $itemRepo,
            'heldSaleRepository' => $heldRepo,
        ]);
        $result = $service->checkoutFromHold('held-1', 'user-1', null, null);
        $this->assertSame('sale-1', $result['sale_id']);
    }

    public function testRefundThrowsWhenNotFound(): void
    {
        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('findById')->willReturn(null);

        $service = $this->createPosService(['saleRepository' => $saleRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sale not found');
        $service->refund('bad-id', 'user-1');
    }

    public function testRefundThrowsWhenNotCompleted(): void
    {
        $sale = $this->createSale(status: Sale::STATUS_REFUNDED);
        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('findById')->willReturn($sale);

        $service = $this->createPosService(['saleRepository' => $saleRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sale cannot be refunded');
        $service->refund('sale-1', 'user-1');
    }

    public function testRefundSuccess(): void
    {
        $sale = $this->createSale(total: 50.0);
        $saleItems = [
            ['item_id' => 'item-1', 'consignor_id' => 'cons-1', 'consignor_share' => 12.5],
        ];
        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('findById')->willReturn($sale);
        $saleRepo->method('getSaleItems')->willReturn($saleItems);
        $saleRepo->expects($this->once())->method('markRefunded')->with('sale-1');

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->expects($this->once())->method('updateStatus')->with('item-1', Item::STATUS_AVAILABLE);

        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->expects($this->once())->method('recordManualAdjustment')->with('cons-1', -12.5, 'Refund sale-1');

        $paymentRepo = $this->createMock(PaymentRepository::class);
        $paymentRepo->method('getBySaleId')->with('sale-1')->willReturn([]);
        $paymentRepo->expects($this->once())->method('addPayment')->with('sale-1', 'refund', -50.0, null);

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'paymentRepository' => $paymentRepo,
            'itemRepository' => $itemRepo,
            'consignorService' => $consignorService,
        ]);
        $result = $service->refund('sale-1', 'user-1');
        $this->assertSame('sale-1', $result['sale_id']);
        $this->assertSame(50.0, $result['refunded_amount']);
    }

    public function testRefundSkipsItemWithoutItemId(): void
    {
        $sale = $this->createSale(total: 50.0);
        $saleItems = [
            ['item_id' => null, 'consignor_id' => 'cons-1', 'consignor_share' => 12.5],
        ];
        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('findById')->willReturn($sale);
        $saleRepo->method('getSaleItems')->willReturn($saleItems);

        $paymentRepo = $this->createMock(PaymentRepository::class);
        $paymentRepo->method('getBySaleId')->willReturn([]);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->expects($this->never())->method('updateStatus');

        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->expects($this->once())->method('recordManualAdjustment');

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'paymentRepository' => $paymentRepo,
            'itemRepository' => $itemRepo,
            'consignorService' => $consignorService,
        ]);
        $service->refund('sale-1', 'user-1');
    }

    public function testCheckoutWithCardChargesProcessorAndStoresReference(): void
    {
        $item = $this->createItem(price: 30.0);
        $sale = $this->createSale(total: 30.0);
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);
        $itemRepo->expects($this->once())->method('updateStatus')->with('item-1', Item::STATUS_SOLD);

        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('create')->willReturn('sale-1');
        $saleRepo->method('findById')->willReturn($sale);
        $saleRepo->expects($this->once())->method('addSaleItem')->with(
            'sale-1',
            'item-1',
            'cons-1',
            1,
            30.0,
            0,
            0,
            15.0,
            15.0
        );

        $processor = $this->createMock(PaymentProcessorInterface::class);
        $processor->expects($this->once())->method('charge')->with(3000, 'pm_abc', 'POS sale')->willReturn('ch_xyz');

        $paymentRepo = $this->createMock(PaymentRepository::class);
        $paymentRepo->expects($this->once())->method('addPayment')->with('sale-1', 'card', 30.0, 'ch_xyz');

        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->expects($this->once())->method('recordManualAdjustment')->with('cons-1', 15.0, 'Sale sale-1');

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'paymentRepository' => $paymentRepo,
            'itemRepository' => $itemRepo,
            'consignorService' => $consignorService,
            'paymentProcessor' => $processor,
        ]);
        $payments = [['method' => 'card', 'amount' => 30.0, 'payment_method_id' => 'pm_abc']];
        $result = $service->checkout('user-1', null, null, [['item_id' => 'item-1', 'quantity' => 1]], $payments);
        $this->assertSame('sale-1', $result['sale_id']);
    }

    public function testCheckoutThrowsWhenCardPaymentMissingPaymentMethodId(): void
    {
        $item = $this->createItem();
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);

        $service = $this->createPosService(['itemRepository' => $itemRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Card payment requires payment_method_id');
        $service->checkout('user-1', null, null, [['item_id' => 'item-1', 'quantity' => 1]], [['method' => 'card', 'amount' => 25.0]]);
    }

    public function testRefundReversesCardCharges(): void
    {
        $sale = $this->createSale(total: 50.0);
        $saleItems = [
            ['item_id' => 'item-1', 'consignor_id' => 'cons-1', 'consignor_share' => 25.0],
        ];
        $salePayments = [
            ['id' => 'pay-1', 'method' => 'card', 'amount' => 50.0, 'reference' => 'ch_xyz'],
        ];
        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('findById')->willReturn($sale);
        $saleRepo->method('getSaleItems')->willReturn($saleItems);
        $saleRepo->expects($this->once())->method('markRefunded')->with('sale-1');

        $paymentRepo = $this->createMock(PaymentRepository::class);
        $paymentRepo->method('getBySaleId')->with('sale-1')->willReturn($salePayments);
        $paymentRepo->expects($this->once())->method('addPayment')->with('sale-1', 'refund', -50.0, null);

        $processor = $this->createMock(PaymentProcessorInterface::class);
        $processor->expects($this->once())->method('refund')->with('ch_xyz', null)->willReturn('re_1');

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->expects($this->once())->method('updateStatus')->with('item-1', Item::STATUS_AVAILABLE);

        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->expects($this->once())->method('recordManualAdjustment')->with('cons-1', -25.0, 'Refund sale-1');

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'paymentRepository' => $paymentRepo,
            'paymentProcessor' => $processor,
            'itemRepository' => $itemRepo,
            'consignorService' => $consignorService,
        ]);
        $result = $service->refund('sale-1', 'user-1');
        $this->assertSame(50.0, $result['refunded_amount']);
    }

    public function testCheckoutWithItemLevelDiscount(): void
    {
        $item = $this->createItem(price: 40.0);
        $sale = $this->createSale(total: 35.0);
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);
        $itemRepo->expects($this->once())->method('updateStatus')->with('item-1', Item::STATUS_SOLD);

        $saleRepo = $this->createMock(SaleRepository::class);
        $saleRepo->method('create')->willReturn('sale-1');
        $saleRepo->method('findById')->willReturn($sale);
        $saleRepo->expects($this->once())->method('addSaleItem')->with(
            'sale-1',
            'item-1',
            'cons-1',
            1,
            40.0,
            5.0,
            0,
            17.5,
            17.5
        );

        $paymentRepo = $this->createMock(PaymentRepository::class);
        $paymentRepo->expects($this->once())->method('addPayment')->with('sale-1', 'cash', 35.0, null);

        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->expects($this->once())->method('recordManualAdjustment')->with('cons-1', 17.5, 'Sale sale-1');

        $service = $this->createPosService([
            'saleRepository' => $saleRepo,
            'paymentRepository' => $paymentRepo,
            'itemRepository' => $itemRepo,
            'consignorService' => $consignorService,
        ]);
        $cart = [['item_id' => 'item-1', 'quantity' => 1, 'discount_amount' => 5.0]];
        $result = $service->checkout('user-1', null, null, $cart, [['method' => 'cash', 'amount' => 35.0]]);
        $this->assertSame('sale-1', $result['sale_id']);
        $this->assertSame(35.0, $result['subtotal']);
        $this->assertSame(35.0, $result['total']);
    }

    public function testCheckoutThrowsWhenItemDiscountExceedsLineTotal(): void
    {
        $item = $this->createItem(price: 10.0);
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findById')->willReturn($item);

        $service = $this->createPosService(['itemRepository' => $itemRepo]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item discount for SKU001 exceeds line total');
        $service->checkout('user-1', null, null, [['item_id' => 'item-1', 'quantity' => 1, 'discount_amount' => 15.0]], [['method' => 'cash', 'amount' => 0.0]]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createPosService(array $overrides = []): PosService
    {
        $pdo = $overrides['pdo'] ?? new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE IF NOT EXISTS item_holds (item_id TEXT, held_id TEXT, user_id TEXT, created_at TEXT)');
        $saleRepo = $overrides['saleRepository'] ?? $this->createMock(SaleRepository::class);
        $paymentRepo = $overrides['paymentRepository'] ?? $this->createMock(PaymentRepository::class);
        $itemRepo = $overrides['itemRepository'] ?? $this->createMock(ItemRepository::class);
        $consignorService = $overrides['consignorService'] ?? $this->createMock(ConsignorService::class);
        $processor = $overrides['paymentProcessor'] ?? $this->createMock(PaymentProcessorInterface::class);
        $heldRepo = $overrides['heldSaleRepository'] ?? $this->createMock(HeldSaleRepository::class);
        $itemHoldRepo = $overrides['itemHoldRepository'] ?? new ItemHoldRepository($pdo);
        $storeCreditRepo = $overrides['storeCreditRepository'] ?? $this->createMock(StoreCreditRepository::class);
        $giftCardRepo = $overrides['giftCardRepository'] ?? $this->createMock(GiftCardRepository::class);
        $consignorRepo = $overrides['consignorRepository'] ?? $this->createDefaultConsignorRepository();

        return new PosService($pdo, $saleRepo, $paymentRepo, $itemRepo, $consignorRepo, $consignorService, $processor, $heldRepo, $itemHoldRepo, $storeCreditRepo, $giftCardRepo);
    }

    private function createDefaultConsignorRepository(): ConsignorRepository
    {
        $consignor = new Consignor(
            'cons-1',
            'c1',
            null,
            'Name',
            null,
            null,
            null,
            50.0,
            null,
            'active',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('findById')->willReturnCallback(function (string $id) use ($consignor): ?Consignor {
            return $id === 'cons-1' ? $consignor : null;
        });
        return $repo;
    }
}
