-- Migration: Add vendor_type, is_cnf to vendors; expand vendor_documents document_type

-- Add vendor_type column
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS vendor_type VARCHAR(10) DEFAULT 'seller' CHECK (vendor_type IN ('buyer','seller'));

-- Add is_cnf column
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS is_cnf BOOLEAN DEFAULT FALSE;

-- Expand vendor_documents document_type constraint to include new types
ALTER TABLE vendor_documents DROP CONSTRAINT IF EXISTS vendor_documents_document_type_check;
ALTER TABLE vendor_documents ADD CONSTRAINT vendor_documents_document_type_check
  CHECK (document_type IN ('AADHAAR','PAN','GST_CERTIFICATE','BUSINESS_LOGO','TRADE_LICENCE','BUSINESS_REG','BANK_PASSBOOK','OTHER'));

-- Make gst_number and pan_number nullable (buyers may not have them)
ALTER TABLE vendors ALTER COLUMN gst_number DROP NOT NULL;
ALTER TABLE vendors ALTER COLUMN pan_number DROP NOT NULL;
ALTER TABLE vendors DROP CONSTRAINT IF EXISTS vendors_gst_number_key;
ALTER TABLE vendors ADD CONSTRAINT vendors_gst_number_key UNIQUE (gst_number);

-- Make email nullable (buyers may not provide email)
ALTER TABLE vendors ALTER COLUMN email DROP NOT NULL;
