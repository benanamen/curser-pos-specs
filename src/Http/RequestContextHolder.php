<?php

declare(strict_types=1);

namespace CurserPos\Http;

final class RequestContextHolder
{
    private static ?RequestContext $context = null;

    public static function set(RequestContext $context): void
    {
        self::$context = $context;
    }

    public static function get(): ?RequestContext
    {
        return self::$context;
    }

    public static function clear(): void
    {
        self::$context = null;
    }
}
