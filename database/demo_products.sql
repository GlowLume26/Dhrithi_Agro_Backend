-- ============================================================
-- DRITHI AGRO - DEMO PRODUCTS (PostgreSQL)
-- ============================================================

DO $$
DECLARE
    v_admin_id   UUID;
    v_vendor_id  UUID;
    v_irr_id     UUID;
    v_gard_id    UUID;
    v_cattle_id  UUID;
BEGIN

-- Get or create admin user
SELECT id INTO v_admin_id FROM users WHERE role = 'admin' LIMIT 1;

-- Create vendor for demo store if not exists
INSERT INTO vendors (user_id, vendor_code, business_name, owner_name, mobile, email, address, city, state, pincode, status, is_verified)
SELECT v_admin_id, 'VENDOR001', 'Drithi Agro Demo Store', 'Demo Owner', '9999999999', 'demo@drithiagro.com', '123 Demo Street', 'Demo City', 'Demo State', '123456', 'approved', TRUE
WHERE v_admin_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM vendors WHERE vendor_code = 'VENDOR001');

SELECT id INTO v_vendor_id FROM vendors WHERE vendor_code = 'VENDOR001';

-- Get category IDs
SELECT id INTO v_irr_id    FROM categories WHERE slug = 'irrigation'       LIMIT 1;
SELECT id INTO v_gard_id   FROM categories WHERE slug = 'gardening'        LIMIT 1;
SELECT id INTO v_cattle_id FROM categories WHERE slug = 'cattle-bird-care' LIMIT 1;

-- Insert products only if vendor exists
IF v_vendor_id IS NOT NULL AND v_irr_id IS NOT NULL THEN

    INSERT INTO products (vendor_id, category_id, name, slug, description, mrp, selling_price, stock_qty, unit, hsn_code, gst_rate, is_active, avg_rating, review_count, sold_count) VALUES
    (v_vendor_id, v_irr_id, 'Impact Sprinkler 1/2" with Base',    'impact-sprinkler-1-2-base',        'High-quality impact sprinkler for efficient irrigation coverage', 450,  350,  100, 'Piece', '84242100', 18, TRUE, 4.2, 15, 45),
    (v_vendor_id, v_irr_id, 'Pop-up Sprinkler 4" Full Circle',    'pop-up-sprinkler-4-full-circle',   'Pop-up sprinkler for lawn and garden irrigation',                 280,  220,  80,  'Piece', '84242100', 18, TRUE, 4.0, 12, 38),
    (v_vendor_id, v_irr_id, 'Drip Irrigation Pipe 16mm 100m',     'drip-irrigation-pipe-16mm-100m',   'UV stabilized drip irrigation pipe for efficient water distribution', 850, 680, 50, 'Roll', '39174000', 18, TRUE, 4.5, 28, 72),
    (v_vendor_id, v_irr_id, 'Drip Emitter Button 2 LPH',          'drip-emitter-button-2-lph',        'Button dripper for precise water delivery to plants',             15,   12,   500, 'Piece', '39174000', 18, TRUE, 4.3, 45, 156),
    (v_vendor_id, v_irr_id, 'PVC Pipe 32mm Class 1',              'pvc-pipe-32mm-class-1',            'High strength PVC pipe for irrigation systems',                   120,  95,   200, 'Piece', '39174000', 18, TRUE, 4.1, 18, 89),
    (v_vendor_id, v_irr_id, 'UPVC Elbow 32mm',                    'upvc-elbow-32mm',                  'UPVC elbow fitting for pipe connections',                         25,   20,   300, 'Piece', '39174000', 18, TRUE, 4.0, 22, 134),
    (v_vendor_id, v_irr_id, 'Complete Drip Irrigation Kit 1 Acre','complete-drip-irrigation-kit-1-acre','All-in-one drip irrigation kit for 1 acre land',               4500, 3800, 20,  'Kit',   '39174000', 18, TRUE, 4.7, 35, 48),
    (v_vendor_id, v_irr_id, 'Rain Pipe Lay Flat Tube 100m',       'rain-pipe-lay-flat-tube-100m',     'Lay flat rain pipe for surface irrigation',                       650,  520,  60,  'Roll',  '39174000', 18, TRUE, 4.4, 19, 67)
    ON CONFLICT (slug) DO NOTHING;

END IF;

