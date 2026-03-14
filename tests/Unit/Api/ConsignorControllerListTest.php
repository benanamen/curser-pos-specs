<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Api;

use CurserPos\Api\V1\ConsignorController;
use CurserPos\Domain\Booth\ConsignorBoothAssignmentRepository;
use CurserPos\Domain\Booth\RentDeductionRepository;
use CurserPos\Domain\Consignor\Consignor;
use CurserPos\Domain\Consignor\ConsignorBalance;
use CurserPos\Domain\Consignor\ConsignorRepository;
use CurserPos\Service\BoothRentalService;
use CurserPos\Service\ConsignorService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class ConsignorControllerListTest extends TestCase
{
    public function testListReturnsActiveConsignorsWithBalances(): void
    {
        $c1 = new Consignor('id-1', 'calvin', null, 'Calvin', 'calvin@example.com', null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $c2 = new Consignor('id-2', 'sally5', null, 'Sally Five', 'sally5@example.com', null, null, 50.0, null, 'active', null, new \DateTimeImmutable(), new \DateTimeImmutable());

        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('findAll')->with('active')->willReturn([$c1, $c2]);

        $consignorService = $this->createMock(ConsignorService::class);
        $consignorService->method('getBalance')->willReturnMap([
            ['id-1', new ConsignorBalance('id-1', 10.0, 0.0, 0.0, new \DateTimeImmutable())],
            ['id-2', new ConsignorBalance('id-2', 20.0, 0.0, 0.0, new \DateTimeImmutable())],
        ]);

        $boothRentalService = $this->createMock(BoothRentalService::class);
        $assignmentRepo = $this->createMock(ConsignorBoothAssignmentRepository::class);
        $rentRepo = $this->createMock(RentDeductionRepository::class);
        $pdo = $this->createMock(\PDO::class);

        $controller = new ConsignorController($repo, $consignorService, $boothRentalService, $assignmentRepo, $rentRepo, $pdo);

        ob_start();
        $controller->list('slug');
        $output = ob_get_clean();

        $this->assertJson($output);
        /** @var list<array<string,mixed>> $data */
        $data = json_decode($output, true);
        $this->assertCount(2, $data);
        $emails = array_column($data, 'email');
        $this->assertContains('calvin@example.com', $emails);
        $this->assertContains('sally5@example.com', $emails);
    }
}

