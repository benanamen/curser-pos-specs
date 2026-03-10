<?php

declare(strict_types=1);

namespace CurserPos\Http\Middleware;

use CurserPos\Http\RequestContext;
use PDO;

final class SchemaContextMiddleware
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function __invoke(RequestContext $context, callable $next): void
    {
        if ($context->tenant === null) {
            $next();
            return;
        }

        $schemaName = $context->tenant->schemaName();
        $this->pdo->exec("SET search_path TO \"{$schemaName}\", public");
        $next();
    }
}
