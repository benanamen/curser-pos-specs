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
}
