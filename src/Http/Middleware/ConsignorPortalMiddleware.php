<?php

declare(strict_types=1);

namespace CurserPos\Http\Middleware;

use CurserPos\Domain\Consignor\ConsignorRepository;
use CurserPos\Http\RequestContext;

final class ConsignorPortalMiddleware
{
    private const PORTAL_PATH_SEGMENT = '/api/v1/portal/';

    public function __construct(
        private readonly ConsignorRepository $consignorRepository
    ) {
    }

    public function __invoke(RequestContext $context, callable $next): void
    {
        if (!str_contains($context->requestUri, self::PORTAL_PATH_SEGMENT)) {
            $next();
            return;
        }

        $token = $this->getToken();
        if ($token === null || $token === '') {
            $next();
            return;
        }

        if ($context->tenant === null) {
            $next();
            return;
        }

        $consignor = $this->consignorRepository->findByPortalToken($token);
        if ($consignor !== null) {
            $context->consignor = $consignor;
        }

        $next();
    }

    private function getToken(): ?string
    {
        $header = $_SERVER['HTTP_X_CONSIGNOR_PORTAL_TOKEN'] ?? null;
        if ($header !== null && $header !== '') {
            return $header;
        }
        return isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : null;
    }
}
