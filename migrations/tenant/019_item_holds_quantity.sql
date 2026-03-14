-- Item holds: add quantity, change PK to (item_id, held_id)
ALTER TABLE item_holds DROP CONSTRAINT IF EXISTS item_holds_pkey;
ALTER TABLE item_holds ADD COLUMN IF NOT EXISTS quantity INTEGER NOT NULL DEFAULT 1;
ALTER TABLE item_holds ADD PRIMARY KEY (item_id, held_id);
