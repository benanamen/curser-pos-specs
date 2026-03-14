<?php

declare(strict_types=1);

namespace CurserPos\Domain\Sale;

use PDO;

final class ItemHoldRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @param list<array{item_id: string, quantity: int}> $cartLines
     */
    public function reserveItems(string $heldId, string $userId, array $cartLines): void
    {
        if ($cartLines === []) {
            return;
        }
        $byItem = [];
        foreach ($cartLines as $line) {
            $itemId = isset($line['item_id']) ? (string) $line['item_id'] : '';
            $qty = (int) ($line['quantity'] ?? 1);
            if ($itemId !== '' && $qty > 0) {
                $byItem[$itemId] = ($byItem[$itemId] ?? 0) + $qty;
            }
        }
        if ($byItem === []) {
            return;
        }
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO item_holds (item_id, held_id, user_id, quantity, created_at) VALUES (?, ?, ?, ?, ?)');
        foreach ($byItem as $itemId => $quantity) {
            $stmt->execute([$itemId, $heldId, $userId, $quantity, $now]);
        }
    }

    public function getQuantityHeld(string $itemId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM item_holds WHERE item_id = ?');
        $stmt->execute([$itemId]);
        $row = $stmt->fetchColumn();
        return $row !== false ? (int) $row : 0;
    }

    public function isReservedByHold(string $itemId, string $heldId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM item_holds WHERE item_id = ? AND held_id = ?');
        $stmt->execute([$itemId, $heldId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return list<string>
     */
    public function listItemIdsByHold(string $heldId): array
    {
        $stmt = $this->pdo->prepare('SELECT item_id FROM item_holds WHERE held_id = ? ORDER BY created_at');
        $stmt->execute([$heldId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (isset($row['item_id']) && $row['item_id'] !== '') {
                $out[] = (string) $row['item_id'];
            }
        }
        return $out;
    }

    public function deleteByHold(string $heldId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM item_holds WHERE held_id = ?');
        $stmt->execute([$heldId]);
    }
}

