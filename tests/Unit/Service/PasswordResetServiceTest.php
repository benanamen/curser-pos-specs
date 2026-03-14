<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\User\PasswordResetTokenRepository;
use CurserPos\Domain\User\User;
use CurserPos\Domain\User\UserRepositoryInterface;
use CurserPos\Service\PasswordResetService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class PasswordResetServiceTest extends TestCase
{
    public function testRequestResetReturnsNullWhenUserNotFound(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->with('nobody@test.com')->willReturn(null);
        $tokenRepo = $this->createMock(PasswordResetTokenRepository::class);
        $tokenRepo->expects($this->never())->method('createToken');

        $service = new PasswordResetService($userRepo, $tokenRepo);
        $result = $service->requestReset('nobody@test.com');
        $this->assertNull($result);
    }

    public function testRequestResetReturnsTokenWhenUserFound(): void
    {
        $user = new User('id-1', 'user@test.com', 'active', new \DateTimeImmutable(), new \DateTimeImmutable());
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByEmail')->with('user@test.com')->willReturn($user);
        $tokenRepo = $this->createMock(PasswordResetTokenRepository::class);
        $tokenRepo->method('createToken')->with('id-1')->willReturn('abc123token');

        $service = new PasswordResetService($userRepo, $tokenRepo);
        $result = $service->requestReset('user@test.com');
        $this->assertSame('abc123token', $result);
    }

    public function testResetPasswordThrowsWhenTokenInvalid(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tokenRepo = $this->createMock(PasswordResetTokenRepository::class);
        $tokenRepo->method('consumeToken')->with('bad-token')->willReturn(null);

        $service = new PasswordResetService($userRepo, $tokenRepo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or expired reset token');
        $service->resetPassword('bad-token', 'newpassword123');
    }

    public function testResetPasswordThrowsWhenPasswordTooShort(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tokenRepo = $this->createMock(PasswordResetTokenRepository::class);
        $tokenRepo->method('consumeToken')->willReturn(['user_id' => 'id-1']);

        $service = new PasswordResetService($userRepo, $tokenRepo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least');
        $service->resetPassword('valid-token', 'short');
    }

    public function testResetPasswordUpdatesPasswordWhenValid(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())->method('updatePassword')->with('id-1', $this->anything());
        $tokenRepo = $this->createMock(PasswordResetTokenRepository::class);
        $tokenRepo->method('consumeToken')->willReturn(['user_id' => 'id-1']);

        $service = new PasswordResetService($userRepo, $tokenRepo);
        $service->resetPassword('valid-token', 'newpassword123');
    }
}
