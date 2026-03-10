-- Plans table (Lite, Pro tiers)
CREATE TABLE IF NOT EXISTS plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    tier VARCHAR(50) NOT NULL,
    item_limit INTEGER NOT NULL DEFAULT 50,
    features JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO plans (id, name, tier, item_limit, features) VALUES
    ('a0000000-0000-0000-0000-000000000001', 'Lite', 'lite', 50, '{"consignor_portal": false, "multi_location": false, "vendor_portal": false}'),
    ('a0000000-0000-0000-0000-000000000002', 'Pro', 'pro', 0, '{"consignor_portal": true, "multi_location": true, "vendor_portal": false}'),
    ('a0000000-0000-0000-0000-000000000003', 'Enterprise', 'enterprise', 0, '{"consignor_portal": true, "multi_location": true, "vendor_portal": true}')
ON CONFLICT (id) DO NOTHING;
