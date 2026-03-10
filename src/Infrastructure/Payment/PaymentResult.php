<?php

declare(strict_types=1);

namespace CurserPos\Infrastructure\Payment;

final class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $reference = null,
        public readonly ?string $errorMessage = null
    ) {
    }

    public static function success(string $reference): self
    {
        return new self(true, $reference, null);
    }

    public static function failure(string $message): self
    {
        return new self(false, null, $message);
    }
}
