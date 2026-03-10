<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Audit\ActivityLogRepository;
use CurserPos\Service\AuditService;
use PHPUnit\Framework\TestCase;

final class AuditServiceTest extends TestCase
{
    public function testLogCallsRepository(): void
    {
        $repo = $this->createMock(ActivityLogRepository::class);
        $repo->expects($this->once())->method('log')->with(
            'tenant-1',
            'user-1',
            null,
            'auth.login',
            null,
            null,
            ['email' => 'test@example.com'],
            '127.0.0.1'
        );

        $service = new AuditService($repo);
        $service->log('tenant-1', 'user-1', null, 'auth.login', null, null, ['email' => 'test@example.com'], '127.0.0.1');
    }

    public function testLogWithMinimalParams(): void
    {
        $repo = $this->createMock(ActivityLogRepository::class);
        $repo->expects($this->once())->method('log')->with(
            null,
            null,
            'support-1',
            'support.impersonate',
            'tenant',
            't-1',
            [],
            null
        );

        $service = new AuditService($repo);
        $service->log(null, null, 'support-1', 'support.impersonate', 'tenant', 't-1');
    }
}
