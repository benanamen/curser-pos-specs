<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Audit\ActivityLogRepository;

final class AuditService
{
    public function __construct(
        private readonly ActivityLogRepository $activityLogRepository
    ) {
    }

    public function log(
        ?string $tenantId,
        ?string $userId,
        ?string $supportUserId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        array $payload = [],
        ?string $ip = null
    ): void {
        $this->activityLogRepository->log(
            $tenantId,
            $userId,
            $supportUserId,
            $action,
            $entityType,
            $entityId,
            $payload,
            $ip
        );
    }
}
