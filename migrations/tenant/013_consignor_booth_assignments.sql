-- Assign consignors (vendors) to booths; one active assignment per consignor
CREATE TABLE IF NOT EXISTS consignor_booth_assignments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    consignor_id UUID NOT NULL REFERENCES consignors(id) ON DELETE CASCADE,
    booth_id UUID NOT NULL REFERENCES booths(id) ON DELETE CASCADE,
    started_at DATE NOT NULL DEFAULT CURRENT_DATE,
    ended_at DATE,
    monthly_rent DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_consignor_booth_active ON consignor_booth_assignments(consignor_id) WHERE ended_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_consignor_booth_booth ON consignor_booth_assignments(booth_id);
