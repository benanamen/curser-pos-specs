<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Consignor\Consignor;
use CurserPos\Domain\Consignor\ConsignorBalance;
use CurserPos\Domain\Consignor\ConsignorRepository;
use CurserPos\Service\ConsignorService;
use PHPUnit\Framework\TestCase;

final class ConsignorServiceTest extends TestCase
{
    public function testCreateConsignorThrowsWhenSlugExists(): void
    {
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('slugExists')->with('existing')->willReturn(true);

        $service = new ConsignorService($repo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Consignor slug 'existing' already exists");
        $service->createConsignor('existing', 'Name');
    }

    public function testCreateConsignorSuccess(): void
    {
        $consignor = new Consignor(
            'c1',
            'slug1',
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
        $repo->method('slugExists')->willReturn(false);
        $repo->method('create')->willReturn('c1');
        $repo->method('findById')->with('c1')->willReturn($consignor);

        $service = new ConsignorService($repo);
        $result = $service->createConsignor('slug1', 'Name');
        $this->assertSame('c1', $result->id);
    }

    public function testUpdateConsignorThrowsWhenSlugExistsForOther(): void
    {
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('slugExists')->with('taken', 'c1')->willReturn(true);

        $service = new ConsignorService($repo);
        $this->expectException(\InvalidArgumentException::class);
        $service->updateConsignor('c1', 'taken', 'Name', null, null, null, null, 50.0, null, null);
    }

    public function testUpdateConsignorSuccess(): void
    {
        $consignor = new Consignor('c1', 'slug1', null, 'Name', null, null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('slugExists')->willReturn(false);
        $repo->method('update');
        $repo->method('findById')->willReturn($consignor);

        $service = new ConsignorService($repo);
        $result = $service->updateConsignor('c1', 'slug1', 'Name', null, null, null, null, 50.0, null, null);
        $this->assertSame('c1', $result->id);
    }

    public function testGetBalanceReturnsZeroWhenNull(): void
    {
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('getBalance')->willReturn(null);

        $service = new ConsignorService($repo);
        $balance = $service->getBalance('c1');
        $this->assertSame(0.0, $balance->balance);
    }

    public function testGetBalanceReturnsBalanceWhenFound(): void
    {
        $balance = new ConsignorBalance('c1', 100.0, 50.0, 50.0, new \DateTimeImmutable());
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('getBalance')->willReturn($balance);

        $service = new ConsignorService($repo);
        $result = $service->getBalance('c1');
        $this->assertSame(100.0, $result->balance);
    }

    public function testRecordManualAdjustment(): void
    {
        $balance = new ConsignorBalance('c1', 100.0, 0.0, 0.0, new \DateTimeImmutable());
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('getBalance')->willReturn($balance);
        $repo->expects($this->once())->method('updateBalance')->with('c1', 150.0, 0.0, 0.0);

        $service = new ConsignorService($repo);
        $service->recordManualAdjustment('c1', 50.0, 'Adjustment');
    }

    public function testDeductForPayout(): void
    {
        $balance = new ConsignorBalance('c1', 100.0, 0.0, 0.0, new \DateTimeImmutable());
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('getBalance')->willReturn($balance);
        $repo->expects($this->once())->method('updateBalance')->with('c1', 50.0, 0.0, 50.0);

        $service = new ConsignorService($repo);
        $service->deductForPayout('c1', 50.0);
    }

    public function testDeductForPayoutAndRent(): void
    {
        $balance = new ConsignorBalance('c1', 100.0, 0.0, 0.0, new \DateTimeImmutable());
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('getBalance')->willReturn($balance);
        $repo->expects($this->once())->method('updateBalance')->with('c1', 40.0, 0.0, 50.0);

        $service = new ConsignorService($repo);
        $service->deductForPayoutAndRent('c1', 50.0, 10.0);
    }

    public function testDeductRentOnlySkipsWhenZeroOrNegative(): void
    {
        $balance = new ConsignorBalance('c1', 100.0, 0.0, 10.0, new \DateTimeImmutable());
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('getBalance')->willReturn($balance);
        $repo->expects($this->never())->method('updateBalance');

        $service = new ConsignorService($repo);
        $service->deductRentOnly('c1', 0.0);
        $service->deductRentOnly('c1', -5.0);
    }

    public function testDeductRentOnlyReducesBalanceWithoutChangingPaidOut(): void
    {
        $balance = new ConsignorBalance('c1', 100.0, 0.0, 10.0, new \DateTimeImmutable());
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('getBalance')->willReturn($balance);
        $repo->expects($this->once())->method('updateBalance')->with('c1', 60.0, 0.0, 10.0);

        $service = new ConsignorService($repo);
        $service->deductRentOnly('c1', 40.0);
    }

    public function testBulkImportFromCsvEmpty(): void
    {
        $repo = $this->createMock(ConsignorRepository::class);
        $service = new ConsignorService($repo);
        $result = $service->bulkImportFromCsv('');
        $this->assertSame([], $result['created']);
        $this->assertCount(1, $result['errors']);
    }

    public function testBulkImportFromCsvValid(): void
    {
        $consignor = new Consignor('c1', 'john', null, 'John', null, null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('slugExists')->willReturn(false);
        $repo->method('create')->willReturn('c1');
        $repo->method('findById')->willReturn($consignor);

        $service = new ConsignorService($repo);
        $csv = "slug,name,email\njohn,John Doe,john@test.com";
        $result = $service->bulkImportFromCsv($csv);
        $this->assertCount(1, $result['created']);
        $this->assertSame('john', $result['created'][0]['slug']);
    }
}
