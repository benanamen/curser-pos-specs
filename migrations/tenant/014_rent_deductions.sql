-- Rent deductions (audit: rent taken from vendor payouts or paid separately)
CREATE TABLE IF NOT EXISTS rent_deductions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    consignor_id UUID NOT NULL REFERENCES consignors(id) ON DELETE CASCADE,
    amount DECIMAL(12,2) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    payout_id UUID REFERENCES payouts(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_rent_deductions_consignor ON rent_deductions(consignor_id);
CREATE INDEX IF NOT EXISTS idx_rent_deductions_period ON rent_deductions(period_start, period_end);
