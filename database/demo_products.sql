-- ============================================================
-- DRITHI AGRO - DEMO PRODUCTS
-- ============================================================

-- First, we need to ensure we have a vendor to attach products to
-- If the admin user doesn't have a vendor record, we'll create one
INSERT INTO vendors (user_id, vendor_id_code, business_name, owner_name, mobile, email, address, city, state, pincode, status, is_verified, total_products, total_orders, total_revenue, rating)
SELECT id, 'VENDOR001', 'Drithi Agro Demo Store', 'Demo Owner', '+919999999999', 'demo@drithiagro.com', '123 Demo Street', 'Demo City', 'Demo State', '123456', 'APPROVED', 1, 0, 0, 0.00, 4.5
FROM users WHERE role = 'ADMIN' AND NOT EXISTS (SELECT 1 FROM vendors WHERE user_id IN (SELECT id FROM users WHERE role = 'ADMIN'));

-- Get the vendor ID for the demo vendor
SET @vendor_id = (SELECT id FROM vendors WHERE business_name = 'Drithi Agro Demo Store' LIMIT 1);

-- Insert demo products for each category
-- Irrigation subcategories
INSERT INTO products (vendor_id, category_id, name, slug, description, mrp, selling_price, stock_qty, unit, hsn_code, gst_rate, is_active, avg_rating, review_count, sold_count) VALUES
(@vendor_id, 4, 'Impact Sprinkler 1/2" with Base', 'impact-sprinkler-1-2-base', 'High-quality impact sprinkler for efficient irrigation coverage', 450, 350, 100, 'Piece', '84242100', 18, 1, 4.2, 15, 45),
(@vendor_id, 4, 'Pop-up Sprinkler 4" Full Circle', 'pop-up-sprinkler-4-full-circle', 'Pop-up sprinkler for lawn and garden irrigation', 280, 220, 80, 'Piece', '84242100', 18, 1, 4.0, 12, 38),
(@vendor_id, 5, 'Drip Irrigation Pipe 16mm 100m', 'drip-irrigation-pipe-16mm-100m', 'UV stabilized drip irrigation pipe for efficient water distribution', 850, 680, 50, 'Roll', '39174000', 18, 1, 4.5, 28, 72),
(@vendor_id, 5, 'Drip Emitter Button 2 LPH', 'drip-emitter-button-2-lph', 'Button dripper for precise water delivery to plants', 15, 12, 500, 'Piece', '39174000', 18, 1, 4.3, 45, 156),
(@vendor_id, 6, 'PVC Pipe 32mm Class 1', 'pvc-pipe-32mm-class-1', 'High strength PVC pipe for irrigation systems', 120, 95, 200, 'Piece', '39174000', 18, 1, 4.1, 18, 89),
(@vendor_id, 6, 'UPVC Elbow 32mm', 'upvc-elbow-32mm', 'UPVC elbow fitting for pipe connections', 25, 20, 300, 'Piece', '39174000', 18, 1, 4.0, 22, 134),
(@vendor_id, 7, 'Complete Drip Irrigation Kit for 1 Acre', 'complete-drip-irrigation-kit-1-acre', 'All-in-one drip irrigation kit for 1 acre land', 4500, 3800, 20, 'Kit', '39174000', 18, 1, 4.7, 35, 48),
(@vendor_id, 8, 'Rain Pipe Lay Flat Tube 100m', 'rain-pipe-lay-flat-tube-100m', 'Lay flat rain pipe for surface irrigation', 650, 520, 60, 'Roll', '39174000', 18, 1, 4.4, 19, 67);

