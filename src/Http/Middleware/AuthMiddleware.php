<?php

declare(strict_types=1);

namespace CurserPos\Http\Middleware;

use CurserPos\Domain\Tenant\TenantUserRepository;
use CurserPos\Domain\User\UserRepositoryInterface;
use CurserPos\Http\RequestContext;
use CurserPos\Service\AuditService;
use PerfectApp\Session\Session;

final class AuthMiddleware
{
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_PLATFORM_USER_ID = 'platform_user_id';
    private const PUBLIC_PATHS = ['health'];

    public function __construct(
        private readonly Session $session,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantUserRepository $tenantUserRepository,
        private readonly AuditService $auditService
    ) {
    }

    private const PORTAL_PATH_SEGMENT = '/api/v1/portal/';

    public function __invoke(RequestContext $context, callable $next): void
    {
        if (!$this->requiresAuth($context->requestUri)) {
            $next();
            return;
        }

        if (str_contains($context->requestUri, self::PORTAL_PATH_SEGMENT) && $context->consignor !== null) {
            $next();
            return;
        }

        $platformUserId = $this->session->get(self::SESSION_PLATFORM_USER_ID);
        if ($platformUserId !== null && $context->tenant !== null) {
            $context->isSupportAccess = true;
            $context->supportUserId = (string) $platformUserId;
            $this->auditService->log(
                $context->tenant->id,
                null,
                (string) $platformUserId,
                'support.access',
                'tenant',
                $context->tenant->id,
                ['request_uri' => $context->requestUri, 'method' => $context->requestMethod],
                $context->clientIp
            );
            $next();
            return;
        }

        $userId = $this->session->get(self::SESSION_USER_ID);
        if ($userId === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            $this->session->delete(self::SESSION_USER_ID);
            $this->session->delete('tenant_id');
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        $context->user = $user;
        if ($context->tenant !== null) {
            $tenantUser = $this->tenantUserRepository->getByUserAndTenant($user->id, $context->tenant->id);
            $context->tenantUser = $tenantUser;
            if ($tenantUser === null) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'No access to this tenant']);
                return;
            }
        }
        $next();
    }

    private function requiresAuth(string $path): bool
    {
        if (!preg_match('@^/t/[a-zA-Z0-9_-]+/api/v1/(.+)$@', $path, $matches)) {
            return false;
        }

        $apiPath = $matches[1];
        return !in_array($apiPath, self::PUBLIC_PATHS, true);
    }
}
