-- ============================================================
-- DRITHI AGRO — Migration: Fix missing columns & triggers
-- Run this on your PostgreSQL database
-- ============================================================

-- Add weight column to products if missing
ALTER TABLE products ADD COLUMN IF NOT EXISTS weight NUMERIC(8,2);

-- Add is_active alias (schema uses is_active, some queries check status)
-- Already exists as is_active BOOLEAN — no change needed

-- Add logo_url and description to brands if missing
ALTER TABLE brands ADD COLUMN IF NOT EXISTS logo_url   VARCHAR(500);
ALTER TABLE brands ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE brands ADD COLUMN IF NOT EXISTS is_active  BOOLEAN DEFAULT TRUE;

-- Auto-create inventory row when a product is inserted
CREATE OR REPLACE FUNCTION create_inventory_for_product()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO inventory (id, product_id, current_stock, low_stock_threshold)
    VALUES (gen_random_uuid(), NEW.id, NEW.stock_qty, 10)
    ON CONFLICT (product_id) DO NOTHING;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auto_inventory ON products;
CREATE TRIGGER trg_auto_inventory
    AFTER INSERT ON products
    FOR EACH ROW EXECUTE FUNCTION create_inventory_for_product();

-- Sync inventory stock when products.stock_qty changes
CREATE OR REPLACE FUNCTION sync_inventory_stock()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    UPDATE inventory SET current_stock = NEW.stock_qty, updated_at = NOW()
    WHERE product_id = NEW.id;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_sync_inventory ON products;
CREATE TRIGGER trg_sync_inventory
    AFTER UPDATE OF stock_qty ON products
    FOR EACH ROW EXECUTE FUNCTION sync_inventory_stock();

-- Back-fill inventory for any existing products that don't have a row
INSERT INTO inventory (id, product_id, current_stock, low_stock_threshold)
SELECT gen_random_uuid(), p.id, p.stock_qty, 10
FROM products p
WHERE NOT EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id);

-- Add product_name and product_image to order_items if missing
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS product_name  VARCHAR(300);
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS product_image VARCHAR(500);

-- Update existing order_items with product names
UPDATE order_items oi
SET product_name = p.name
FROM products p
WHERE oi.product_id = p.id AND oi.product_name IS NULL;

-- Add vendor_id to order_items if missing
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS vendor_id UUID REFERENCES vendors(id);

-- Update existing order_items with vendor_id
UPDATE order_items oi
SET vendor_id = p.vendor_id
FROM products p
WHERE oi.product_id = p.id AND oi.vendor_id IS NULL;

-- Add unit_price alias (some queries use unit_price, schema uses price)
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS unit_price NUMERIC(10,2);
UPDATE order_items SET unit_price = price WHERE unit_price IS NULL;

-- Add placed_at alias for created_at on orders
ALTER TABLE orders ADD COLUMN IF NOT EXISTS placed_at TIMESTAMPTZ DEFAULT NOW();
UPDATE orders SET placed_at = created_at WHERE placed_at IS NULL;
