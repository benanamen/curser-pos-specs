-- Tenant billing (provider-agnostic: Stripe, Paddle, etc.)
CREATE TABLE IF NOT EXISTS tenant_billing (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    provider VARCHAR(50) NOT NULL DEFAULT 'stripe',
    external_customer_id VARCHAR(255),
    external_subscription_id VARCHAR(255),
    plan_id UUID NOT NULL REFERENCES plans(id),
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    current_period_start TIMESTAMPTZ,
    current_period_end TIMESTAMPTZ,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_tenant_billing_tenant ON tenant_billing(tenant_id);
CREATE INDEX IF NOT EXISTS idx_tenant_billing_external_sub ON tenant_billing(external_subscription_id) WHERE external_subscription_id IS NOT NULL;
