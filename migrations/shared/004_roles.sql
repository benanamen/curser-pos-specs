-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL UNIQUE,
    permissions JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (id, name, permissions) VALUES
    ('b0000000-0000-0000-0000-000000000001', 'admin', '{"all": true}'),
    ('b0000000-0000-0000-0000-000000000002', 'manager', '{"pos": true, "inventory": true, "payouts": true, "reports": true}'),
    ('b0000000-0000-0000-0000-000000000003', 'cashier', '{"pos": true}')
ON CONFLICT (id) DO NOTHING;
