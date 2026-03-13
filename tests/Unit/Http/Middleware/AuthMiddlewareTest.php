<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http\Middleware;

use CurserPos\Domain\Audit\ActivityLogRepository;
use CurserPos\Domain\Tenant\Tenant;
use CurserPos\Domain\Tenant\TenantUserRepository;
use CurserPos\Domain\User\User;
use CurserPos\Domain\User\UserRepositoryInterface;
use CurserPos\Http\Middleware\AuthMiddleware;
use CurserPos\Http\RequestContext;
use CurserPos\Service\AuditService;
use PHPUnit\Framework\TestCase;
use PerfectApp\Session\Session;

final class AuthMiddlewareTest extends TestCase
{
    public function testCallsNextWhenPathDoesNotRequireAuth(): void
    {
        $session = $this->createMock(Session::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $audit = new AuditService($this->createMock(ActivityLogRepository::class));

        $context = new RequestContext();
        $context->requestUri = '/api/v1/signup';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new AuthMiddleware($session, $userRepo, $tenantUserRepo, $audit);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenTenantPathHealth(): void
    {
        $session = $this->createMock(Session::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $audit = new AuditService($this->createMock(ActivityLogRepository::class));

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/health';
        $context->tenant = $this->createTenant();
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new AuthMiddleware($session, $userRepo, $tenantUserRepo, $audit);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenPortalPathAndConsignorSet(): void
    {
        $session = $this->createMock(Session::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $audit = new AuditService($this->createMock(ActivityLogRepository::class));

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/portal/me';
        $context->tenant = $this->createTenant();
        $now = new \DateTimeImmutable();
        $context->consignor = new \CurserPos\Domain\Consignor\Consignor('c1', 'jane', null, 'Jane', null, null, null, 50.0, null, 'active', null, $now, $now);
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new AuthMiddleware($session, $userRepo, $tenantUserRepo, $audit);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testSetsSupportAccessAndCallsNextWhenPlatformUserIdInSession(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('get')->with('platform_user_id')->willReturn('plat-1');
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $activityLogRepo = $this->createMock(ActivityLogRepository::class);
        $activityLogRepo->expects($this->once())->method('log');
        $audit = new AuditService($activityLogRepo);

        $tenant = $this->createTenant();
        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/items';
        $context->tenant = $tenant;
        $context->requestMethod = 'GET';
        $context->clientIp = '127.0.0.1';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new AuthMiddleware($session, $userRepo, $tenantUserRepo, $audit);
        $middleware($context, $next);
        $this->assertTrue($context->isSupportAccess);
        $this->assertSame('plat-1', $context->supportUserId);
        $this->assertTrue($called);
    }

    public function testReturns401WhenNoUserIdInSession(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('get')->willReturnMap([['platform_user_id', null], ['user_id', null]]);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $audit = new AuditService($this->createMock(ActivityLogRepository::class));

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/items';
        $context->tenant = $this->createTenant();
        $next = function () { $this->fail('Next should not be called'); };

        $middleware = new AuthMiddleware($session, $userRepo, $tenantUserRepo, $audit);
        ob_start();
        $middleware($context, $next);
        $out = ob_get_clean();
        $this->assertStringContainsString('Authentication required', $out);
    }

    public function testReturns401WhenUserNotFound(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('get')->willReturnMap([['platform_user_id', null], ['user_id', 'user-1']]);
        $session->expects($this->atLeastOnce())->method('delete');
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findById')->with('user-1')->willReturn(null);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $audit = new AuditService($this->createMock(ActivityLogRepository::class));

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/items';
        $context->tenant = $this->createTenant();
        $next = function () { $this->fail('Next should not be called'); };

        $middleware = new AuthMiddleware($session, $userRepo, $tenantUserRepo, $audit);
        ob_start();
        $middleware($context, $next);
        ob_get_clean();
    }

    public function testReturns403WhenTenantUserNull(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('get')->willReturnMap([['platform_user_id', null], ['user_id', 'user-1']]);
        $now = new \DateTimeImmutable();
        $user = new User('user-1', 'u@example.com', 'active', $now, $now);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findById')->with('user-1')->willReturn($user);
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $tenantUserRepo->method('getByUserAndTenant')->willReturn(null);
        $audit = new AuditService($this->createMock(ActivityLogRepository::class));

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/items';
        $context->tenant = $this->createTenant();
        $next = function () { $this->fail('Next should not be called'); };

        $middleware = new AuthMiddleware($session, $userRepo, $tenantUserRepo, $audit);
        ob_start();
        $middleware($context, $next);
        $out = ob_get_clean();
        $this->assertStringContainsString('No access to this tenant', $out);
    }

    public function testSetsUserAndTenantUserAndCallsNext(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('get')->willReturnMap([['platform_user_id', null], ['user_id', 'user-1']]);
        $now = new \DateTimeImmutable();
        $user = new User('user-1', 'u@example.com', 'active', $now, $now);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findById')->with('user-1')->willReturn($user);
        $tenantUser = ['id' => 'tu1', 'permissions' => []];
        $tenantUserRepo = $this->createMock(TenantUserRepository::class);
        $tenantUserRepo->method('getByUserAndTenant')->with('user-1', 't1')->willReturn($tenantUser);
        $audit = new AuditService($this->createMock(ActivityLogRepository::class));

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/items';
        $context->tenant = $this->createTenant();
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new AuthMiddleware($session, $userRepo, $tenantUserRepo, $audit);
        $middleware($context, $next);
        $this->assertSame($user, $context->user);
        $this->assertSame($tenantUser, $context->tenantUser);
        $this->assertTrue($called);
    }

    private function createTenant(): Tenant
    {
        return new Tenant('t1', 'mystore', 'My Store', 'active', 'plan-1', [], new \DateTimeImmutable(), new \DateTimeImmutable());
    }
}
