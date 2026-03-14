<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Booth\Booth;
use CurserPos\Domain\Booth\BoothRepository;
use CurserPos\Domain\Booth\ConsignorBoothAssignment;
use CurserPos\Domain\Booth\ConsignorBoothAssignmentRepository;
use CurserPos\Domain\Booth\RentDeductionRepository;
use CurserPos\Service\BoothRentalService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class BoothRentalServiceTest extends TestCase
{
    public function testAssignToBoothThrowsWhenBoothNotFound(): void
    {
        $boothRepo = $this->createMock(BoothRepository::class);
        $boothRepo->method('findById')->willReturn(null);
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $rentRepo = $this->createMock(RentDeductionRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Booth not found');
        $service->assignToBooth('c1', 'b-nonexistent');
    }

    public function testAssignToBoothSuccess(): void
    {
        $booth = new Booth('b1', 'Booth 1', null, 100.0, 'active', new \DateTimeImmutable(), new \DateTimeImmutable());
        $boothRepo = $this->createMock(BoothRepository::class);
        $boothRepo->method('findById')->willReturn($booth);
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $assignmentRepo->method('assign')->willReturn('a1');
        $rentRepo = $this->createMock(RentDeductionRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $id = $service->assignToBooth('c1', 'b1');
        $this->assertSame('a1', $id);
    }

    public function testEndAssignment(): void
    {
        $boothRepo = $this->createMock(BoothRepository::class);
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $assignmentRepo->expects($this->once())->method('endAssignment');

        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $service->endAssignment('c1');
    }

    public function testGetRentDueReturnsNullWhenNoAssignment(): void
    {
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $assignmentRepo->method('getActiveByConsignorId')->willReturn(null);
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $boothRepo = $this->createMock(BoothRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $this->assertNull($service->getRentDue('c1'));
    }

    public function testGetRentDueReturnsAmountWhenAssignmentExists(): void
    {
        $assignment = new ConsignorBoothAssignment(
            'a1',
            'c1',
            'b1',
            new \DateTimeImmutable('2025-01-01'),
            null,
            100.0,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $assignmentRepo->method('getActiveByConsignorId')->willReturn($assignment);
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $rentRepo->method('getLastDeductionDate')->willReturn(null);
        $boothRepo = $this->createMock(BoothRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $result = $service->getRentDue('c1', new \DateTimeImmutable('2025-02-15'));
        $this->assertNotNull($result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('period_start', $result);
        $this->assertArrayHasKey('period_end', $result);
    }

    public function testGetRentDueRespectsLastDeductionDate(): void
    {
        $assignment = new ConsignorBoothAssignment(
            'a1',
            'c1',
            'b1',
            new \DateTimeImmutable('2025-01-01'),
            null,
            100.0,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $assignmentRepo->method('getActiveByConsignorId')->willReturn($assignment);

        $lastDeduction = new \DateTimeImmutable('2025-01-31');
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $rentRepo->method('getLastDeductionDate')->willReturn($lastDeduction);

        $boothRepo = $this->createMock(BoothRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $result = $service->getRentDue('c1', new \DateTimeImmutable('2025-02-10'), 1);

        $this->assertNotNull($result);
        $this->assertSame(35.71, $result['amount']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['period_start']);
        $this->assertSame('2025-02-01', $result['period_start']->format('Y-m-d'));
    }

    public function testGetRentDueProratesPartialFirstMonth(): void
    {
        $assignment = new ConsignorBoothAssignment(
            'a1',
            'c1',
            'b1',
            new \DateTimeImmutable('2025-01-15'),
            null,
            100.0,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $assignmentRepo->method('getActiveByConsignorId')->willReturn($assignment);
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $rentRepo->method('getLastDeductionDate')->willReturn(null);
        $boothRepo = $this->createMock(BoothRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $result = $service->getRentDue('c1', new \DateTimeImmutable('2025-01-31'), 1);

        $this->assertNotNull($result);
        $this->assertSame(54.84, $result['amount']);
        $this->assertSame('2025-01-15', $result['period_start']->format('Y-m-d'));
        $this->assertSame('2025-01-31', $result['period_end']->format('Y-m-d'));
    }

    public function testGetRentDueProratesFullMonthPlusPartial(): void
    {
        $assignment = new ConsignorBoothAssignment(
            'a1',
            'c1',
            'b1',
            new \DateTimeImmutable('2025-01-01'),
            null,
            100.0,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $assignmentRepo->method('getActiveByConsignorId')->willReturn($assignment);
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $rentRepo->method('getLastDeductionDate')->willReturn(null);
        $boothRepo = $this->createMock(BoothRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $result = $service->getRentDue('c1', new \DateTimeImmutable('2025-03-10'), 1);

        $this->assertNotNull($result);
        $this->assertSame(232.26, $result['amount']);
        $this->assertSame('2025-01-01', $result['period_start']->format('Y-m-d'));
        $this->assertSame('2025-03-10', $result['period_end']->format('Y-m-d'));
    }

    public function testGetRentDueReturnsNullWhenThroughBeforeStart(): void
    {
        $assignment = new ConsignorBoothAssignment(
            'a1',
            'c1',
            'b1',
            new \DateTimeImmutable('2025-03-01'),
            null,
            100.0,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $assignmentRepo->method('getActiveByConsignorId')->willReturn($assignment);
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $boothRepo = $this->createMock(BoothRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $this->assertNull($service->getRentDue('c1', new \DateTimeImmutable('2025-02-01')));
    }

    public function testRecordDeductionWithNullPayoutId(): void
    {
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $rentRepo->method('record')->willReturn('rd1');
        $boothRepo = $this->createMock(BoothRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $id = $service->recordDeduction(
            'c1',
            50.0,
            new \DateTimeImmutable('2025-02-01'),
            new \DateTimeImmutable('2025-02-28'),
            null
        );
        $this->assertSame('rd1', $id);
    }

    public function testRecordDeduction(): void
    {
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $rentRepo->method('record')->willReturn('rd1');
        $boothRepo = $this->createMock(BoothRepository::class);

        $service = new BoothRentalService($assignmentRepo, $rentRepo, $boothRepo);
        $id = $service->recordDeduction(
            'c1',
            100.0,
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-01-31'),
            'p1'
        );
        $this->assertSame('rd1', $id);
    }
}
