<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Tenant;

use CurserPos\Domain\Tenant\TenantProvisioningService;
use CurserPos\Domain\Tenant\TenantRepositoryInterface;
use CurserPos\Domain\User\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;

#[AllowMockObjectsWithoutExpectations]
class TenantProvisioningServiceTest extends TestCase
{
    private PDO $pdo;
    private TenantRepositoryInterface $tenantRepository;
    private UserRepositoryInterface $userRepository;
    private TenantProvisioningService $service;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->tenantRepository = $this->createMock(TenantRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->service = new TenantProvisioningService(
            $this->pdo,
            $this->tenantRepository,
            $this->userRepository,
            __DIR__ . '/../../../migrations/tenant'
        );
    }

    public function testSlugExistsReturnsTrueWhenSlugTaken(): void
    {
        $this->tenantRepository->method('slugExists')->with('mystore')->willReturn(true);
        $this->assertTrue($this->service->slugExists('mystore'));
    }

    public function testSlugExistsReturnsFalseWhenSlugAvailable(): void
    {
        $this->tenantRepository->method('slugExists')->with('mystore')->willReturn(false);
        $this->assertFalse($this->service->slugExists('mystore'));
    }

    public function testProvisionThrowsWhenSlugTaken(): void
    {
        $this->tenantRepository->method('slugExists')->with('taken')->willReturn(true);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Store slug already taken');
        $this->service->provision('Store', 'taken', 'user@test.com', 'password123');
    }
}
