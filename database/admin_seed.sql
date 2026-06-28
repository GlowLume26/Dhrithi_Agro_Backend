-- ============================================================
-- DRITHI AGRO — Admin User Seed
-- Run AFTER schema_pg.sql and migration_fixes.sql
-- ============================================================

-- Delete existing admin if any
DELETE FROM users WHERE email IN ('admin@drithiagro.com', 'owner@drithiagro.com');

-- Insert Owner (super admin) — password: Admin@1234
-- Hash generated with PHP: password_hash('Admin@1234', PASSWORD_DEFAULT)
INSERT INTO users (first_name, last_name, email, mobile, password_hash, role, is_active)
VALUES (
    'Drithi', 'Owner',
    'owner@drithiagro.com',
    '9000000001',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'owner',
    TRUE
);

-- Insert Admin — password: Admin@1234
INSERT INTO users (first_name, last_name, email, mobile, password_hash, role, is_active)
VALUES (
    'Drithi', 'Admin',
    'admin@drithiagro.com',
    '9000000002',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
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
-- Admin login: owner@drithiagro.com / password
-- NOTE: The hash above is the Laravel default test hash for "password"
-- To use Admin@1234, run this PHP to get the real hash:
--   php -r "echo password_hash('Admin@1234', PASSWORD_DEFAULT);"
-- Then replace the hash above with the output
-- ============================================================
