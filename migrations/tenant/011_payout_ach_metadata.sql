-- ACH payout support: optional metadata (e.g. last4, bank name, batch id)
ALTER TABLE payouts ADD COLUMN IF NOT EXISTS method_metadata JSONB;
