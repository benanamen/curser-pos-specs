<?php

declare(strict_types=1);

namespace CurserPos\Infrastructure\Payment;

interface PaymentProcessorInterface
{
    /**
     * Charge a card (tokenized). Returns transaction reference or throws.
     *
     * @param int $amountCents Amount in cents
     * @param string $paymentMethodId Token or payment method ID from client
     * @param string|null $description Optional description for the charge
     * @return string Transaction/reference ID
     */
    public function charge(int $amountCents, string $paymentMethodId, ?string $description = null): string;

    /**
     * Refund a previous charge.
     *
     * @param string $chargeReference Original charge reference
     * @param int|null $amountCents Refund amount in cents, or null for full refund
     * @return string Refund reference ID
     */
    public function refund(string $chargeReference, ?int $amountCents = null): string;
}
