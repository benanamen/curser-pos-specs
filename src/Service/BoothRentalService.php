<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Booth\ConsignorBoothAssignmentRepository;
use CurserPos\Domain\Booth\RentDeductionRepository;

class BoothRentalService
{
    public function __construct(
        private readonly ConsignorBoothAssignmentRepository $assignmentRepository,
        private readonly RentDeductionRepository $rentDeductionRepository,
        private readonly \CurserPos\Domain\Booth\BoothRepository $boothRepository
    ) {
    }

    /**
     * Assign consignor to booth; ends any current assignment first. monthly_rent overrides booth default when provided.
     */
    public function assignToBooth(string $consignorId, string $boothId, ?float $monthlyRentOverride = null, ?\DateTimeImmutable $startedAt = null): string
    {
        $booth = $this->boothRepository->findById($boothId);
        if ($booth === null) {
            throw new \InvalidArgumentException('Booth not found');
        }
        $this->assignmentRepository->endAssignment($consignorId, $startedAt ?? new \DateTimeImmutable());
        $rent = $monthlyRentOverride ?? $booth->monthlyRent;
        $start = $startedAt ?? new \DateTimeImmutable();
        return $this->assignmentRepository->assign($consignorId, $boothId, $rent, $start);
    }

    public function endAssignment(string $consignorId, ?\DateTimeImmutable $endedAt = null): void
    {
        $end = $endedAt ?? new \DateTimeImmutable();
        $this->assignmentRepository->endAssignment($consignorId, $end);
    }

    /**
     * Compute rent due from last deduction (or assignment start) through end of the given date.
     * Pro-rates by day using billing cycle day (1-31); partial months are charged proportionally.
     * Returns [amount, period_start, period_end] or null if no assignment or no rent due.
     *
     * @return array{amount: float, period_start: \DateTimeImmutable, period_end: \DateTimeImmutable}|null
     */
    public function getRentDue(string $consignorId, ?\DateTimeImmutable $throughDate = null, int $rentCycleDay = 1): ?array
    {
        $assignment = $this->assignmentRepository->getActiveByConsignorId($consignorId);
        if ($assignment === null) {
            return null;
        }

        $rentCycleDay = max(1, min(31, $rentCycleDay));
        $through = $throughDate ?? new \DateTimeImmutable();
        $periodStart = $this->rentDeductionRepository->getLastDeductionDate($consignorId);
        if ($periodStart !== null) {
            $periodStart = $periodStart->modify('+1 day');
        } else {
            $periodStart = $assignment->startedAt;
        }

        if ($periodStart > $through) {
            return null;
        }

        $periodEnd = $through;
        $amount = $this->computeProratedRent($periodStart, $periodEnd, $assignment->monthlyRent, $rentCycleDay);
        if ($amount <= 0) {
            return null;
        }

        return [
            'amount' => round($amount, 2),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    /**
     * Record a rent deduction (e.g. when deducted from payout) and optionally link to payout.
     */
    public function recordDeduction(string $consignorId, float $amount, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?string $payoutId = null): string
    {
        return $this->rentDeductionRepository->record($consignorId, $amount, $periodStart, $periodEnd, $payoutId);
    }

    private function computeProratedRent(\DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, float $monthlyRent, int $rentCycleDay): float
    {
        if ($periodStart > $periodEnd) {
            return 0.0;
        }
        $total = 0.0;
        $current = $periodStart;
        while ($current <= $periodEnd) {
            $billingStart = $this->startOfBillingMonthContaining($current, $rentCycleDay);
            $billingEnd = $this->endOfBillingMonthContaining($current, $rentCycleDay);
            $segmentStart = $current > $billingStart ? $current : $billingStart;
            $segmentEnd = $periodEnd < $billingEnd ? $periodEnd : $billingEnd;
            if ($segmentStart <= $segmentEnd) {
                $daysInBilling = $this->daysBetween($billingStart, $billingEnd) + 1;
                $daysInSegment = $this->daysBetween($segmentStart, $segmentEnd) + 1;
                $total += ($daysInSegment / $daysInBilling) * $monthlyRent;
            }
            $current = $billingEnd->modify('+1 day');
        }
        return $total;
    }

    private function startOfBillingMonthContaining(\DateTimeImmutable $date, int $cycleDay): \DateTimeImmutable
    {
        $y = (int) $date->format('Y');
        $m = (int) $date->format('m');
        $d = (int) $date->format('d');
        $lastDay = (int) $date->format('t');
        $effectiveDay = min($cycleDay, $lastDay);
        if ($d >= $effectiveDay) {
            return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $effectiveDay));
        }
        $prev = $date->modify('first day of last month');
        $prevLast = (int) $prev->format('t');
        $prevEffective = min($cycleDay, $prevLast);
        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', (int) $prev->format('Y'), (int) $prev->format('m'), $prevEffective));
    }

    private function endOfBillingMonthContaining(\DateTimeImmutable $date, int $cycleDay): \DateTimeImmutable
    {
        $start = $this->startOfBillingMonthContaining($date, $cycleDay);
        $next = $start->modify('+1 month');
        $nextY = (int) $next->format('Y');
        $nextM = (int) $next->format('m');
        $nextLast = (int) $next->format('t');
        $nextEffective = min($cycleDay, $nextLast);
        $nextStart = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $nextY, $nextM, $nextEffective));
        return $nextStart->modify('-1 day');
    }

    private function daysBetween(\DateTimeImmutable $a, \DateTimeImmutable $b): int
    {
        $diff = $a->diff($b);
        return $diff->days;
    }
}
