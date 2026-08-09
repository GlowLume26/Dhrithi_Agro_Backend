-- ============================================================
-- DRITHI AGRO — Admin User Seed
-- Run AFTER schema_pg.sql and migration_fixes.sql
-- ============================================================

-- Delete existing admin if any
DELETE FROM users WHERE email IN ('admin@drithiagro.com', 'owner@drithiagro.com');

-- Insert Owner (super admin) — password: Admin@1234
-- Hash generated with: php -r "echo password_hash('Admin@1234', PASSWORD_DEFAULT);"
INSERT INTO users (first_name, last_name, email, mobile, password_hash, role, is_active)
VALUES (
    'Drithi', 'Owner',
    'owner@drithiagro.com',
    '9000000001',
    '$2y$12$rW1daBj3BM1E.vXWDa8qOO5p8d5HmdHHN7bJr4LOgPbz6nE3z5x6e',
    'owner',
    TRUE
);

-- Insert Admin — password: Admin@1234
INSERT INTO users (first_name, last_name, email, mobile, password_hash, role, is_active)
VALUES (
    'Drithi', 'Admin',
    'admin@drithiagro.com',
    '9000000002',
    '$2y$12$rW1daBj3BM1E.vXWDa8qOO5p8d5HmdHHN7bJr4LOgPbz6nE3z5x6e',
    'admin',
    TRUE
);

-- ============================================================
-- HOW TO RUN EVERYTHING (in order):
-- ============================================================
-- 1. psql -U postgres -c "CREATE DATABASE drithi_agro;"
-- 2. psql -U postgres -d drithi_agro -f database/schema_pg.sql
-- 3. psql -U postgres -d drithi_agro -f database/migration_fixes.sql
-- 4. psql -U postgres -d drithi_agro -f database/admin_seed.sql
-- 5. psql -U postgres -d drithi_agro -f database/demo_products.sql  (optional)
-- ============================================================
-- Admin login credentials:
--   owner@drithiagro.com  /  Admin@1234  (full access)
--   admin@drithiagro.com  /  Admin@1234  (limited access)
--
-- NOTE: If the hash above doesn't work, regenerate it by running:
--   php -r "echo password_hash('Admin@1234', PASSWORD_DEFAULT);"
-- Then replace both hashes above with the output.
-- ============================================================
