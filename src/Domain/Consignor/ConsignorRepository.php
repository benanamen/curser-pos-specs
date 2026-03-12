<?php

declare(strict_types=1);

namespace CurserPos\Domain\Consignor;

use PDO;

class ConsignorRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(string $id): ?Consignor
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, email, phone, address, default_commission_pct, agreement_signed_at, status, notes, created_at, updated_at FROM consignors WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?Consignor
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, email, phone, address, default_commission_pct, agreement_signed_at, status, notes, created_at, updated_at FROM consignors WHERE slug = ?'
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findByPortalToken(string $token): ?Consignor
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, email, phone, address, default_commission_pct, agreement_signed_at, status, notes, created_at, updated_at FROM consignors WHERE portal_token = ? AND status = ?'
        );
        $stmt->execute([$token, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    public function setPortalToken(string $consignorId, string $token): void
    {
        $stmt = $this->pdo->prepare('UPDATE consignors SET portal_token = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$token, (new \DateTimeImmutable())->format('Y-m-d H:i:s'), $consignorId]);
    }

    public function generatePortalToken(string $consignorId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->setPortalToken($consignorId, $token);
        return $token;
    }

    /**
     * @return list<Consignor>
     */
    public function findAll(string $status = 'active'): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, email, phone, address, default_commission_pct, agreement_signed_at, status, notes, created_at, updated_at FROM consignors WHERE status = ? ORDER BY name'
        );
        $stmt->execute([$status]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    public function slugExists(string $slug, ?string $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM consignors WHERE slug = ? AND id != ?');
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM consignors WHERE slug = ?');
            $stmt->execute([$slug]);
        }
        return $stmt->fetch() !== false;
    }

    public function getBalance(string $consignorId): ?ConsignorBalance
    {
        $stmt = $this->pdo->prepare(
            'SELECT consignor_id, balance, pending_sales, paid_out, updated_at FROM consignor_balances WHERE consignor_id = ?'
        );
        $stmt->execute([$consignorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? new ConsignorBalance(
            (string) $row['consignor_id'],
            (float) $row['balance'],
            (float) $row['pending_sales'],
            (float) $row['paid_out'],
            new \DateTimeImmutable((string) $row['updated_at'])
        ) : null;
    }

    public function create(
        string $slug,
        string $name,
        ?string $email = null,
        ?string $phone = null,
        ?string $address = null,
        float $defaultCommissionPct = 50.0,
        ?\DateTimeImmutable $agreementSignedAt = null,
        ?string $notes = null
    ): string {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $agreementDate = $agreementSignedAt?->format('Y-m-d');

        $stmt = $this->pdo->prepare(
            'INSERT INTO consignors (id, slug, name, email, phone, address, default_commission_pct, agreement_signed_at, status, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?::date, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $slug, $name, $email, $phone, $address, $defaultCommissionPct, $agreementDate, 'active', $notes, $now, $now]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO consignor_balances (id, consignor_id, balance, pending_sales, paid_out, updated_at) VALUES (?, ?, 0, 0, 0, ?)'
        );
        $stmt->execute([$this->generateUuid(), $id, $now]);

        return $id;
    }

    public function update(
        string $id,
        string $slug,
        string $name,
        ?string $email,
        ?string $phone,
        ?string $address,
        float $defaultCommissionPct,
        ?\DateTimeImmutable $agreementSignedAt,
        ?string $notes
    ): void {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $agreementDate = $agreementSignedAt?->format('Y-m-d');

        $stmt = $this->pdo->prepare(
            'UPDATE consignors SET slug = ?, name = ?, email = ?, phone = ?, address = ?, default_commission_pct = ?, agreement_signed_at = ?::date, notes = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$slug, $name, $email, $phone, $address, $defaultCommissionPct, $agreementDate, $notes, $now, $id]);
    }

    public function updateStatus(string $id, string $status): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE consignors SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, $now, $id]);
    }

    public function updateBalance(string $consignorId, float $balance, float $pendingSales, float $paidOut): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO consignor_balances (id, consignor_id, balance, pending_sales, paid_out, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT (consignor_id) DO UPDATE SET balance = EXCLUDED.balance, pending_sales = EXCLUDED.pending_sales, paid_out = EXCLUDED.paid_out, updated_at = EXCLUDED.updated_at'
        );
        $stmt->execute([$this->generateUuid(), $consignorId, $balance, $pendingSales, $paidOut, $now]);
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Consignor
    {
        $agreement = isset($row['agreement_signed_at']) && $row['agreement_signed_at'] !== null
            ? new \DateTimeImmutable((string) $row['agreement_signed_at'])
            : null;

        return new Consignor(
            (string) $row['id'],
            (string) $row['slug'],
            (string) $row['name'],
            isset($row['email']) && $row['email'] !== '' ? (string) $row['email'] : null,
            isset($row['phone']) && $row['phone'] !== '' ? (string) $row['phone'] : null,
            isset($row['address']) && $row['address'] !== '' ? (string) $row['address'] : null,
            (float) $row['default_commission_pct'],
            $agreement,
            (string) $row['status'],
            isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at'])
        );
    }
}
