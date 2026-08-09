-- ============================================================
-- MIGRATION: Buyer/Seller types, Licence doc, Commission rates
-- Run this on your drithi_agro PostgreSQL database
-- ============================================================

-- 1. Add vendor_type to vendors (buyer | seller)
ALTER TABLE vendors
  ADD COLUMN IF NOT EXISTS vendor_type   VARCHAR(10) NOT NULL DEFAULT 'seller'
    CHECK (vendor_type IN ('buyer','seller')),
  ADD COLUMN IF NOT EXISTS licence_number VARCHAR(50);

-- 2. Expand vendor_documents document_type allowed values
ALTER TABLE vendor_documents
  DROP CONSTRAINT IF EXISTS vendor_documents_document_type_check;

ALTER TABLE vendor_documents
  ADD CONSTRAINT vendor_documents_document_type_check
    CHECK (document_type IN (
      'AADHAAR','PAN','GST_CERTIFICATE','TRADE_LICENCE',
      'BUSINESS_REGISTRATION','BANK_PASSBOOK','BUSINESS_LOGO','OTHER'
    ));

-- 3. Commission rates table (admin-managed, not hardcoded)
CREATE TABLE IF NOT EXISTS commission_rates (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    category     VARCHAR(100) NOT NULL UNIQUE,
    rate         DECIMAL(5,2) NOT NULL DEFAULT 8.00,
    gst_on_comm  DECIMAL(5,2) NOT NULL DEFAULT 18.00,
    is_active    BOOLEAN DEFAULT TRUE,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Seed default commission rates
INSERT INTO commission_rates (category, rate) VALUES
  ('Seeds & Planting',        8.00),
  ('Fertilizers & Nutrients', 7.00),
  ('Pesticides & Herbicides', 9.00),
  ('Organic Farming',         6.00),
  ('Farm Equipment',          5.00),
  ('Irrigation Systems',      5.00),
  ('Animal Husbandry',        7.00),
  ('Multi-Category',          8.00)
ON CONFLICT (category) DO NOTHING;

-- 5. Index for vendor_type filter
CREATE INDEX IF NOT EXISTS idx_vendors_type   ON vendors (vendor_type);
CREATE INDEX IF NOT EXISTS idx_vendors_status ON vendors (status);
