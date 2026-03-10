<?php

declare(strict_types=1);

namespace CurserPos\Domain\Category;

use PDO;

final class CategoryRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(string $id): ?Category
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, parent_id, name, sort_order, tax_exempt, created_at, updated_at FROM categories WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    /**
     * @return list<Category>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, parent_id, name, sort_order, tax_exempt, created_at, updated_at FROM categories ORDER BY sort_order, name'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    public function create(string $name, ?string $parentId = null, int $sortOrder = 0, bool $taxExempt = false): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (id, parent_id, name, sort_order, tax_exempt, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $parentId, $name, $sortOrder, $taxExempt ? 't' : 'f', $now, $now]);
        return $id;
    }

    public function update(string $id, string $name, ?string $parentId, int $sortOrder, bool $taxExempt): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'UPDATE categories SET name = ?, parent_id = ?, sort_order = ?, tax_exempt = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$name, $parentId, $sortOrder, $taxExempt ? 't' : 'f', $now, $id]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Category
    {
        return new Category(
            (string) $row['id'],
            isset($row['parent_id']) && $row['parent_id'] !== null ? (string) $row['parent_id'] : null,
            (string) $row['name'],
            (int) $row['sort_order'],
            ($row['tax_exempt'] ?? false) === true || ($row['tax_exempt'] ?? 'f') === 't',
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
