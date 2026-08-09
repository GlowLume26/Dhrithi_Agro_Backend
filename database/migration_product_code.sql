-- Migration: Add product_code column to products table
ALTER TABLE products ADD COLUMN IF NOT EXISTS product_code VARCHAR(50);

-- Backfill existing products with a code based on their id
UPDATE products SET product_code = 'PRD-' || UPPER(SUBSTRING(id::text, 1, 8)) WHERE product_code IS NULL;
