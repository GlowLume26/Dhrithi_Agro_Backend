-- ============================================================
-- DRITHI AGRO — Products Import for Render DB
-- ============================================================

-- Step 1: Vendor users
INSERT INTO users (id,first_name,last_name,email,mobile,password_hash,role,is_active) VALUES
('fadb8ce2-7dd6-4142-8192-164dbc958fa4','Rajesh','Jain','jain@jainagro.com','9800000001','$2y$12$placeholder','vendor',true),
('80a2221a-5d59-43e1-9ccf-0da1b5507d75','Mohan','Krishna','info@krishnaseeds.com','9800000002','$2y$12$placeholder','vendor',true),
('c4857ac0-0491-49f9-825d-a9b511169993','Suresh','Mehta','contact@agrotech.com','9800000003','$2y$12$placeholder','vendor',true)
ON CONFLICT (id) DO NOTHING;

-- Step 2: Vendors
INSERT INTO vendors (id,user_id,vendor_code,business_name,owner_name,email,mobile,gst_number,pan_number,city,state,pincode,status,is_verified) VALUES
('24517e8e-564e-4aee-ac50-c06669257413','fadb8ce2-7dd6-4142-8192-164dbc958fa4','DA-VND-24517E','Jain Irrigation Systems','Rajesh Jain','jain@jainagro.com','9800000001','22AAACJ1234A1ZK','AAACJ1234A','Pune','Maharashtra','411001','approved',true),
('256dbb4a-a35e-4015-87f0-eda0c3f56117','80a2221a-5d59-43e1-9ccf-0da1b5507d75','DA-VND-256DBB','Krishna Seeds & Agro','Mohan Krishna','info@krishnaseeds.com','9800000002','22AAACK5678B1ZP','AAACK5678B','Hyderabad','Telangana','500001','approved',true),
('48fc16a2-f324-4b5a-86c0-df2c067040f0','c4857ac0-0491-49f9-825d-a9b511169993','DA-VND-48FC16','AgroTech Solutions','Suresh Mehta','contact@agrotech.com','9800000003','22AAACM9012C1ZR','AAACM9012C','Ahmedabad','Gujarat','380001','approved',true)
ON CONFLICT (id) DO NOTHING;

