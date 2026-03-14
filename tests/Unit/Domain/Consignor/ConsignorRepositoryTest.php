<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Consignor;

use CurserPos\Domain\Consignor\Consignor;
use CurserPos\Domain\Consignor\ConsignorBalance;
use CurserPos\Domain\Consignor\ConsignorRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class ConsignorRepositoryTest extends TestCase
{
    private function consignorRow(string $id = 'cons-1'): array
    {
        return [
            'id' => $id,
            'slug' => 'jane-doe',
            'custom_id' => 'C001',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => null,
            'address' => null,
            'default_commission_pct' => 50.0,
            'agreement_signed_at' => '2025-01-01',
            'status' => 'active',
            'notes' => null,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['bad-id']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $this->assertNull($repo->findById('bad-id'));
    }

    public function testFindByIdReturnsConsignorWhenFound(): void
    {
        $row = $this->consignorRow();
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $c = $repo->findById('cons-1');
        $this->assertInstanceOf(Consignor::class, $c);
        $this->assertSame('cons-1', $c->id);
        $this->assertSame('jane-doe', $c->slug);
    }

    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['nope']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $this->assertNull($repo->findBySlug('nope'));
    }

    public function testFindByPortalTokenExecutesWithStatus(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['token123', 'active']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $this->assertNull($repo->findByPortalToken('token123'));
    }

    public function testSetPortalTokenExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 'new-token' && $params[2] === 'cons-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $repo->setPortalToken('cons-1', 'new-token');
    }

    public function testGeneratePortalTokenCallsSetPortalTokenAndReturnsToken(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $token = $repo->generatePortalToken('cons-1');
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function testFindAllReturnsList(): void
    {
        $row = $this->consignorRow();
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['active']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([$row]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $list = $repo->findAll('active');
        $this->assertCount(1, $list);
        $this->assertInstanceOf(Consignor::class, $list[0]);
    }

    public function testSlugExistsWithExcludeId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['jane', 'cons-1']));
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $this->assertFalse($repo->slugExists('jane', 'cons-1'));
    }

    public function testGetBalanceReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $this->assertNull($repo->getBalance('cons-1'));
    }

    public function testGetBalanceReturnsConsignorBalanceWhenFound(): void
    {
        $row = ['consignor_id' => 'cons-1', 'balance' => 100.5, 'pending_sales' => 25.0, 'paid_out' => 75.5, 'updated_at' => '2025-01-01 00:00:00'];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $bal = $repo->getBalance('cons-1');
        $this->assertInstanceOf(ConsignorBalance::class, $bal);
        $this->assertSame(100.5, $bal->balance);
    }

    public function testCreateExecutesInsertAndBalanceInsert(): void
    {
        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->exactly(2))->method('execute');
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($insertStmt);

        $repo = new ConsignorRepository($pdo);
        $id = $repo->create('new-slug', 'New Name', null, null, null, null, 50.0, null, null);
        $this->assertNotEmpty($id);
    }

    public function testUpdateExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 'slug2' && $params[1] === 'Name' && $params[10] === 'cons-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $repo->update('cons-1', 'slug2', 'Name', null, null, null, null, 50.0, null, null);
    }

    public function testUpdateStatusExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 'inactive' && $params[2] === 'cons-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $repo->updateStatus('cons-1', 'inactive');
    }

    public function testUpdateBalanceExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'cons-1' && (float) $params[2] === 100.0 && (float) $params[3] === 50.0 && (float) $params[4] === 50.0;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorRepository($pdo);
        $repo->updateBalance('cons-1', 100.0, 50.0, 50.0);
    }
}
