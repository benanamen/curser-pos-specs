<?php

declare(strict_types=1);

namespace CurserPos\Http\Middleware;

use CurserPos\Domain\Tenant\TenantRepository;
use CurserPos\Http\RequestContext;

final class TenantResolutionMiddleware
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly string $tenantPathPrefix = '/t/'
    ) {
    }

    public function __invoke(RequestContext $context, callable $next): void
    {
        $path = $context->requestUri;

        if (!str_starts_with($path, $this->tenantPathPrefix)) {
            $next();
            return;
        }

        $pattern = '@^' . preg_quote($this->tenantPathPrefix, '@') . '([a-zA-Z0-9_-]+)(/|$)@';
        if (!preg_match($pattern, $path, $matches)) {
            $next();
            return;
        }

        $slug = $matches[1];
        $context->tenantSlug = $slug;

        $tenant = $this->tenantRepository->findBySlug($slug);
        if ($tenant === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Tenant not found', 'slug' => $slug]);
            return;
        }

        $context->tenant = $tenant;
        $next();
    }
}