-- Step 3: Products (using Render category slugs)
INSERT INTO products (id,vendor_id,category_id,name,slug,description,sku,mrp,selling_price,stock_qty,unit,gst_rate,avg_rating,review_count,sold_count,is_active,is_featured) VALUES
('a02f57ea-d6c6-469a-8017-43e79dc09736','24517e8e-564e-4aee-ac50-c06669257413',(SELECT id FROM categories WHERE slug='irrigation'),'Rain Bird Sprinkler System','rain-bird-sprinkler-system','High efficiency rotating sprinkler for large farms','SKU-SPK-001',2500.00,1899.00,150,'Set',5.00,3.8,39,84,true,true),
('4e3443a7-1fc4-4f22-9da0-a9e131088276','24517e8e-564e-4aee-ac50-c06669257413',(SELECT id FROM categories WHERE slug='irrigation'),'Drip Irrigation Lateral Pipe 16mm','drip-irrigation-lateral-pipe-16mm','UV stabilized LDPE pipe 1000m roll','SKU-DRP-001',3200.00,2650.00,80,'Roll',5.00,4.2,15,170,true,false),
('c1b80bb7-f834-4593-a00c-a0962f55b9b3','24517e8e-564e-4aee-ac50-c06669257413',(SELECT id FROM categories WHERE slug='irrigation'),'PVC Agricultural Pipe 2 inch','pvc-agricultural-pipe-2-inch','High pressure resistant 6m length','SKU-PVC-001',450.00,360.00,200,'Piece',5.00,4.6,44,17,true,false),
('db912075-29e0-40e2-895c-0cd0cac73fa0','24517e8e-564e-4aee-ac50-c06669257413',(SELECT id FROM categories WHERE slug='irrigation'),'Complete Drip Kit 1 Acre','complete-drip-kit-1-acre','Full drip irrigation kit for 1 acre','SKU-DKT-001',8500.00,6999.00,40,'Set',5.00,4.8,11,131,true,true),
('8333f7a8-eca3-4744-8306-56448e08425c','256dbb4a-a35e-4015-87f0-eda0c3f56117',(SELECT id FROM categories WHERE slug='gardening'),'Tomato Hybrid Seeds 10g','tomato-hybrid-seeds-10g','F1 hybrid high yield disease resistant','SKU-TOM-001',180.00,149.00,499,'Pack',5.00,4.3,19,192,true,true),
('6ec574de-3a88-4cce-9f4a-96b7cadf6df4','256dbb4a-a35e-4015-87f0-eda0c3f56117',(SELECT id FROM categories WHERE slug='gardening'),'Brinjal Seeds Premium 5g','brinjal-seeds-premium-5g','Long purple variety 90-day crop','SKU-BRJ-001',120.00,99.00,400,'Pack',5.00,4.0,30,124,true,false),
('6909ac93-07db-4b78-9fce-5de7e66e07d1','256dbb4a-a35e-4015-87f0-eda0c3f56117',(SELECT id FROM categories WHERE slug='gardening'),'NPK 19-19-19 Fertilizer 1kg','npk-19-19-19-fertilizer-1kg','Water soluble all crop stages','SKU-NPK-001',320.00,275.00,300,'Kg',5.00,4.1,44,69,true,true),
('b7e554c8-a706-4d05-8f0f-fc4dbaef7411','256dbb4a-a35e-4015-87f0-eda0c3f56117',(SELECT id FROM categories WHERE slug='gardening'),'Urea 46% 50kg Bag','urea-46-50kg-bag','High nitrogen for vegetative growth','SKU-URE-001',1400.00,1250.00,100,'Bag',5.00,4.2,48,78,true,false),
('06bda882-8078-4765-a24e-4a5d5fb3d507','256dbb4a-a35e-4015-87f0-eda0c3f56117',(SELECT id FROM categories WHERE slug='gardening'),'Cypermethrin 25% EC 500ml','cypermethrin-25-ec-500ml','Broad spectrum insecticide','SKU-CYP-001',380.00,320.00,250,'Bottle',5.00,4.3,42,186,true,false),
('f7954d7d-eb1b-4359-b69d-823b196378f4','48fc16a2-f324-4b5a-86c0-df2c067040f0',(SELECT id FROM categories WHERE slug='gardening'),'Battery Sprayer 16L','battery-sprayer-16l','Rechargeable 4 spray modes ergonomic','SKU-BSP-001',2800.00,2299.00,60,'Piece',5.00,4.4,38,151,true,true),
('54411763-0f5f-44a5-acf6-d1e9cff2781a','48fc16a2-f324-4b5a-86c0-df2c067040f0',(SELECT id FROM categories WHERE slug='gardening'),'Soil Testing Kit','soil-testing-kit','Tests NPK and pH 50 tests','SKU-STK-001',1200.00,980.00,120,'Kit',5.00,4.5,35,29,true,false),
('47e799e4-eac9-4f79-9d48-c8f1f4bc601e','48fc16a2-f324-4b5a-86c0-df2c067040f0',(SELECT id FROM categories WHERE slug='gardening'),'HDPE Grow Bags 12x12 inch 10pcs','hdpe-grow-bags-12x12-inch-10pcs','UV stabilized 500gsm reusable','SKU-GRB-001',350.00,280.00,200,'Pack',5.00,4.8,29,199,true,false),
('f87e07e1-d897-4dea-ba8c-c5540d8e678b','48fc16a2-f324-4b5a-86c0-df2c067040f0',(SELECT id FROM categories WHERE slug='gardening'),'Cocopeat Block 5kg','cocopeat-block-5kg','Compressed expands 8x RHP certified','SKU-CCP-001',299.00,249.00,180,'Piece',5.00,4.9,5,153,true,false),
('cf590771-9e45-4902-8b12-be7258141cb5','256dbb4a-a35e-4015-87f0-eda0c3f56117',(SELECT id FROM categories WHERE slug='cattle-bird-care'),'Bajra Napier Hybrid Fodder Seeds 1kg','bajra-napier-hybrid-fodder-seeds-1kg','High biomass perennial 4 cuts per year','SKU-BNH-001',450.00,380.00,150,'Kg',5.00,3.6,48,142,true,true),
('36a48b2a-542c-44a9-b533-aad0d90d2392','48fc16a2-f324-4b5a-86c0-df2c067040f0',(SELECT id FROM categories WHERE slug='cattle-bird-care'),'Premium Bird Seed Mix 5kg','premium-bird-seed-mix-5kg','Sunflower millet wheat blend','SKU-BRD-001',550.00,449.00,90,'Bag',5.00,4.3,48,49,true,false),
('d1468bbc-1521-491b-b39a-043feb05d0cc','48fc16a2-f324-4b5a-86c0-df2c067040f0',(SELECT id FROM categories WHERE slug='gardening'),'Organic Neem Cake Fertilizer 5kg','organic-neem-cake-fertilizer-5kg','100% organic neem cake fertilizer','NMC-ORG-5KG',450.00,380.00,100,'Piece',5.00,0,0,0,true,false)
ON CONFLICT (id) DO NOTHING;

