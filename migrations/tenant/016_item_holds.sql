-- Item holds (reserve inventory for held sales)
CREATE TABLE IF NOT EXISTS item_holds (
    item_id UUID PRIMARY KEY REFERENCES items(id) ON DELETE CASCADE,
    held_id UUID NOT NULL REFERENCES held_sales(id) ON DELETE CASCADE,
    user_id UUID NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_item_holds_held_id ON item_holds(held_id);

