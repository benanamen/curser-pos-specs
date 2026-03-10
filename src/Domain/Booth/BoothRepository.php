<?php

declare(strict_types=1);

namespace CurserPos\Domain\Booth;

use PDO;

final class BoothRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(string $id): ?Booth
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, location_id, monthly_rent, status, created_at, updated_at FROM booths WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    /**
     * @return list<Booth>
     */
    public function findAll(string $status = 'active'): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, location_id, monthly_rent, status, created_at, updated_at FROM booths WHERE status = ? ORDER BY name'
        );
        $stmt->execute([$status]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    public function create(string $name, ?string $locationId, float $monthlyRent): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO booths (id, name, location_id, monthly_rent, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $name, $locationId, $monthlyRent, 'active', $now, $now]);
        return $id;
    }

    public function update(string $id, string $name, ?string $locationId, float $monthlyRent, string $status): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'UPDATE booths SET name = ?, location_id = ?, monthly_rent = ?, status = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$name, $locationId, $monthlyRent, $status, $now, $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Booth
    {
        return new Booth(
            (string) $row['id'],
            (string) $row['name'],
            isset($row['location_id']) && $row['location_id'] !== '' ? (string) $row['location_id'] : null,
            (float) $row['monthly_rent'],
            (string) $row['status'],
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at'])
        );
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
