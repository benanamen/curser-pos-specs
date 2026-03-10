-- Items (per-tenant)
CREATE TABLE IF NOT EXISTS items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    sku VARCHAR(100) NOT NULL,
    barcode VARCHAR(100),
    consignor_id UUID REFERENCES consignors(id) ON DELETE SET NULL,
    category_id UUID REFERENCES categories(id) ON DELETE SET NULL,
    location_id UUID REFERENCES locations(id) ON DELETE SET NULL,
    description TEXT,
    size VARCHAR(100),
    condition VARCHAR(50),
    price DECIMAL(12,2) NOT NULL,
    store_share_pct DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    consignor_share_pct DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    status VARCHAR(50) NOT NULL DEFAULT 'available',
    intake_date DATE NOT NULL,
    expiry_date DATE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_items_sku ON items(sku);
CREATE INDEX IF NOT EXISTS idx_items_barcode ON items(barcode);
CREATE INDEX IF NOT EXISTS idx_items_status ON items(status);
CREATE INDEX IF NOT EXISTS idx_items_consignor ON items(consignor_id);
