-- ============================================================
-- APP SETTINGS TABLE + SEED
-- Run this once against your drithi_agro database
-- ============================================================

SET client_encoding = 'UTF8';

CREATE TABLE IF NOT EXISTS app_settings (
    key        VARCHAR(100) PRIMARY KEY,
    value      TEXT         NOT NULL,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO app_settings (key, value) VALUES
('delivery_free_threshold', '499'),
('delivery_charge',         '49'),
('page_limit',              '20'),
('inventory_limit',         '100'),
('business_name',           'Drithi Agro'),
('business_gst',            ''),
('business_address',        ''),
('business_phone',          ''),
('business_email',          'support@drithiagro.com'),
('payment_razorpay',        'true'),
('payment_stripe',          'false'),
('payment_cod',             'true'),
('shipping_flat_enabled',   'true'),
('shipping_flat_amount',    '49'),
('shipping_free_enabled',   'true'),
('shipping_free_threshold', '499'),
('tax_gst_rate',            '5'),
('tax_state_tax',           '0'),
('tax_extra_charges',       '0'),
('helpline_number',         '1800-XXX-XXXX'),
('hero_slides', '[
  {"bg":"linear-gradient(135deg,#1b5e20,#2e7d32)","img":"https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=1400&q=80","tag":"Kharif Season 2025","h2":"Grow More,\nEarn More","p":"Premium quality seeds, fertilizers & farm tools delivered right to your doorstep. Trusted by 5 lakh+ farmers.","btn1_label":"Shop Now","btn1_link":"/categories","btn2_label":"Join Free","btn2_link":"/login"},
  {"bg":"linear-gradient(135deg,#33691e,#558b2f)","img":"https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=1400&q=80","tag":"Organic Collection","h2":"Go Organic,\nGo Healthy","p":"100% certified organic fertilizers and bio-pesticides for healthier crops and better yields.","btn1_label":"Explore Organic","btn1_link":"/categories","btn2_label":"Learn More","btn2_link":"/about"},
  {"bg":"linear-gradient(135deg,#004d40,#00695c)","img":"https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1400&q=80","tag":"Farm Equipment","h2":"Smart Tools\nfor Smart Farmers","p":"Modern irrigation systems, sprayers, and precision farming tools to maximize your productivity.","btn1_label":"View Tools","btn1_link":"/categories","btn2_label":"Get Expert Advice","btn2_link":"/contact"}
]'),
('stats_bar', '[
  {"num":"5L+","label":"Happy Farmers"},
  {"num":"10,000+","label":"Products"},
  {"num":"500+","label":"Brands"},
  {"num":"28","label":"States Covered"},
  {"num":"24/7","label":"Expert Support"}
]'),
('offer_strip', '[
  "Free delivery on selective products",
  "New Season Products Now Available!",
  "Up to 40% OFF on selected items",
  "Buy 2 Get 1 Free on selected products"
]'),
('testimonials', '[
  {"init":"R","name":"Ramesh Patil","loc":"Nashik, Maharashtra","stars":5,"text":"Drithi Agro has changed the way I buy farm inputs. The quality is excellent and delivery is always on time. My yield has improved by 30% this season!"},
  {"init":"S","name":"Sunita Devi","loc":"Lucknow, UP","stars":5,"text":"The organic fertilizers I bought here are amazing. My vegetables are healthier and I am getting better prices in the market. Highly recommend!"},
  {"init":"K","name":"Krishnamurthy","loc":"Coimbatore, TN","stars":4,"text":"Very easy to use. I just enter my phone number and get OTP. The expert helpline is very helpful for crop disease queries."}
]'),
('org_team', '[
  {"init":"A","name":"Arjun Mehta","role":"CEO & Co-Founder","bio":"Visionary leader with 15+ years in agri-tech. Passionate about empowering Indian farmers through technology."},
  {"init":"P","name":"Priya Nair","role":"CTO & Co-Founder","bio":"Full-stack engineer and AI enthusiast. Built Drithi Agro platform from the ground up to serve millions of farmers."},
  {"init":"S","name":"Suresh Reddy","role":"Head of Agronomy","bio":"PhD in Agricultural Sciences. Guides farmers with expert crop advice and leads our agri-consultation team."},
  {"init":"M","name":"Meera Joshi","role":"Head of Operations","bio":"Supply chain expert ensuring on-time delivery across 28 states. Obsessed with farmer satisfaction."}
]'),
('indian_states', '["Andhra Pradesh","Arunachal Pradesh","Assam","Bihar","Chhattisgarh","Goa","Gujarat","Haryana","Himachal Pradesh","Jharkhand","Karnataka","Kerala","Madhya Pradesh","Maharashtra","Manipur","Meghalaya","Mizoram","Nagaland","Odisha","Punjab","Rajasthan","Sikkim","Tamil Nadu","Telangana","Tripura","Uttar Pradesh","Uttarakhand","West Bengal","Delhi","Jammu & Kashmir","Ladakh","Puducherry","Chandigarh"]')
ON CONFLICT (key) DO NOTHING;

-- Add start_date / end_date columns to banners if missing
ALTER TABLE banners ADD COLUMN IF NOT EXISTS start_date DATE;
ALTER TABLE banners ADD COLUMN IF NOT EXISTS end_date   DATE;

-- Add district column to customer_addresses if missing
ALTER TABLE customer_addresses ADD COLUMN IF NOT EXISTS district VARCHAR(100);

-- Extend users role check to include owner/superadmin
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
ALTER TABLE users ADD CONSTRAINT users_role_check
    CHECK (role IN ('customer','vendor','admin','owner','superadmin'));
