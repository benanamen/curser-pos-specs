<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Tenant\TenantProvisioningService;
use CurserPos\Domain\User\User;
use CurserPos\Domain\User\UserRepositoryInterface;
use CurserPos\Service\AuthService;
use PHPUnit\Framework\TestCase;
use PerfectApp\Session\Session;

final class AuthServiceTest extends TestCase
{
    public function testLoginThrowsWhenUserNotFound(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn(null);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $session = $this->createMock(Session::class);

        $service = new AuthService($userRepo, $provisioning, $session);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid credentials');
        $service->login('nobody@test.com', 'password');
    }

    public function testLoginThrowsWhenPasswordWrong(): void
    {
        $user = new User('id-1', 'user@test.com', 'active', new \DateTimeImmutable(), new \DateTimeImmutable());
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn($user);
        $userRepo->method('getPasswordHash')->willReturn(password_hash('correct', PASSWORD_DEFAULT));
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $session = $this->createMock(Session::class);

        $service = new AuthService($userRepo, $provisioning, $session);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid credentials');
        $service->login('user@test.com', 'wrong');
    }

    public function testLoginThrowsWhenNoTenantAccess(): void
    {
        $user = new User('id-1', 'user@test.com', 'active', new \DateTimeImmutable(), new \DateTimeImmutable());
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn($user);
        $userRepo->method('getPasswordHash')->willReturn(password_hash('correct', PASSWORD_DEFAULT));
        $userRepo->method('getDefaultTenantForUser')->willReturn(null);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $session = $this->createMock(Session::class);

        $service = new AuthService($userRepo, $provisioning, $session);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User has no tenant access');
        $service->login('user@test.com', 'correct');
    }

    public function testLoginSetsSessionAndReturnsWhenValid(): void
    {
        $user = new User('id-1', 'user@test.com', 'active', new \DateTimeImmutable(), new \DateTimeImmutable());
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn($user);
        $userRepo->method('getPasswordHash')->willReturn(password_hash('correct', PASSWORD_DEFAULT));
        $userRepo->method('getDefaultTenantForUser')->willReturn([
            'tenant_id' => 't1',
            'tenant_slug' => 'mystore',
            'tenant_name' => 'My Store',
        ]);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(2))->method('set');

        $service = new AuthService($userRepo, $provisioning, $session);
        $result = $service->login('user@test.com', 'correct');
        $this->assertSame('id-1', $result['user']['id']);
        $this->assertSame('mystore', $result['tenant']['slug']);
    }

    public function testLogoutDeletesSession(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(2))->method('delete');

        $service = new AuthService($userRepo, $provisioning, $session);
        $service->logout();
    }

    public function testGetCurrentUserReturnsNullWhenNoSession(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $session = $this->createMock(Session::class);
        $session->method('get')->willReturn(null);

        $service = new AuthService($userRepo, $provisioning, $session);
        $this->assertNull($service->getCurrentUser());
    }

    public function testGetCurrentUserReturnsUserWhenSessionSet(): void
    {
        $user = new User('id-1', 'user@test.com', 'active', new \DateTimeImmutable(), new \DateTimeImmutable());
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findById')->with('id-1')->willReturn($user);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $session = $this->createMock(Session::class);
        $session->method('get')->willReturnCallback(fn (string $k) => $k === 'user_id' ? 'id-1' : null);

        $service = new AuthService($userRepo, $provisioning, $session);
        $result = $service->getCurrentUser();
        $this->assertSame($user, $result);
    }

    public function testGetCurrentTenantIdReturnsNullWhenNoSession(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $session = $this->createMock(Session::class);
        $session->method('get')->willReturn(null);

        $service = new AuthService($userRepo, $provisioning, $session);
        $this->assertNull($service->getCurrentTenantId());
    }

    public function testGetCurrentTenantIdReturnsTenantIdWhenSessionSet(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $session = $this->createMock(Session::class);
        $session->method('get')->willReturnCallback(fn (string $k) => $k === 'tenant_id' ? 't1' : null);

        $service = new AuthService($userRepo, $provisioning, $session);
        $this->assertSame('t1', $service->getCurrentTenantId());
    }

    public function testSignupThrowsWhenEmailExists(): void
    {
        $user = new User('id-1', 'existing@test.com', 'active', new \DateTimeImmutable(), new \DateTimeImmutable());
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn($user);
        $provisioning = $this->createMock(TenantProvisioningService::class);

        $service = new AuthService($userRepo, $provisioning, $this->createMock(Session::class));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already registered');
        $service->signup('existing@test.com', 'password123', 'Store', 'mystore');
    }

    public function testSignupThrowsWhenSlugTaken(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn(null);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $provisioning->method('slugExists')->with('taken')->willReturn(true);

        $service = new AuthService($userRepo, $provisioning, $this->createMock(Session::class));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Store slug already taken');
        $service->signup('new@test.com', 'password123', 'Store', 'taken');
    }

    public function testSignupThrowsWhenPasswordTooShort(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn(null);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $provisioning->method('slugExists')->willReturn(false);

        $service = new AuthService($userRepo, $provisioning, $this->createMock(Session::class));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');
        $service->signup('new@test.com', 'short', 'Store', 'mystore');
    }

    public function testSignupThrowsWhenSlugInvalid(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn(null);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $provisioning->method('slugExists')->willReturn(false);

        $service = new AuthService($userRepo, $provisioning, $this->createMock(Session::class));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Store slug can only contain letters');
        $service->signup('new@test.com', 'password123', 'Store', 'invalid slug!');
    }

    public function testSignupSuccess(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->willReturn(null);
        $provisioning = $this->createMock(TenantProvisioningService::class);
        $provisioning->method('slugExists')->willReturn(false);
        $provisioning->method('provision')->with('My Store', 'mystore', 'new@test.com', 'password123')
            ->willReturn(['tenant_id' => 't1', 'user_id' => 'u1']);
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(2))->method('set');

        $service = new AuthService($userRepo, $provisioning, $session);
        $result = $service->signup('new@test.com', 'password123', 'My Store', 'mystore');
        $this->assertSame('u1', $result['user']['id']);
        $this->assertSame('mystore', $result['tenant']['slug']);
    }
}
