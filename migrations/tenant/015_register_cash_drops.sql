-- Cash drops (money taken out of drawer during shift)
CREATE TABLE IF NOT EXISTS register_cash_drops (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    register_id UUID NOT NULL REFERENCES registers(id) ON DELETE CASCADE,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_register_cash_drops_register ON register_cash_drops(register_id);
