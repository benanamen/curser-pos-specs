<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Tenant\TenantUserRepository;
use CurserPos\Domain\User\InviteTokenRepository;
use CurserPos\Domain\User\User;
use CurserPos\Domain\User\UserRepositoryInterface;
use CurserPos\Service\UserInviteService;
use PHPUnit\Framework\TestCase;

final class UserInviteServiceTest extends TestCase
{
    public function testInviteReturnsToken(): void
    {
        $tokenRepo = $this->createMock(InviteTokenRepository::class);
        $tokenRepo->method('create')->with('tenant-1', 'user@test.com', 'role-1')->willReturn('token123');
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);

        $service = new UserInviteService($tokenRepo, $userRepo, $tenantUserRepo);
        $token = $service->invite('tenant-1', 'user@test.com', 'role-1');
        $this->assertSame('token123', $token);
    }

    public function testAcceptInviteThrowsWhenTokenInvalid(): void
    {
        $tokenRepo = $this->createMock(InviteTokenRepository::class);
        $tokenRepo->method('consumeToken')->willReturn(null);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);

        $service = new UserInviteService($tokenRepo, $userRepo, $tenantUserRepo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or expired invite token');
        $service->acceptInvite('bad-token', 'password123');
    }

    public function testAcceptInviteThrowsWhenPasswordTooShort(): void
    {
        $tokenRepo = $this->createMock(InviteTokenRepository::class);
        $tokenRepo->method('consumeToken')->willReturn(['tenant_id' => 't1', 'email' => 'u@t.com', 'role_id' => 'r1']);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);

        $service = new UserInviteService($tokenRepo, $userRepo, $tenantUserRepo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least');
        $service->acceptInvite('valid-token', 'short');
    }

    public function testAcceptInviteCreatesUserAndAddsToTenantWhenNew(): void
    {
        $tokenRepo = $this->createMock(InviteTokenRepository::class);
        $tokenRepo->method('consumeToken')->willReturn(['tenant_id' => 't1', 'email' => 'new@test.com', 'role_id' => 'r1']);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn(null);
        $userRepo->expects($this->once())->method('create')->with('new@test.com', $this->anything())->willReturn('new-user-id');
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $tenantUserRepo->expects($this->once())->method('addUserToTenant')->with('t1', 'new-user-id', 'r1');

        $service = new UserInviteService($tokenRepo, $userRepo, $tenantUserRepo);
        $result = $service->acceptInvite('valid-token', 'password123');
        $this->assertSame('new-user-id', $result['user_id']);
        $this->assertSame('new@test.com', $result['email']);
        $this->assertSame('t1', $result['tenant_id']);
    }

    public function testAcceptInviteAddsExistingUserToTenant(): void
    {
        $user = new User('existing-id', 'existing@test.com', 'active', new \DateTimeImmutable(), new \DateTimeImmutable());
        $tokenRepo = $this->createMock(InviteTokenRepository::class);
        $tokenRepo->method('consumeToken')->willReturn(['tenant_id' => 't1', 'email' => 'existing@test.com', 'role_id' => 'r1']);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn($user);
        $userRepo->expects($this->never())->method('create');
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $tenantUserRepo->expects($this->once())->method('addUserToTenant')->with('t1', 'existing-id', 'r1');

        $service = new UserInviteService($tokenRepo, $userRepo, $tenantUserRepo);
        $result = $service->acceptInvite('valid-token', 'password123');
        $this->assertSame('existing-id', $result['user_id']);
    }
}
