# Technical Notes

## Tenant provisioning: default "Store" consignor

When a new tenant (store) is provisioned, `TenantProvisioningService::seedTenantBaseline()` creates a **default "Store" consignor** as the first consignor for that tenant.

- **Implementation**: `src/Domain/Tenant/TenantProvisioningService.php`, method `seedTenantBaseline()`.
- **Behavior**:
  - Inserts one consignor with **name** `"Store"` and **slug** `store`. If `store` is already taken in that tenant schema, slugs `store-1`, `store-2`, … are used.
  - **default_commission_pct** is `0` (store keeps 100% of sales for store-owned items).
  - **status** is `active`.
  - A row is inserted into **consignor_balances** for this consignor with zero balance, zero pending_sales, and zero paid_out.
- **Purpose**: Supports retail/store-owned inventory and sales where the store keeps 100%; the Store consignor is available in Instant Inventory and elsewhere so staff can assign store-owned items to it.

This runs automatically as part of `provision()` after the tenant schema is created and tenant migrations have been run.
