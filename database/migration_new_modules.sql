-- ============================================================
-- DRITHI AGRO — New Modules Migration
-- Run AFTER schema_pg.sql and migration_fixes.sql
-- ============================================================

-- 1. USER PERMISSIONS (granular per-user module access)
CREATE TABLE IF NOT EXISTS user_permissions (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    module     VARCHAR(60) NOT NULL,
    granted    BOOLEAN NOT NULL DEFAULT TRUE,
    granted_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, module)
);
CREATE INDEX IF NOT EXISTS idx_user_permissions_user ON user_permissions(user_id);

-- 2. PERMISSION AUDIT LOG
CREATE TABLE IF NOT EXISTS permission_audit (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    changed_by   UUID NOT NULL REFERENCES users(id),
    target_user  UUID NOT NULL REFERENCES users(id),
    module       VARCHAR(60) NOT NULL,
    old_value    BOOLEAN,
    new_value    BOOLEAN NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. MANUFACTURERS
CREATE TABLE IF NOT EXISTS manufacturers (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name         VARCHAR(200) NOT NULL,
    contact_name VARCHAR(100),
    email        VARCHAR(100),
    mobile       VARCHAR(15),
    address      TEXT,
    city         VARCHAR(100),
    state        VARCHAR(100),
    pincode      VARCHAR(10),
    gst_number   VARCHAR(20),
    is_active    BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. MANUFACTURER ORDERS (links existing orders to manufacturers)
CREATE TABLE IF NOT EXISTS manufacturer_orders (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id        UUID REFERENCES orders(id) ON DELETE SET NULL,
    manufacturer_id UUID NOT NULL REFERENCES manufacturers(id),
    order_number    VARCHAR(30) UNIQUE,
    order_date      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount    DECIMAL(12,2) DEFAULT 0,
    status          VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','confirmed','dispatched','delivered','cancelled')),
    invoice_number  VARCHAR(50),
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_mfr_orders_mfr ON manufacturer_orders(manufacturer_id);

-- 5. MANUFACTURER ORDER ITEMS
CREATE TABLE IF NOT EXISTS manufacturer_order_items (
    id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    manufacturer_order_id UUID NOT NULL REFERENCES manufacturer_orders(id) ON DELETE CASCADE,
    product_id            UUID REFERENCES products(id),
    product_name          VARCHAR(300) NOT NULL,
    quantity              INT NOT NULL DEFAULT 1,
    unit_price            DECIMAL(10,2) NOT NULL,
    tax_rate              DECIMAL(5,2) DEFAULT 0,
    discount              DECIMAL(10,2) DEFAULT 0,
    total                 DECIMAL(12,2) NOT NULL
);

-- 6. CNF COMPANIES
CREATE TABLE IF NOT EXISTS cnf_companies (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name           VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    contact_number VARCHAR(15),
    email          VARCHAR(100),
    address        TEXT,
    city           VARCHAR(100),
    state          VARCHAR(100),
    pincode        VARCHAR(10),
    gst_number     VARCHAR(20),
    status         VARCHAR(10) DEFAULT 'active' CHECK (status IN ('active','inactive')),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. WAREHOUSES
CREATE TABLE IF NOT EXISTS warehouses (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cnf_company_id UUID NOT NULL REFERENCES cnf_companies(id) ON DELETE CASCADE,
    name           VARCHAR(200) NOT NULL,
    address        TEXT,
    city           VARCHAR(100),
    state          VARCHAR(100),
    pincode        VARCHAR(10),
    manager_name   VARCHAR(100),
    contact_number VARCHAR(15),
    email          VARCHAR(100),
    status         VARCHAR(10) DEFAULT 'active' CHECK (status IN ('active','inactive')),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_warehouses_cnf ON warehouses(cnf_company_id);

-- 8. WAREHOUSE EMPLOYEES
CREATE TABLE IF NOT EXISTS warehouse_employees (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id      UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    warehouse_id UUID NOT NULL REFERENCES warehouses(id) ON DELETE CASCADE,
    role         VARCHAR(50) DEFAULT 'staff',
    is_active    BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, warehouse_id)
);

-- 9. WAREHOUSE INVENTORY
CREATE TABLE IF NOT EXISTS warehouse_inventory (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    warehouse_id     UUID NOT NULL REFERENCES warehouses(id) ON DELETE CASCADE,
    product_id       UUID NOT NULL REFERENCES products(id),
    batch_number     VARCHAR(50),
    expiry_date      DATE,
    current_stock    INT NOT NULL DEFAULT 0,
    reserved_stock   INT DEFAULT 0,
    incoming_stock   INT DEFAULT 0,
    outgoing_stock   INT DEFAULT 0,
    last_movement_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (warehouse_id, product_id, batch_number)
);
CREATE INDEX IF NOT EXISTS idx_wh_inv_warehouse ON warehouse_inventory(warehouse_id);
CREATE INDEX IF NOT EXISTS idx_wh_inv_product   ON warehouse_inventory(product_id);
CREATE INDEX IF NOT EXISTS idx_wh_inv_expiry     ON warehouse_inventory(expiry_date);

-- 10. CNF ORDERS / INVOICES
CREATE TABLE IF NOT EXISTS cnf_orders (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_number VARCHAR(50) UNIQUE,
    cnf_company_id UUID NOT NULL REFERENCES cnf_companies(id),
    warehouse_id   UUID REFERENCES warehouses(id),
    order_date     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount   DECIMAL(12,2) DEFAULT 0,
    payment_status VARCHAR(10) DEFAULT 'pending' CHECK (payment_status IN ('pending','paid','partial','cancelled')),
    order_status   VARCHAR(20) DEFAULT 'pending' CHECK (order_status IN ('pending','confirmed','dispatched','delivered','cancelled')),
    notes          TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_cnf_orders_cnf ON cnf_orders(cnf_company_id);

-- 11. CNF ORDER ITEMS
CREATE TABLE IF NOT EXISTS cnf_order_items (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cnf_order_id UUID NOT NULL REFERENCES cnf_orders(id) ON DELETE CASCADE,
    product_id   UUID REFERENCES products(id),
    product_name VARCHAR(300) NOT NULL,
    quantity     INT NOT NULL DEFAULT 1,
    unit_price   DECIMAL(10,2) NOT NULL,
    total        DECIMAL(12,2) NOT NULL
);

-- 12. SALESMEN
CREATE TABLE IF NOT EXISTS salesmen (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID REFERENCES users(id) ON DELETE SET NULL,
    name            VARCHAR(100) NOT NULL,
    mobile          VARCHAR(15),
    email           VARCHAR(100),
    distributor_id  UUID REFERENCES vendors(id) ON DELETE SET NULL,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_salesmen_distributor ON salesmen(distributor_id);

-- 13. SALESMAN ORDERS (links existing orders to salesmen)
CREATE TABLE IF NOT EXISTS salesman_orders (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id    UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    salesman_id UUID NOT NULL REFERENCES salesmen(id),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (order_id, salesman_id)
);
CREATE INDEX IF NOT EXISTS idx_salesman_orders_salesman ON salesman_orders(salesman_id);

-- Seed demo manufacturer
INSERT INTO manufacturers (name, contact_name, email, mobile, city, state)
VALUES ('Drithi Agro Manufacturer', 'Demo Contact', 'mfr@drithiagro.com', '9000000099', 'Bangalore', 'Karnataka')
ON CONFLICT DO NOTHING;
