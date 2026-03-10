<?php

declare(strict_types=1);

namespace CurserPos\Domain\Location;

use PDO;

final class LocationRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(string $id): ?Location
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, address, tax_rates, created_at, updated_at FROM locations WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    /**
     * @return list<Location>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, address, tax_rates, created_at, updated_at FROM locations ORDER BY name'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    public function create(string $name, string $address = '', array $taxRates = []): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $taxJson = json_encode($taxRates);

        $stmt = $this->pdo->prepare(
            'INSERT INTO locations (id, name, address, tax_rates, created_at, updated_at) VALUES (?, ?, ?, ?::jsonb, ?, ?)'
        );
        $stmt->execute([$id, $name, $address, $taxJson, $now, $now]);
        return $id;
    }

    public function update(string $id, string $name, string $address, array $taxRates): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $taxJson = json_encode($taxRates);

        $stmt = $this->pdo->prepare(
            'UPDATE locations SET name = ?, address = ?, tax_rates = ?::jsonb, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$name, $address, $taxJson, $now, $id]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM locations WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Location
    {
        $taxRates = is_string($row['tax_rates'] ?? '[]')
            ? json_decode($row['tax_rates'], true)
            : ($row['tax_rates'] ?? []);
        $taxRates = is_array($taxRates) ? $taxRates : [];

        return new Location(
            (string) $row['id'],
            (string) $row['name'],
            (string) ($row['address'] ?? ''),
            $taxRates,
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
