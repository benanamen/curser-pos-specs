-- Held sales (per-tenant)
CREATE TABLE IF NOT EXISTS held_sales (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cart_data JSONB NOT NULL DEFAULT '{}',
    user_id UUID NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Store credits (per-tenant)
CREATE TABLE IF NOT EXISTS store_credits (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    consignor_id UUID REFERENCES consignors(id) ON DELETE SET NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Gift cards (per-tenant)
CREATE TABLE IF NOT EXISTS gift_cards (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code VARCHAR(50) NOT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_gift_cards_code ON gift_cards(code);
