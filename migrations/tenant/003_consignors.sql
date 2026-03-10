-- Consignors (per-tenant)
CREATE TABLE IF NOT EXISTS consignors (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    default_commission_pct DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    agreement_signed_at DATE,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_consignors_slug ON consignors(slug);

-- Consignor balances
CREATE TABLE IF NOT EXISTS consignor_balances (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    consignor_id UUID NOT NULL REFERENCES consignors(id) ON DELETE CASCADE,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    pending_sales DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_out DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(consignor_id)
);
