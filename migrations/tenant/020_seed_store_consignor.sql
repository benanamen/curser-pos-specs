-- Ensure default "Store" consignor exists (for tenants created via provision or manual migration run)
INSERT INTO consignors (id, slug, custom_id, name, email, phone, address, default_commission_pct, agreement_signed_at, status, notes, created_at, updated_at)
SELECT gen_random_uuid(), 'store', NULL, 'Store', NULL, NULL, NULL, 0.0, NULL, 'active', NULL, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM consignors WHERE slug = 'store');

INSERT INTO consignor_balances (id, consignor_id, balance, pending_sales, paid_out, updated_at)
SELECT gen_random_uuid(), c.id, 0, 0, 0, NOW()
FROM consignors c
WHERE c.slug = 'store'
  AND NOT EXISTS (SELECT 1 FROM consignor_balances WHERE consignor_id = c.id);
