<?php

declare(strict_types=1);

namespace CurserPos\Http\Middleware;

use CurserPos\Http\RequestContext;

final class PermissionMiddleware
{
    /**
     * Map API path segment (after /api/v1/) to required permission key.
     * First matching prefix wins. '*' means any subpath.
     */
    private const ROUTE_PERMISSIONS = [
        'pos' => 'pos',
        'registers' => 'pos',
        'store-credits' => 'pos',
        'gift-cards' => 'pos',
        'store/config' => 'settings',
        'payouts' => 'payouts',
        'items' => 'inventory',
        'categories' => 'inventory',
        'locations' => 'inventory',
        'consignors' => 'inventory',
        'booths' => 'inventory',
        'reports' => 'reports',
        'dashboard' => 'reports',
        'audit' => 'audit',
        'users' => 'users',
        'me' => null,
        'health' => null,
    ];

    public function __invoke(RequestContext $context, callable $next): void
    {
        if ($context->tenant === null) {
            $next();
            return;
        }

        if ($context->isSupportAccess) {
            $next();
            return;
        }

        if ($context->tenantUser === null) {
            $next();
            return;
        }

        $required = $this->requiredPermission($context->requestUri);
        if ($required === null) {
            $next();
            return;
        }

        $permissions = $context->tenantUser['permissions'] ?? [];
        if (!empty($permissions['all'])) {
            $next();
            return;
        }

        if (!empty($permissions[$required])) {
            $next();
            return;
        }

        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Insufficient permissions']);
    }

    private function requiredPermission(string $requestUri): ?string
    {
        if (!preg_match('@^/t/[a-zA-Z0-9_-]+/api/v1/([a-zA-Z0-9/_-]+)@', $requestUri, $matches)) {
            return null;
        }
        $apiPath = $matches[1];
        $segments = explode('/', $apiPath);
        $first = $segments[0] ?? '';

        foreach (self::ROUTE_PERMISSIONS as $prefix => $permission) {
            if ($permission === null) {
                continue;
            }
            if ($first === $prefix) {
                return $permission;
            }
            if (str_starts_with($apiPath, $prefix . '/') || $apiPath === $prefix) {
                return $permission;
            }
        }
        return 'pos';
    }
}
