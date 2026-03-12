<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Booth\RentDeductionRepository;
use CurserPos\Service\ReportService;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class ReportServiceTest extends TestCase
{
    public function testSalesSummary(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn([
            'total_sales' => 5,
            'total_amount' => 500.0,
            'total_discounts' => 10.0,
            'total_tax' => 40.0,
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $service = new ReportService($pdo, $rentRepo);
        $result = $service->salesSummary();
        $this->assertSame(5, $result['total_sales']);
        $this->assertSame(500.0, $result['total_amount']);
    }

    public function testSalesSummaryReturnsEmptyWhenNoRows(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $service = new ReportService($pdo, $rentRepo);
        $result = $service->salesSummary();
        $this->assertSame(0, $result['total_sales']);
    }

    public function testSalesByConsignor(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetchAll')->willReturn([
            ['consignor_id' => 'c1', 'sale_count' => 3, 'total_share' => 150.0],
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $service = new ReportService($pdo, $rentRepo);
        $result = $service->salesByConsignor();
        $this->assertCount(1, $result);
        $this->assertSame('c1', $result[0]['consignor_id']);
    }

    public function testInventorySummary(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['status' => 'available', 'count' => 10, 'total_value' => 500.0],
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $service = new ReportService($pdo, $rentRepo);
        $result = $service->inventorySummary();
        $this->assertCount(1, $result);
    }

    public function testPayoutsSummary(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetchAll')->willReturn([
            ['method' => 'check', 'count' => 2, 'total' => 200.0],
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $service = new ReportService($pdo, $rentRepo);
        $result = $service->payoutsSummary();
        $this->assertCount(1, $result);
    }

    public function testDashboardMetrics(): void
    {
        $todayStmt = $this->createMock(PDOStatement::class);
        $todayStmt->method('execute');
        $todayStmt->method('fetch')->willReturn(['cnt' => 2, 'total' => 100.0]);
        $weekStmt = $this->createMock(PDOStatement::class);
        $weekStmt->method('execute');
        $weekStmt->method('fetch')->willReturn(['cnt' => 10, 'total' => 500.0]);
        $itemsStmt = $this->createMock(PDOStatement::class);
        $itemsStmt->method('execute');
        $itemsStmt->method('fetchColumn')->willReturn(25);
        $consignorsStmt = $this->createMock(PDOStatement::class);
        $consignorsStmt->method('execute');
        $consignorsStmt->method('fetchColumn')->willReturn(5);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($todayStmt, $weekStmt, $itemsStmt, $consignorsStmt);

        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $service = new ReportService($pdo, $rentRepo);
        $result = $service->dashboardMetrics();
        $this->assertSame(2, $result['sales_today_count']);
        $this->assertSame(25, $result['inventory_available_count']);
    }

    public function testVendorMallSummary(): void
    {
        $salesStmt = $this->createMock(PDOStatement::class);
        $salesStmt->method('execute');
        $salesStmt->method('fetchAll')->willReturn([]);
        $assignStmt = $this->createMock(PDOStatement::class);
        $assignStmt->method('execute');
        $assignStmt->method('fetchColumn')->willReturn(3);
        $boothStmt = $this->createMock(PDOStatement::class);
        $boothStmt->method('execute');
        $boothStmt->method('fetchColumn')->willReturn(5);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($salesStmt, $assignStmt);
        $pdo->method('query')->willReturn($boothStmt);
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $rentRepo->method('sumCollected')->willReturn(500.0);

        $service = new ReportService($pdo, $rentRepo);
        $result = $service->vendorMallSummary();
        $this->assertArrayHasKey('sales_by_vendor', $result);
        $this->assertSame(500.0, $result['rent_collected']);
    }
}
