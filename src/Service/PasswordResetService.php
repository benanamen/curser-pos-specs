<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\User\PasswordResetTokenRepository;
use CurserPos\Domain\User\UserRepositoryInterface;

final class PasswordResetService
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetTokenRepository $tokenRepository
    ) {
    }

    /**
     * Returns token if user exists (caller may send email with link containing token).
     * Returns null if email not found to avoid leaking existence.
     */
    public function requestReset(string $email): ?string
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            return null;
        }
        return $this->tokenRepository->createToken($user->id);
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        $this->validatePassword($newPassword);
        $result = $this->tokenRepository->consumeToken($token);
        if ($result === null) {
            throw new \InvalidArgumentException('Invalid or expired reset token');
        }
        $this->userRepository->updatePassword(
            $result['user_id'],
            password_hash($newPassword, PASSWORD_DEFAULT)
        );
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException('Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters');
        }
    }
}
