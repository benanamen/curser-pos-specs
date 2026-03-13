<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Consignor\Consignor;
use CurserPos\Domain\Consignor\ConsignorBalance;
use CurserPos\Domain\Consignor\ConsignorRepository;
use CurserPos\Domain\Payout\PayoutRepository;
use CurserPos\Service\BoothRentalService;
use CurserPos\Service\ConsignorService;
use CurserPos\Service\PayoutService;
use PHPUnit\Framework\TestCase;

final class PayoutServiceTest extends TestCase
{
    public function testRunPayoutRunWithNoConsignors(): void
    {
        $consignorRepo = $this->createMock(ConsignorRepository::class);
        $consignorRepo->method('findAll')->willReturn([]);
        $consignorService = $this->createMock(ConsignorService::class);
        $payoutRepo = $this->createMock(PayoutRepository::class);
        $boothService = $this->createMock(BoothRentalService::class);

        $service = new PayoutService($consignorRepo, $consignorService, $payoutRepo, $boothService);
        $result = $service->runPayoutRun([], 10.0);
        $this->assertSame([], $result);
    }

    public function testRunPayoutRunSkipsWhenBelowMinimum(): void
    {
        $consignor = new Consignor('c1', 's1', null, 'Name', null, null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $balance = new ConsignorBalance('c1', 5.0, 0.0, 0.0, new \DateTimeImmutable());

        $consignorRepo = $this->createMock(ConsignorRepository::class);
        $consignorRepo->method('findById')->willReturn($consignor);
        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->method('getBalance')->willReturn($balance);
        $consignorService->expects($this->never())->method('deductRentOnly');
        $payoutRepo = $this->createMock(PayoutRepository::class);
        $boothService = $this->createMock(BoothRentalService::class);
        $boothService->method('getRentDue')->willReturn(null);

        $service = new PayoutService($consignorRepo, $consignorService, $payoutRepo, $boothService);
        $result = $service->runPayoutRun(['c1'], 10.0);
        $this->assertSame([], $result);
    }

    public function testRunPayoutRunPaysWhenNoRent(): void
    {
        $consignor = new Consignor('c1', 's1', null, 'Name', null, null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $balance = new ConsignorBalance('c1', 100.0, 0.0, 0.0, new \DateTimeImmutable());

        $consignorRepo = $this->createMock(ConsignorRepository::class);
        $consignorRepo->method('findById')->willReturn($consignor);
        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->method('getBalance')->willReturn($balance);
        $consignorService->expects($this->once())->method('deductForPayout')->with('c1', 100.0);
        $payoutRepo = $this->createMock(PayoutRepository::class);
        $payoutRepo->method('create')->willReturn('p1');
        $payoutRepo->expects($this->once())->method('markProcessed')->with('p1');
        $boothService = $this->createMock(BoothRentalService::class);
        $boothService->method('getRentDue')->willReturn(null);

        $service = new PayoutService($consignorRepo, $consignorService, $payoutRepo, $boothService);
        $result = $service->runPayoutRun(['c1'], 10.0);
        $this->assertCount(1, $result);
        $this->assertSame(100.0, $result[0]['amount']);
        $this->assertSame(0.0, $result[0]['rent_deducted']);
    }

    public function testRunPayoutRunUsesDefaultMethodWhenInvalid(): void
    {
        $consignor = new Consignor('c1', 's1', null, 'Name', null, null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $balance = new ConsignorBalance('c1', 100.0, 0.0, 0.0, new \DateTimeImmutable());

        $consignorRepo = $this->createMock(ConsignorRepository::class);
        $consignorRepo->method('findById')->willReturn($consignor);
        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->method('getBalance')->willReturn($balance);
        $payoutRepo = $this->createMock(PayoutRepository::class);
        $payoutRepo->method('create')->willReturn('p1');
        $boothService = $this->createMock(BoothRentalService::class);
        $boothService->method('getRentDue')->willReturn(null);

        $service = new PayoutService($consignorRepo, $consignorService, $payoutRepo, $boothService);
        $result = $service->runPayoutRun(['c1'], 10.0, 'invalid');
        $this->assertSame('check', $result[0]['method']);
    }

    public function testRunPayoutRunPaysAndDeductsRent(): void
    {
        $consignor = new Consignor('c1', 's1', null, 'Name', null, null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $balance = new ConsignorBalance('c1', 200.0, 0.0, 0.0, new \DateTimeImmutable());

        $consignorRepo = $this->createMock(ConsignorRepository::class);
        $consignorRepo->method('findById')->willReturn($consignor);
        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->method('getBalance')->willReturn($balance);
        $consignorService
            ->expects($this->once())
            ->method('deductForPayoutAndRent')
            ->with('c1', 150.0, 50.0);

        $payoutRepo = $this->createMock(PayoutRepository::class);
        $payoutRepo->method('create')->willReturn('p1');
        $payoutRepo->expects($this->once())->method('markProcessed')->with('p1');

        $boothService = $this->createMock(BoothRentalService::class);
        $boothService->method('getRentDue')->willReturn([
            'amount' => 50.0,
            'period_start' => new \DateTimeImmutable('2025-01-01'),
            'period_end' => new \DateTimeImmutable('2025-01-31'),
        ]);
        $boothService
            ->expects($this->once())
            ->method('recordDeduction')
            ->with(
                'c1',
                50.0,
                $this->isInstanceOf(\DateTimeImmutable::class),
                $this->isInstanceOf(\DateTimeImmutable::class),
                'p1'
            );

        $service = new PayoutService($consignorRepo, $consignorService, $payoutRepo, $boothService);
        $result = $service->runPayoutRun(['c1'], 10.0, 'check');

        $this->assertCount(1, $result);
        $this->assertSame('p1', $result[0]['payout_id']);
        $this->assertSame('c1', $result[0]['consignor_id']);
        $this->assertSame(150.0, $result[0]['amount']);
        $this->assertSame(50.0, $result[0]['rent_deducted']);
    }

    public function testRunPayoutRunChargesRentEvenWhenBelowMinimum(): void
    {
        $consignor = new Consignor('c1', 's1', null, 'Name', null, null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $balance = new ConsignorBalance('c1', 50.0, 0.0, 0.0, new \DateTimeImmutable());

        $consignorRepo = $this->createMock(ConsignorRepository::class);
        $consignorRepo->method('findById')->willReturn($consignor);
        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->method('getBalance')->willReturn($balance);
        $consignorService
            ->expects($this->once())
            ->method('deductRentOnly')
            ->with('c1', 100.0);

        $payoutRepo = $this->createMock(PayoutRepository::class);
        $boothService = $this->createMock(BoothRentalService::class);
        $boothService->method('getRentDue')->willReturn([
            'amount' => 100.0,
            'period_start' => new \DateTimeImmutable('2025-01-01'),
            'period_end' => new \DateTimeImmutable('2025-01-31'),
        ]);
        $boothService
            ->expects($this->once())
            ->method('recordDeduction')
            ->with(
                'c1',
                100.0,
                $this->isInstanceOf(\DateTimeImmutable::class),
                $this->isInstanceOf(\DateTimeImmutable::class),
                null
            );

        $service = new PayoutService($consignorRepo, $consignorService, $payoutRepo, $boothService);
        $result = $service->runPayoutRun(['c1'], 50.1, 'check');

        $this->assertSame([], $result);
    }

    public function testPreviewPayoutRunSkipsBelowMinimum(): void
    {
        $consignor = new Consignor('c1', 's1', null, 'Name', null, null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $balance = new ConsignorBalance('c1', 20.0, 0.0, 0.0, new \DateTimeImmutable());

        $consignorRepo = $this->createMock(ConsignorRepository::class);
        $consignorRepo->method('findAll')->willReturn([$consignor]);
        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->method('getBalance')->willReturn($balance);

        $payoutRepo = $this->createMock(PayoutRepository::class);
        $boothService = $this->createMock(BoothRentalService::class);
        $boothService->method('getRentDue')->willReturn(['amount' => 10.0, 'period_start' => new \DateTimeImmutable(), 'period_end' => new \DateTimeImmutable()]);

        $service = new PayoutService($consignorRepo, $consignorService, $payoutRepo, $boothService);
        $result = $service->previewPayoutRun(20.1);

        $this->assertSame([], $result);
    }

    public function testPreviewPayoutRunIncludesConsignorWhenAboveMinimum(): void
    {
        $consignor = new Consignor('c1', 's1', null, 'Name', null, null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $balance = new ConsignorBalance('c1', 100.0, 0.0, 0.0, new \DateTimeImmutable());

        $consignorRepo = $this->createMock(ConsignorRepository::class);
        $consignorRepo->method('findAll')->willReturn([$consignor]);
        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->method('getBalance')->willReturn($balance);

        $payoutRepo = $this->createMock(PayoutRepository::class);
        $boothService = $this->createMock(BoothRentalService::class);
        $boothService->method('getRentDue')->willReturn(['amount' => 25.0, 'period_start' => new \DateTimeImmutable(), 'period_end' => new \DateTimeImmutable()]);

        $service = new PayoutService($consignorRepo, $consignorService, $payoutRepo, $boothService);
        $result = $service->previewPayoutRun(10.0);

        $this->assertCount(1, $result);
        $this->assertSame('c1', $result[0]['consignor_id']);
        $this->assertSame('Name', $result[0]['name']);
        $this->assertSame(100.0, $result[0]['balance']);
        $this->assertSame(25.0, $result[0]['rent_due']);
        $this->assertSame(75.0, $result[0]['payout_amount']);
    }
}
