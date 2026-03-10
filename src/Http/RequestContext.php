<?php

declare(strict_types=1);

namespace CurserPos\Http;

use CurserPos\Domain\Consignor\Consignor;
use CurserPos\Domain\Tenant\Tenant;
use CurserPos\Domain\User\User;

final class RequestContext
{
    public ?Tenant $tenant = null;
    public ?User $user = null;
    public ?Consignor $consignor = null;
    public ?array $tenantUser = null;
    public string $requestUri = '';
    public string $requestMethod = 'GET';
    public ?string $tenantSlug = null;
    public ?string $clientIp = null;
    public bool $isSupportAccess = false;
    public ?string $supportUserId = null;
    /** @var array<string, string> */
    public array $queryParams = [];

    public static function fromGlobals(): self
    {
        $ctx = new self();
        $ctx->requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $ctx->requestUri = str_contains($ctx->requestUri, '?')
            ? strstr($ctx->requestUri, '?', true)
            : $ctx->requestUri;
        $ctx->requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $ctx->clientIp = isset($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
            : (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
        return $ctx;
    }
}
