<?php

declare(strict_types=1);

namespace CurserPos\Infrastructure\Payment;

/**
 * Stripe integration placeholder. Implement with stripe/stripe-php when approved.
 * For MVP, card payments can be recorded with a reference; actual processing is external.
 */
final class StripePaymentProcessor implements PaymentProcessorInterface
{
    public function __construct(
        private readonly string $secretKey
    ) {
    }

    public function charge(int $amountCents, string $paymentMethodId, ?string $description = null): string
    {
        if ($this->secretKey === '' || $this->secretKey === 'test') {
            return 'ch_test_' . bin2hex(random_bytes(8));
        }
        throw new \RuntimeException('Stripe SDK not installed. Add stripe/stripe-php and implement charge.');
    }

    public function refund(string $chargeReference, ?int $amountCents = null): string
    {
        if (str_starts_with($chargeReference, 'ch_test_')) {
            return 're_test_' . bin2hex(random_bytes(8));
        }
        throw new \RuntimeException('Stripe SDK not installed. Add stripe/stripe-php and implement refund.');
    }
}
