<?php

declare(strict_types=1);

namespace CurserPos\Http\Middleware;

use CurserPos\Http\RequestContext;
use PerfectApp\Session\Session;

final class PlatformAuthMiddleware
{
    private const SESSION_PLATFORM_USER_ID = 'platform_user_id';
    private const PLATFORM_PREFIX = '/api/v1/platform/';
    private const PUBLIC_PATHS = ['api/v1/platform/login', 'api/v1/platform/logout'];

    public function __construct(
        private readonly Session $session
    ) {
    }

    public function __invoke(RequestContext $context, callable $next): void
    {
        $path = $context->requestUri;

        if (!str_starts_with($path, self::PLATFORM_PREFIX)) {
            $next();
            return;
        }

        $suffix = substr($path, strlen(self::PLATFORM_PREFIX));
        $firstSegment = explode('/', $suffix)[0] ?? '';
        $pathKey = 'api/v1/platform/' . $firstSegment;

        if (in_array($pathKey, self::PUBLIC_PATHS, true)) {
            $next();
            return;
        }

        $platformUserId = $this->session->get(self::SESSION_PLATFORM_USER_ID);
        if ($platformUserId === null || $platformUserId === '') {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Platform authentication required']);
            return;
        }

        $next();
    }
}