-- Step 4: Product images
INSERT INTO product_images (product_id,image_url,alt_text,sort_order,is_primary) VALUES
('a02f57ea-d6c6-469a-8017-43e79dc09736','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80','Rain Bird Sprinkler',0,true),
('4e3443a7-1fc4-4f22-9da0-a9e131088276','https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=400&q=80','Drip Pipe',0,true),
('c1b80bb7-f834-4593-a00c-a0962f55b9b3','https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=400&q=80','PVC Pipe',0,true),
('db912075-29e0-40e2-895c-0cd0cac73fa0','https://images.unsplash.com/photo-1574943320219-553eb213f72d?w=400&q=80','Drip Kit',0,true),
('8333f7a8-eca3-4744-8306-56448e08425c','https://images.unsplash.com/photo-1592982537447-7440770cbfc9?w=400&q=80','Tomato Seeds',0,true),
('6ec574de-3a88-4cce-9f4a-96b7cadf6df4','https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=400&q=80','Brinjal Seeds',0,true),
('6909ac93-07db-4b78-9fce-5de7e66e07d1','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80','NPK Fertilizer',0,true),
('b7e554c8-a706-4d05-8f0f-fc4dbaef7411','https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=400&q=80','Urea Bag',0,true),
('06bda882-8078-4765-a24e-4a5d5fb3d507','https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=400&q=80','Cypermethrin',0,true),
('f7954d7d-eb1b-4359-b69d-823b196378f4','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80','Battery Sprayer',0,true),
('54411763-0f5f-44a5-acf6-d1e9cff2781a','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80','Soil Testing Kit',0,true),
('47e799e4-eac9-4f79-9d48-c8f1f4bc601e','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80','Grow Bags',0,true),
('f87e07e1-d897-4dea-ba8c-c5540d8e678b','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80','Cocopeat',0,true),
('cf590771-9e45-4902-8b12-be7258141cb5','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80','Fodder Seeds',0,true),
('36a48b2a-542c-44a9-b533-aad0d90d2392','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80','Bird Seed Mix',0,true),
('d1468bbc-1521-491b-b39a-043feb05d0cc','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80','Neem Cake',0,true)
ON CONFLICT DO NOTHING;

SELECT COUNT(*) AS total_products FROM products;