IF v_vendor_id IS NOT NULL AND v_gard_id IS NOT NULL THEN

    INSERT INTO products (vendor_id, category_id, name, slug, description, mrp, selling_price, stock_qty, unit, hsn_code, gst_rate, is_active, avg_rating, review_count, sold_count) VALUES
    (v_vendor_id, v_gard_id, 'Garden Trowel with Wooden Handle',  'garden-trowel-wooden-handle',      'Ergonomic garden trowel for digging and planting',                180,  145,  150, 'Piece',  '82044000', 18, TRUE, 4.3, 28, 94),
    (v_vendor_id, v_gard_id, 'Pruning Shears 8" Stainless Steel', 'pruning-shears-8-stainless',       'Sharp pruning shears for cutting branches and stems',             320,  260,  80,  'Piece',  '82014000', 18, TRUE, 4.5, 42, 127),
    (v_vendor_id, v_gard_id, 'Hand Pressure Sprayer 2L',          'hand-pressure-sprayer-2l',         'Manual pressure sprayer for pesticides and fertilizers',          450,  380,  60,  'Piece',  '84242100', 18, TRUE, 4.2, 35, 89),
    (v_vendor_id, v_gard_id, 'Battery Operated Sprayer 16L',      'battery-operated-sprayer-16l',     'Rechargeable battery sprayer for large gardens',                  2800, 2400, 25,  'Piece',  '84242100', 18, TRUE, 4.6, 18, 52),
    (v_vendor_id, v_gard_id, 'Tomato Seeds Hybrid F1',            'tomato-seeds-hybrid-f1',           'High-yield hybrid tomato seeds packet',                           199,  150,  200, 'Packet', '12091000', 5,  TRUE, 4.5, 67, 234),
    (v_vendor_id, v_gard_id, 'Chilli Seeds G4',                   'chilli-seeds-g4',                  'Premium quality chilli seeds for high yield',                     175,  140,  180, 'Packet', '12091000', 5,  TRUE, 4.3, 54, 189),
    (v_vendor_id, v_gard_id, 'NPK 19:19:19 Water Soluble 1kg',   'npk-19-19-19-water-soluble-1kg',   'Balanced NPK fertilizer for all crops',                           220,  185,  100, 'Packet', '31059000', 5,  TRUE, 4.6, 89, 312),
    (v_vendor_id, v_gard_id, 'Urea 45kg',                         'urea-45kg',                        'Nitrogen fertilizer for vegetative growth',                       1200, 1080, 50,  'Bag',    '31010000', 5,  TRUE, 4.4, 45, 178),
    (v_vendor_id, v_gard_id, 'Neem Oil 10000 PPM 1L',             'neem-oil-10000-ppm-1l',            'Organic neem oil bio-pesticide',                                  950,  780,  80,  'Bottle', '38089100', 18, TRUE, 4.5, 38, 145),
    (v_vendor_id, v_gard_id, 'Coco Peat Block 5kg',               'coco-peat-block-5kg',              'Compressed coco peat block for potting mix',                      280,  230,  120, 'Block',  '53031000', 18, TRUE, 4.4, 56, 198),
    (v_vendor_id, v_gard_id, 'Grow Bag 12" Black',                'grow-bag-12-black',                'UV stabilized grow bag for container gardening',                  45,   38,   300, 'Piece',  '39269000', 18, TRUE, 4.2, 41, 167),
    (v_vendor_id, v_gard_id, 'Marigold Seeds Orange',             'marigold-seeds-orange',            'Beautiful orange marigold flower seeds',                          85,   65,   250, 'Packet', '12091000', 5,  TRUE, 4.3, 33, 134)
    ON CONFLICT (slug) DO NOTHING;

END IF;

IF v_vendor_id IS NOT NULL AND v_cattle_id IS NOT NULL THEN

    INSERT INTO products (vendor_id, category_id, name, slug, description, mrp, selling_price, stock_qty, unit, hsn_code, gst_rate, is_active, avg_rating, review_count, sold_count) VALUES
    (v_vendor_id, v_cattle_id, 'Maize Fodder Seeds 5kg',    'maize-fodder-seeds-5kg',    'High-yield maize seeds for green fodder',          450,  380,  80,  'Packet', '12091000', 5,  TRUE, 4.4, 28, 112),
    (v_vendor_id, v_cattle_id, 'Oats Fodder Seeds 5kg',     'oats-fodder-seeds-5kg',     'Nutritious oats seeds for cattle fodder',          380,  320,  70,  'Packet', '12091000', 5,  TRUE, 4.3, 24, 98),
    (v_vendor_id, v_cattle_id, 'Mineral Mixture 1kg',       'mineral-mixture-1kg',       'Balanced mineral supplement for livestock',        180,  150,  150, 'Packet', '23099000', 12, TRUE, 4.5, 45, 167),
    (v_vendor_id, v_cattle_id, 'Calcium Carbonate 1kg',     'calcium-carbonate-1kg',     'Calcium supplement for strong bones',              120,  100,  200, 'Packet', '28365000', 12, TRUE, 4.2, 32, 134),
    (v_vendor_id, v_cattle_id, 'Bird Feed Mix 2kg',         'bird-feed-mix-2kg',         'Nutritious mixed feed for pet birds',              280,  240,  90,  'Packet', '23099000', 12, TRUE, 4.4, 38, 145),
    (v_vendor_id, v_cattle_id, 'Sparrow Food 1kg',          'sparrow-food-1kg',          'Specialized food for sparrows and small birds',    150,  125,  120, 'Packet', '23099000', 12, TRUE, 4.3, 29, 118),
    (v_vendor_id, v_cattle_id, 'Aqua Care Pro 500ml',       'aqua-care-pro-500ml',       'Water conditioner for fish ponds',                 450,  380,  60,  'Bottle', '38089100', 18, TRUE, 4.5, 22, 89),
    (v_vendor_id, v_cattle_id, 'Goat Feed Pellets 25kg',    'goat-feed-pellets-25kg',    'Complete nutrition pellets for goats',             1800, 1600, 40,  'Bag',    '23099000', 12, TRUE, 4.6, 18, 72),
    (v_vendor_id, v_cattle_id, 'Poultry Grower Feed 25kg',  'poultry-grower-feed-25kg',  'Balanced grower feed for poultry',                 1650, 1450, 45,  'Bag',    '23099000', 12, TRUE, 4.4, 25, 98),
    (v_vendor_id, v_cattle_id, 'Liver Tonic 500ml',         'liver-tonic-500ml',         'Herbal liver tonic for animals',                   380,  320,  70,  'Bottle', '30049000', 12, TRUE, 4.3, 31, 124)
    ON CONFLICT (slug) DO NOTHING;

END IF;

-- Add placeholder image for all products that have none
INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
SELECT p.id, 'https://images.unsplash.com/photo-1574943320219-553eb213f72d?w=400&q=80', TRUE, 0
FROM products p
WHERE NOT EXISTS (SELECT 1 FROM product_images WHERE product_id = p.id);

END $$;
