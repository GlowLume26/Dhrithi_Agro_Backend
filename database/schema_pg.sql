-- ============================================================
-- DRITHI AGRO MARKETPLACE - PostgreSQL DATABASE SCHEMA
-- ============================================================

CREATE DATABASE drithi_agro;
\c drithi_agro;

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 1. USERS
CREATE TABLE users (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    first_name    VARCHAR(100) NOT NULL DEFAULT '',
    last_name     VARCHAR(100) NOT NULL DEFAULT '',
    email         VARCHAR(100) UNIQUE,
    mobile        VARCHAR(15) UNIQUE,
    password_hash VARCHAR(255) NOT NULL DEFAULT '',
    role          VARCHAR(10)  NOT NULL DEFAULT 'customer' CHECK (role IN ('customer','vendor','admin')),
    is_active     BOOLEAN DEFAULT TRUE,
    gender        VARCHAR(10) CHECK (gender IN ('male','female','other')),
    occupation    VARCHAR(100),
    farm_size     VARCHAR(50),
    primary_crop  VARCHAR(50),
    dob           DATE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. OTP CACHE (managed by PHP)
CREATE TABLE otp_cache (
    identifier  VARCHAR(100) PRIMARY KEY,
    otp_code    VARCHAR(6)   NOT NULL,
    purpose     VARCHAR(20)  NOT NULL DEFAULT 'LOGIN',
    expires_at  BIGINT       NOT NULL,
    is_used     BOOLEAN      NOT NULL DEFAULT FALSE
);

-- 3. CUSTOMERS
CREATE TABLE customers (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id       UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    customer_code VARCHAR(30) UNIQUE,
    total_orders  INT DEFAULT 0,
    total_spent   DECIMAL(15,2) DEFAULT 0.00,
    loyalty_points INT DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. CUSTOMER ADDRESSES
CREATE TABLE customer_addresses (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id   UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    full_name     VARCHAR(100) NOT NULL,
    mobile        VARCHAR(15)  NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city          VARCHAR(100) NOT NULL,
    state         VARCHAR(100) NOT NULL,
    pincode       VARCHAR(10)  NOT NULL,
    country       VARCHAR(50)  DEFAULT 'India',
    address_type  VARCHAR(10)  DEFAULT 'home' CHECK (address_type IN ('home','work','other')),
    is_default    BOOLEAN DEFAULT FALSE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. VENDORS
CREATE TABLE vendors (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id          UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    vendor_code      VARCHAR(30) UNIQUE,
    business_name    VARCHAR(200) NOT NULL,
    owner_name       VARCHAR(100) NOT NULL,
    email            VARCHAR(100) NOT NULL,
    mobile           VARCHAR(15)  NOT NULL,
    gst_number       VARCHAR(20)  UNIQUE,
    pan_number       VARCHAR(15)  UNIQUE,
    address          TEXT,
    city             VARCHAR(100),
    state            VARCHAR(100),
    pincode          VARCHAR(10),
    status           VARCHAR(10)  DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected','suspended')),
    is_verified      BOOLEAN DEFAULT FALSE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. VENDOR DOCUMENTS
CREATE TABLE vendor_documents (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vendor_id     UUID NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    document_type VARCHAR(30)  NOT NULL CHECK (document_type IN ('AADHAAR','PAN','GST_CERTIFICATE','BUSINESS_LOGO','OTHER')),
    document_url  VARCHAR(500) NOT NULL,
    is_verified   BOOLEAN DEFAULT FALSE,
    uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. CATEGORIES
CREATE TABLE categories (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon        VARCHAR(10),
    image_url   VARCHAR(500),
    parent_id   UUID NULL REFERENCES categories(id) ON DELETE SET NULL,
    sort_order  INT DEFAULT 0,
    is_active   BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. BRANDS
CREATE TABLE brands (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name       VARCHAR(100) NOT NULL,
    slug       VARCHAR(100) NOT NULL UNIQUE,
    logo_url   VARCHAR(500),
    is_active  BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. PRODUCTS
CREATE TABLE products (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vendor_id     UUID NOT NULL REFERENCES vendors(id),
    category_id   UUID NOT NULL REFERENCES categories(id),
    brand_id      UUID REFERENCES brands(id),
    name          VARCHAR(300) NOT NULL,
    slug          VARCHAR(300) NOT NULL UNIQUE,
    description   TEXT,
    sku           VARCHAR(100) UNIQUE,
    mrp           DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    stock_qty     INT NOT NULL DEFAULT 0,
    unit          VARCHAR(20)  DEFAULT 'Piece',
    hsn_code      VARCHAR(20),
    gst_rate      DECIMAL(5,2) DEFAULT 5.00,
    avg_rating    DECIMAL(3,2) DEFAULT 0.00,
    review_count  INT DEFAULT 0,
    sold_count    INT DEFAULT 0,
    is_active     BOOLEAN DEFAULT TRUE,
    is_featured   BOOLEAN DEFAULT FALSE,
    search_vector TSVECTOR,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_products_category ON products (category_id);
CREATE INDEX idx_products_vendor   ON products (vendor_id);
CREATE INDEX idx_products_price    ON products (selling_price);
CREATE INDEX idx_products_fts      ON products USING GIN (search_vector);

-- Auto-update search_vector
CREATE OR REPLACE FUNCTION products_search_vector_update() RETURNS TRIGGER AS $$
BEGIN
    NEW.search_vector := to_tsvector('english', NEW.name || ' ' || COALESCE(NEW.description, ''));
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_products_search_vector
BEFORE INSERT OR UPDATE ON products
FOR EACH ROW EXECUTE FUNCTION products_search_vector_update();

-- 10. INVENTORY
CREATE TABLE inventory (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id          UUID NOT NULL UNIQUE REFERENCES products(id) ON DELETE CASCADE,
    current_stock       INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 10,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. PRODUCT IMAGES
CREATE TABLE product_images (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    image_url  VARCHAR(500) NOT NULL,
    alt_text   VARCHAR(200),
    sort_order INT DEFAULT 0,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 12. CART
CREATE TABLE cart (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    product_id  UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    quantity    INT NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (customer_id, product_id)
);

-- 13. WISHLIST
CREATE TABLE wishlist (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    product_id  UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (customer_id, product_id)
);

-- 14. ORDERS
CREATE TABLE orders (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_number    VARCHAR(30)   NOT NULL UNIQUE,
    customer_id     UUID NOT NULL REFERENCES customers(id),
    address_id      UUID REFERENCES customer_addresses(id),
    total_amount    DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    shipping_charge DECIMAL(10,2) DEFAULT 0.00,
    final_amount    DECIMAL(12,2) NOT NULL,
    payment_status  VARCHAR(10)  DEFAULT 'pending' CHECK (payment_status IN ('pending','paid','failed','refunded')),
    order_status    VARCHAR(20)  DEFAULT 'placed'  CHECK (order_status  IN ('placed','confirmed','packed','shipped','out_for_delivery','delivered','cancelled','returned')),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_orders_customer ON orders (customer_id);
CREATE INDEX idx_orders_status   ON orders (order_status);

-- 15. ORDER ITEMS
CREATE TABLE order_items (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id    UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id  UUID NOT NULL REFERENCES products(id),
    quantity    INT          NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    total       DECIMAL(12,2) NOT NULL
);

-- 16. ORDER STATUS HISTORY
CREATE TABLE order_status_history (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id   UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    status     VARCHAR(20) NOT NULL,
    remarks    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 17. PAYMENTS
CREATE TABLE payments (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id            UUID NOT NULL UNIQUE REFERENCES orders(id),
    payment_method      VARCHAR(20) NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    payment_status      VARCHAR(10) DEFAULT 'pending' CHECK (payment_status IN ('pending','paid','failed','refunded')),
    razorpay_order_id   VARCHAR(100),
    razorpay_payment_id VARCHAR(100),
    razorpay_signature  VARCHAR(500),
    paid_at             TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 18. COUPONS
CREATE TABLE coupons (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code                VARCHAR(30)   NOT NULL UNIQUE,
    title               VARCHAR(200)  NOT NULL,
    discount_type       VARCHAR(12)   NOT NULL CHECK (discount_type IN ('percentage','flat')),
    discount_value      DECIMAL(10,2) NOT NULL,
    minimum_order_amount DECIMAL(10,2) DEFAULT 0.00,
    max_discount        DECIMAL(10,2),
    usage_limit         INT,
    used_count          INT DEFAULT 0,
    valid_from          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valid_to            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active           BOOLEAN DEFAULT TRUE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 19. COUPON USAGE
CREATE TABLE coupon_usage (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    coupon_id   UUID NOT NULL REFERENCES coupons(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    order_id    UUID NOT NULL REFERENCES orders(id),
    used_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 20. REVIEWS
CREATE TABLE reviews (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id  UUID NOT NULL REFERENCES products(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    rating      SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT,
    is_approved BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (product_id, customer_id)
);

-- 21. NOTIFICATIONS
CREATE TABLE notifications (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title      VARCHAR(200) NOT NULL,
    message    TEXT NOT NULL,
    is_read    BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_user ON notifications (user_id, is_read);

-- 22. BANNERS
CREATE TABLE banners (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title      VARCHAR(200),
    image_url  VARCHAR(500) NOT NULL,
    link_url   VARCHAR(500),
    sort_order INT DEFAULT 0,
    is_active  BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user
INSERT INTO users (first_name, last_name, email, mobile, password_hash, role)
VALUES ('Admin', 'Drithi', 'admin@drithiagro.com', '9000000000',
        '$2y$10$placeholder_hash_change_on_first_login', 'admin');

-- Categories (parent)
INSERT INTO categories (id, name, slug, icon, sort_order) VALUES
(gen_random_uuid(), 'Irrigation',         'irrigation',       '💧', 1),
(gen_random_uuid(), 'Gardening',          'gardening',        '🌿', 2),
(gen_random_uuid(), 'Cattle & Bird Care', 'cattle-bird-care', '🐄', 3);

-- Brands
INSERT INTO brands (name, slug) VALUES
('Syngenta India',          'syngenta'),
('Bayer CropScience',       'bayer'),
('IFFCO',                   'iffco'),
('Jain Irrigation',         'jain-irrigation'),
('Coromandel International','coromandel');

-- Coupons
INSERT INTO coupons (code, title, discount_type, discount_value, minimum_order_amount, valid_from, valid_to) VALUES
('AGRO10',  '10% off on all orders',       'percentage', 10.00, 299.00, NOW(), NOW() + INTERVAL '30 days'),
('FIRST50', 'Flat ₹50 off on first order', 'flat',       50.00, 199.00, NOW(), NOW() + INTERVAL '60 days');
