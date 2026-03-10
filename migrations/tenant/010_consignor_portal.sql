-- Consignor portal: token for passwordless portal access (Pro feature)
ALTER TABLE consignors ADD COLUMN IF NOT EXISTS portal_token VARCHAR(64) UNIQUE;
CREATE INDEX IF NOT EXISTS idx_consignors_portal_token ON consignors(portal_token);
