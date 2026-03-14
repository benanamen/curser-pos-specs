<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Consignor\Consignor;
use CurserPos\Domain\Consignor\ConsignorBalance;
use CurserPos\Domain\Consignor\ConsignorRepository;

class ConsignorService
{
    public function __construct(
        private readonly ConsignorRepository $consignorRepository
    ) {
    }

    public function createConsignor(
        string $slug,
        string $name,
        ?string $customId = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $address = null,
        float $defaultCommissionPct = 50.0,
        ?\DateTimeImmutable $agreementSignedAt = null,
        ?string $notes = null
    ): Consignor {
        $slug = $this->normalizeSlug($slug);
        if ($customId !== null && $customId !== '') {
            $customId = $this->normalizeCustomId($customId);
        } else {
            $customId = null;
        }
        if ($this->consignorRepository->slugExists($slug)) {
            throw new \InvalidArgumentException("Consignor slug '{$slug}' already exists");
        }
        $id = $this->consignorRepository->create($slug, $name, $customId, $email, $phone, $address, $defaultCommissionPct, $agreementSignedAt, $notes);
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            throw new \RuntimeException('Failed to create consignor');
        }
        return $consignor;
    }

    public function updateConsignor(
        string $id,
        string $slug,
        string $name,
        ?string $customId,
        ?string $email,
        ?string $phone,
        ?string $address,
        float $defaultCommissionPct,
        ?\DateTimeImmutable $agreementSignedAt,
        ?string $notes
    ): Consignor {
        $slug = $this->normalizeSlug($slug);
        if ($customId !== null && $customId !== '') {
            $customId = $this->normalizeCustomId($customId);
        } else {
            $customId = null;
        }
        if ($this->consignorRepository->slugExists($slug, $id)) {
            throw new \InvalidArgumentException("Consignor slug '{$slug}' already exists");
        }
        $this->consignorRepository->update($id, $slug, $name, $customId, $email, $phone, $address, $defaultCommissionPct, $agreementSignedAt, $notes);
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            throw new \RuntimeException('Consignor not found');
        }
        return $consignor;
    }

    public function getBalance(string $consignorId): ConsignorBalance
    {
        $balance = $this->consignorRepository->getBalance($consignorId);
        if ($balance === null) {
            return new ConsignorBalance($consignorId, 0.0, 0.0, 0.0, new \DateTimeImmutable());
        }
        return $balance;
    }

    public function recordManualAdjustment(string $consignorId, float $amount, string $reason): void
    {
        $balance = $this->getBalance($consignorId);
        $newBalance = $balance->balance + $amount;
        $this->consignorRepository->updateBalance($consignorId, $newBalance, $balance->pendingSales, $balance->paidOut);
    }

    public function deductForPayout(string $consignorId, float $amount): void
    {
        $balance = $this->getBalance($consignorId);
        $newBalance = $balance->balance - $amount;
        $newPaidOut = $balance->paidOut + $amount;
        $this->consignorRepository->updateBalance($consignorId, $newBalance, $balance->pendingSales, $newPaidOut);
    }

    /**
     * Deduct payout amount and rent from balance; only payout amount is added to paid_out (rent is kept by store).
     */
    public function deductForPayoutAndRent(string $consignorId, float $payoutAmount, float $rentAmount): void
    {
        $balance = $this->getBalance($consignorId);
        $totalDeduct = $payoutAmount + $rentAmount;
        $newBalance = $balance->balance - $totalDeduct;
        $newPaidOut = $balance->paidOut + $payoutAmount;
        $this->consignorRepository->updateBalance($consignorId, $newBalance, $balance->pendingSales, $newPaidOut);
    }

    /**
     * Deduct rent only from balance without increasing paid_out (rent is kept entirely by the store).
     */
    public function deductRentOnly(string $consignorId, float $rentAmount): void
    {
        if ($rentAmount <= 0) {
            return;
        }
        $balance = $this->getBalance($consignorId);
        $newBalance = $balance->balance - $rentAmount;
        $this->consignorRepository->updateBalance($consignorId, $newBalance, $balance->pendingSales, $balance->paidOut);
    }

    /**
     * Bulk import consignors from CSV. Header row required. Columns: slug, name, email, phone, address, default_commission_pct
     *
     * @return array{created: list<array{id: string, slug: string}>, errors: list<array{row: int, message: string}>}
     */
    public function bulkImportFromCsv(string $csv): array
    {
        $lines = array_filter(explode("\n", $csv), fn (string $l) => trim($l) !== '');
        if ($lines === []) {
            return ['created' => [], 'errors' => [['row' => 0, 'message' => 'Empty or invalid CSV']]];
        }
        $header = str_getcsv(array_shift($lines), ',', '"', '\\');
        $header = array_map('trim', $header);
        $created = [];
        $errors = [];
        $rowNum = 2;
        foreach ($lines as $line) {
            $row = str_getcsv($line, ',', '"', '\\');
            $assoc = array_combine($header, array_pad($row, count($header), null));
            if ($assoc === false) {
                $errors[] = ['row' => $rowNum, 'message' => 'Column count mismatch'];
                $rowNum++;
                continue;
            }
            $name = trim((string) ($assoc['name'] ?? ''));
            if ($name === '') {
                $errors[] = ['row' => $rowNum, 'message' => 'Name is required'];
                $rowNum++;
                continue;
            }
            $slug = isset($assoc['slug']) && trim((string) $assoc['slug']) !== '' ? trim((string) $assoc['slug']) : $this->normalizeSlug($name);
            $customId = isset($assoc['custom_id']) && trim((string) $assoc['custom_id']) !== '' ? trim((string) $assoc['custom_id']) : null;
            $email = isset($assoc['email']) && trim((string) $assoc['email']) !== '' ? trim((string) $assoc['email']) : null;
            $phone = isset($assoc['phone']) && trim((string) $assoc['phone']) !== '' ? trim((string) $assoc['phone']) : null;
            $address = isset($assoc['address']) && trim((string) $assoc['address']) !== '' ? trim((string) $assoc['address']) : null;
            $commission = (float) ($assoc['default_commission_pct'] ?? 50.0);
            try {
                $consignor = $this->createConsignor($slug, $name, $customId, $email, $phone, $address, $commission);
                $created[] = ['id' => $consignor->id, 'slug' => $consignor->slug];
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
            $rowNum++;
        }
        return ['created' => $created, 'errors' => $errors];
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', $slug));
        return trim($slug, '-') ?: 'consignor-' . substr(uniqid(), -6);
    }

    private function normalizeCustomId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('Custom consignor ID cannot be empty when provided');
        }
        if (!preg_match('/^[A-Za-z0-9-]+$/', $id)) {
            throw new \InvalidArgumentException('Custom consignor ID may only contain letters, numbers, and dashes');
        }
        return $id;
    }
}
