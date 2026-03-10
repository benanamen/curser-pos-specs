<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http;

use CurserPos\Http\RequestContext;
use PHPUnit\Framework\TestCase;

final class RequestContextTest extends TestCase
{
    public function testFromGlobalsCreatesContext(): void
    {
        $_SERVER['REQUEST_URI'] = '/t/mystore/api/v1/health';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);

        $ctx = RequestContext::fromGlobals();
        $this->assertSame('/t/mystore/api/v1/health', $ctx->requestUri);
        $this->assertSame('GET', $ctx->requestMethod);
    }

    public function testFromGlobalsStripsQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/t/mystore/api/v1/items?status=available';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $ctx = RequestContext::fromGlobals();
        $this->assertSame('/t/mystore/api/v1/items', $ctx->requestUri);
    }

    public function testFromGlobalsSetsClientIpFromRemoteAddr(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $ctx = RequestContext::fromGlobals();
        $this->assertSame('192.168.1.1', $ctx->clientIp);
    }

    public function testFromGlobalsPrefersXForwardedFor(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 192.168.1.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $ctx = RequestContext::fromGlobals();
        $this->assertSame('10.0.0.1', $ctx->clientIp);
    }
}