-- Gardening subcategories
INSERT INTO products (vendor_id, category_id, name, slug, description, mrp, selling_price, stock_qty, unit, hsn_code, gst_rate, is_active, avg_rating, review_count, sold_count) VALUES
(@vendor_id, 9, 'Garden Trowel with Wooden Handle', 'garden-trowel-wooden-handle', 'Ergonomic garden trowel for digging and planting', 180, 145, 150, 'Piece', '82044000', 18, 1, 4.3, 28, 94),
(@vendor_id, 9, 'Pruning Shears 8" Stainless Steel', 'pruning-shears-8-stainless', 'Sharp pruning shears for cutting branches and stems', 320, 260, 80, 'Piece', '82014000', 18, 1, 4.5, 42, 127),
(@vendor_id, 10, 'Hand Pressure Sprayer 2L', 'hand-pressure-sprayer-2l', 'Manual pressure sprayer for pesticides and fertilizers', 450, 380, 60, 'Piece', '84242100', 18, 1, 4.2, 35, 89),
(@vendor_id, 10, 'Battery Operated Sprayer 16L', 'battery-operated-sprayer-16l', 'Rechargeable battery sprayer for large gardens', 2800, 2400, 25, 'Piece', '84242100', 18, 1, 4.6, 18, 52),
(@vendor_id, 11, 'Manual Lawn Mower 14"', 'manual-lawn-mower-14', 'Push-type lawn mower for small lawns', 3500, 2900, 15, 'Piece', '84331100', 18, 1, 4.4, 12, 38),
(@vendor_id, 14, 'Tomato Seeds Hybrid F1', 'tomato-seeds-hybrid-f1', 'High-yield hybrid tomato seeds packet', 199, 150, 200, 'Packet', '12091000', 5, 1, 4.5, 67, 234),
(@vendor_id, 14, 'Chilli Seeds G4', 'chilli-seeds-g4', 'Premium quality chilli seeds for high yield', 175, 140, 180, 'Packet', '12091000', 5, 1, 4.3, 54, 189),
(@vendor_id, 15, 'NPK 19:19:19 Water Soluble 1kg', 'npk-19-19-19-water-soluble-1kg', 'Balanced NPK fertilizer for all crops', 220, 185, 100, 'Packet', '31059000', 5, 1, 4.6, 89, 312),
(@vendor_id, 15, 'Urea 45kg', 'urea-45kg', 'Nitrogen fertilizer for vegetative growth', 1200, 1080, 50, 'Bag', '31010000', 5, 1, 4.4, 45, 178),
(@vendor_id, 16, 'Neem Oil 10000 PPM 1L', 'neem-oil-10000-ppm-1l', 'Organic neem oil bio-pesticide', 950, 780, 80, 'Bottle', '38089100', 18, 1, 4.5, 38, 145),
(@vendor_id, 18, 'Coco Peat Block 5kg', 'coco-peat-block-5kg', 'Compressed coco peat block for potting mix', 280, 230, 120, 'Block', '53031000', 18, 1, 4.4, 56, 198),
(@vendor_id, 21, 'Grow Bag 12" Black', 'grow-bag-12-black', 'UV stabilized grow bag for container gardening', 45, 38, 300, 'Piece', '39269000', 18, 1, 4.2, 41, 167),
(@vendor_id, 23, 'Marigold Seeds Orange', 'marigold-seeds-orange', 'Beautiful orange marigold flower seeds', 85, 65, 250, 'Packet', '12091000', 5, 1, 4.3, 33, 134);

-- Cattle & Bird Care subcategories
INSERT INTO products (vendor_id, category_id, name, slug, description, mrp, selling_price, stock_qty, unit, hsn_code, gst_rate, is_active, avg_rating, review_count, sold_count) VALUES
(@vendor_id, 26, 'Maize Fodder Seeds 5kg', 'maize-fodder-seeds-5kg', 'High-yield maize seeds for green fodder', 450, 380, 80, 'Packet', '12091000', 5, 1, 4.4, 28, 112),
(@vendor_id, 26, 'Oats Fodder Seeds 5kg', 'oats-fodder-seeds-5kg', 'Nutritious oats seeds for cattle fodder', 380, 320, 70, 'Packet', '12091000', 5, 1, 4.3, 24, 98),
(@vendor_id, 27, 'Mineral Mixture 1kg', 'mineral-mixture-1kg', 'Balanced mineral supplement for livestock', 180, 150, 150, 'Packet', '23099000', 12, 1, 4.5, 45, 167),
(@vendor_id, 27, 'Calcium Carbonate 1kg', 'calcium-carbonate-1kg', 'Calcium supplement for strong bones', 120, 100, 200, 'Packet', '28365000', 12, 1, 4.2, 32, 134),
(@vendor_id, 28, 'Bird Feed Mix 2kg', 'bird-feed-mix-2kg', 'Nutritious mixed feed for pet birds', 280, 240, 90, 'Packet', '23099000', 12, 1, 4.4, 38, 145),
(@vendor_id, 28, 'Sparrow Food 1kg', 'sparrow-food-1kg', 'Specialized food for sparrows and small birds', 150, 125, 120, 'Packet', '23099000', 12, 1, 4.3, 29, 118),
(@vendor_id, 30, 'Aqua Care Pro 500ml', 'aqua-care-pro-500ml', 'Water conditioner for fish ponds', 450, 380, 60, 'Bottle', '38089100', 18, 1, 4.5, 22, 89),
(@vendor_id, 32, 'Goat Feed Pellets 25kg', 'goat-feed-pellets-25kg', 'Complete nutrition pellets for goats', 1800, 1600, 40, 'Bag', '23099000', 12, 1, 4.6, 18, 72),
(@vendor_id, 33, 'Poultry Grower Feed 25kg', 'poultry-grower-feed-25kg', 'Balanced grower feed for poultry', 1650, 1450, 45, 'Bag', '23099000', 12, 1, 4.4, 25, 98),
(@vendor_id, 36, 'Liver Tonic 500ml', 'liver-tonic-500ml', 'Herbal liver tonic for animals', 380, 320, 70, 'Bottle', '30049000', 12, 1, 4.3, 31, 124);

-- Insert product images for all products
INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
SELECT p.id, 'https://images.unsplash.com/photo-1574943320219-553eb213f72d?w=400&q=80', 1, 0
FROM products p
WHERE NOT EXISTS (SELECT 1 FROM product_images WHERE product_id = p.id);

-- Update vendor total products count
UPDATE vendors SET total_products = (SELECT COUNT(*) FROM products WHERE vendor_id = @vendor_id AND is_active = 1)
WHERE id = @vendor_id;
