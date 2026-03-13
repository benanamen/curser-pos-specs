<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http;

use CurserPos\Http\RequestContext;
use CurserPos\Http\RequestContextHolder;
use PHPUnit\Framework\TestCase;

final class RequestContextHolderTest extends TestCase
{
    protected function tearDown(): void
    {
        RequestContextHolder::clear();
        parent::tearDown();
    }

    public function testSetAndGet(): void
    {
        $context = new RequestContext();
        $context->requestUri = '/test';
        RequestContextHolder::set($context);
        $this->assertSame($context, RequestContextHolder::get());
    }

    public function testGetReturnsNullWhenNotSet(): void
    {
        RequestContextHolder::clear();
        $this->assertNull(RequestContextHolder::get());
    }

    public function testClearRemovesContext(): void
    {
        $context = new RequestContext();
        RequestContextHolder::set($context);
        RequestContextHolder::clear();
        $this->assertNull(RequestContextHolder::get());
    }
}
