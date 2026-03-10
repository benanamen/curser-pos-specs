# Next Steps — Curser POS

**Last updated:** Session before closing. Use this to resume work in a new Cursor session.

---

## Project Summary

Multi-tenant SaaS POS for consignment stores. PHP 8.4, PostgreSQL, schema-per-tenant. API-first with session auth.

**Repo:** https://github.com/benanamen/curser-pos-specs (private)

---

## Completed

- **Auth:** Signup, login, logout, password reset (forgot + reset token), staff invite (accept-invite)
- **RBAC:** Roles (admin, manager, cashier), PermissionMiddleware, route→permission map
- **Super-admin:** Platform users table, platform login/logout, support access (impersonate tenant via session)
- **User/staff management:** List users, invite (token), update role/active
- **Audit log:** ActivityLogRepository, AuditService, audit on login/logout/void/refund/payout/config/user actions
- **Vendor portal:** Consignor portal extended for Enterprise plan; vendors add/edit own items and prices
- **OpenAPI:** `openapi.yaml` for API testing (Swagger, Postman, etc.)
- **Unit tests:** ~80 tests for new features; overall coverage ~21%
- **Git:** Initial commit, pushed to GitHub

---

## Possible Next Steps (pick any)

1. **Unit test coverage to 100%** — Add tests for: PosService, InventoryService, ConsignorService, PayoutService, ReportService, BoothRentalService, remaining repositories, middleware (AuthMiddleware, TenantResolutionMiddleware, etc.). See `phpunit.xml` — `src/Api` is excluded.

2. **Platform admin seed** — Create first platform user for support access. Option: CLI script or migration that inserts into `platform_users` with a hashed password.

3. **Email for password reset** — `PasswordResetService::requestReset()` returns a token but does not send email. Integrate mail (or a stub) to send reset links.

4. **Email for staff invites** — `UserInviteService::invite()` returns invite URL but does not send email. Add mail integration.

5. **Support access audit** — Log when platform user first accesses a tenant (e.g. in AuthMiddleware when `isSupportAccess` is set).

6. **Run shared migrations** — If starting fresh: `php bin/migrate.php shared`. Enterprise plan has `vendor_portal: true`; ensure tenants can be assigned that plan.

7. **Integration tests** — Expand beyond `HealthEndpointTest`; add tests for auth flow, POS checkout, etc. (may need test DB or mocks.)

8. **Frontend** — No UI yet; API is ready. Build web or mobile client.

9. **FEATURE-LIST.md** — Review remaining P1/P2 items (partial refunds, promos, layaway, 2FA, etc.).

---

## Key Files

| Purpose | Path |
|---------|------|
| API entry | `public/index.php` |
| Routes | `src/Api/V1/*Controller.php` (attribute routing) |
| Auth | `AuthService`, `AuthMiddleware`, `PlatformAuthService` |
| RBAC | `PermissionMiddleware`, `RoleRepository`, `TenantUserRepository` |
| Audit | `AuditService`, `ActivityLogRepository` |
| Container | `config/container.php` |
| Migrations | `migrations/shared/`, `migrations/tenant/` |
| OpenAPI | `openapi.yaml` |

---

## Commands

```bash
composer install
php bin/migrate.php shared                    # Run shared migrations
php vendor/bin/phpunit                       # Run tests
php vendor/bin/phpunit --coverage-text       # With coverage
cd public && php -S localhost:8000           # Dev server
```

---

## When Resuming

Tell the AI: *"Read NEXT-STEPS.md and continue from there. I want to [pick a task from above]."*
