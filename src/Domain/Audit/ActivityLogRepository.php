<?php

declare(strict_types=1);

namespace CurserPos\Domain\Audit;

use PDO;

class ActivityLogRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function log(
        ?string $tenantId,
        ?string $userId,
        ?string $supportUserId,
        string $action,
        ?string $entityType,
        ?string $entityId,
        array $payload,
        ?string $ip
    ): void {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $payloadJson = json_encode($payload);

        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_log (id, tenant_id, user_id, support_user_id, action, entity_type, entity_id, payload, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?)'
        );
        $stmt->execute([
            $id,
            $tenantId,
            $userId,
            $supportUserId,
            $action,
            $entityType,
            $entityId,
            $payloadJson,
            $ip,
            $now,
        ]);
    }

    /**
     * @return list<array{id: string, tenant_id: ?string, user_id: ?string, support_user_id: ?string, action: string, entity_type: ?string, entity_id: ?string, payload: string, ip: ?string, created_at: string}>
     */
    public function listByTenant(
        string $tenantId,
        ?string $action = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $sql = 'SELECT id, tenant_id, user_id, support_user_id, action, entity_type, entity_id, payload, ip, created_at
                FROM activity_log WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($action !== null && $action !== '') {
            $sql .= ' AND action = ?';
            $params[] = $action;
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $sql .= ' AND created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $sql .= ' AND created_at <= ?';
            $params[] = $dateTo;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
