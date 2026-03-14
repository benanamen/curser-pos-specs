<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Platform\PlatformUserRepository;
use CurserPos\Service\PlatformAuthService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PerfectApp\Session\Session;

#[AllowMockObjectsWithoutExpectations]
final class PlatformAuthServiceTest extends TestCase
{
    public function testLoginThrowsWhenUserNotFound(): void
    {
        $repo = $this->createMock(PlatformUserRepository::class);
        $repo->method('findByEmail')->willReturn(null);
        $session = $this->createMock(Session::class);

        $service = new PlatformAuthService($repo, $session);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid credentials');
        $service->login('nobody@platform.com', 'password');
    }

    public function testLoginThrowsWhenPasswordWrong(): void
    {
        $repo = $this->createMock(PlatformUserRepository::class);
        $repo->method('findByEmail')->willReturn(['id' => 'p1', 'email' => 'admin@platform.com']);
        $repo->method('getPasswordHash')->willReturn(password_hash('correct', PASSWORD_DEFAULT));
        $session = $this->createMock(Session::class);

        $service = new PlatformAuthService($repo, $session);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid credentials');
        $service->login('admin@platform.com', 'wrong');
    }

    public function testLoginSetsSessionWhenValid(): void
    {
        $hash = password_hash('correct', PASSWORD_DEFAULT);
        $repo = $this->createMock(PlatformUserRepository::class);
        $repo->method('findByEmail')->willReturn(['id' => 'p1', 'email' => 'admin@platform.com']);
        $repo->method('getPasswordHash')->willReturn($hash);
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('set')->with('platform_user_id', 'p1');

        $service = new PlatformAuthService($repo, $session);
        $result = $service->login('admin@platform.com', 'correct');
        $this->assertSame('p1', $result['id']);
        $this->assertSame('admin@platform.com', $result['email']);
    }

    public function testLogoutDeletesSession(): void
    {
        $repo = $this->createMock(PlatformUserRepository::class);
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('delete')->with('platform_user_id');

        $service = new PlatformAuthService($repo, $session);
        $service->logout();
    }
}
