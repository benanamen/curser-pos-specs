-- Optional custom consignor ID (store-defined identifier)
ALTER TABLE consignors
    ADD COLUMN IF NOT EXISTS custom_id VARCHAR(100);

CREATE UNIQUE INDEX IF NOT EXISTS idx_consignors_custom_id ON consignors(custom_id) WHERE custom_id IS NOT NULL;

