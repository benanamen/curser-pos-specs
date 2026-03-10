<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Audit\ActivityLogRepository;
use PerfectApp\Routing\Route;

final class AuditController
{
    public function __construct(
        private readonly ActivityLogRepository $activityLogRepository
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/audit', ['GET'])]
    public function list(string $slug): void
    {
        $context = \CurserPos\Http\RequestContextHolder::get();
        $tenant = $context?->tenant;
        if ($tenant === null) {
            $this->jsonResponse(404, ['error' => 'Tenant not found']);
            return;
        }
        $params = $context->queryParams ?? [];
        $action = isset($params['action']) ? trim($params['action']) : null;
        $action = $action === '' ? null : $action;
        $dateFrom = isset($params['date_from']) ? trim($params['date_from']) : null;
        $dateFrom = $dateFrom === '' ? null : $dateFrom;
        $dateTo = isset($params['date_to']) ? trim($params['date_to']) : null;
        $dateTo = $dateTo === '' ? null : $dateTo;
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int) $params['limit'] : 100;
        $limit = min(max($limit, 1), 500);
        $offset = isset($params['offset']) && is_numeric($params['offset']) ? (int) $params['offset'] : 0;
        $offset = max($offset, 0);

        $entries = $this->activityLogRepository->listByTenant(
            $tenant->id,
            $action ?: null,
            $dateFrom ?: null,
            $dateTo ?: null,
            $limit,
            $offset
        );
        $this->jsonResponse(200, ['entries' => $entries]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonResponse(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
