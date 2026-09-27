<?php
$pdo = new PDO(
    "pgsql:host=dpg-d9lgleu417fc73cs995g-a.singapore-postgres.render.com;port=5432;dbname=drithi_agro;sslmode=require",
    'drithi_agro_user', 'OZR7IMxb19gBoyq6g2MdaAydfKNeDtTQ',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
echo "Connected to Render DB\n";

// Step 1: Create vendor users
$vendorUsers = [
    ['id'=>'fadb8ce2-7dd6-4142-8192-164dbc958fa4','first_name'=>'Rajesh','last_name'=>'Jain','email'=>'jain@jainagro.com','mobile'=>'9800000001','role'=>'vendor'],
    ['id'=>'80a2221a-5d59-43e1-9ccf-0da1b5507d75','first_name'=>'Mohan','last_name'=>'Krishna','email'=>'info@krishnaseeds.com','mobile'=>'9800000002','role'=>'vendor'],
    ['id'=>'c4857ac0-0491-49f9-825d-a9b511169993','first_name'=>'Suresh','last_name'=>'Mehta','email'=>'contact@agrotech.com','mobile'=>'9800000003','role'=>'vendor'],
];
foreach ($vendorUsers as $u) {
    $pdo->prepare("INSERT INTO users (id,first_name,last_name,email,mobile,password_hash,role,is_active) VALUES (?,?,?,?,?,?,?,true) ON CONFLICT (id) DO NOTHING")
        ->execute([$u['id'],$u['first_name'],$u['last_name'],$u['email'],$u['mobile'],'$2y$12$placeholder','vendor']);
}
echo "Users inserted\n";

// Step 2: Insert vendors
$vendors = [
    ['id'=>'24517e8e-564e-4aee-ac50-c06669257413','user_id'=>'fadb8ce2-7dd6-4142-8192-164dbc958fa4','vendor_code'=>'DA-VND-24517E','business_name'=>'Jain Irrigation Systems','owner_name'=>'Rajesh Jain','email'=>'jain@jainagro.com','mobile'=>'9800000001','gst_number'=>'22AAACJ1234A1ZK','pan_number'=>'AAACJ1234A','city'=>'Pune','state'=>'Maharashtra','pincode'=>'411001'],
    ['id'=>'256dbb4a-a35e-4015-87f0-eda0c3f56117','user_id'=>'80a2221a-5d59-43e1-9ccf-0da1b5507d75','vendor_code'=>'DA-VND-256DBB','business_name'=>'Krishna Seeds & Agro','owner_name'=>'Mohan Krishna','email'=>'info@krishnaseeds.com','mobile'=>'9800000002','gst_number'=>'22AAACK5678B1ZP','pan_number'=>'AAACK5678B','city'=>'Hyderabad','state'=>'Telangana','pincode'=>'500001'],
    ['id'=>'48fc16a2-f324-4b5a-86c0-df2c067040f0','user_id'=>'c4857ac0-0491-49f9-825d-a9b511169993','vendor_code'=>'DA-VND-48FC16','business_name'=>'AgroTech Solutions','owner_name'=>'Suresh Mehta','email'=>'contact@agrotech.com','mobile'=>'9800000003','gst_number'=>'22AAACM9012C1ZR','pan_number'=>'AAACM9012C','city'=>'Ahmedabad','state'=>'Gujarat','pincode'=>'380001'],
];
foreach ($vendors as $v) {
    $pdo->prepare("INSERT INTO vendors (id,user_id,vendor_code,business_name,owner_name,email,mobile,gst_number,pan_number,city,state,pincode,status,is_verified) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'approved',true) ON CONFLICT (id) DO NOTHING")
        ->execute([$v['id'],$v['user_id'],$v['vendor_code'],$v['business_name'],$v['owner_name'],$v['email'],$v['mobile'],$v['gst_number'],$v['pan_number'],$v['city'],$v['state'],$v['pincode']]);
}
echo "Vendors inserted\n";

// Step 3: Get category IDs from Render DB
$cats = $pdo->query("SELECT id, name, slug FROM categories")->fetchAll(PDO::FETCH_ASSOC);
echo "Categories in Render DB:\n";
foreach ($cats as $c) echo "  {$c['id']} => {$c['name']} ({$c['slug']})\n";

// Step 4: Insert products using Render's category IDs
// Map: local category_id => category slug (to re-map to Render's IDs)
$catBySlug = [];
foreach ($cats as $c) $catBySlug[$c['slug']] = $c['id'];

// Get first available category as fallback
$fallbackCat = $cats[0]['id'] ?? null;

$products = [
    ['id'=>'a02f57ea-d6c6-469a-8017-43e79dc09736','vendor_id'=>'24517e8e-564e-4aee-ac50-c06669257413','cat_slug'=>'irrigation','name'=>'Rain Bird Sprinkler System','slug'=>'rain-bird-sprinkler-system','desc'=>'High efficiency rotating sprinkler for large farms','sku'=>'SKU-SPK-001','mrp'=>2500,'price'=>1899,'stock'=>150,'unit'=>'Set','gst'=>5,'rating'=>3.8,'reviews'=>39,'sold'=>84,'featured'=>true],
    ['id'=>'4e3443a7-1fc4-4f22-9da0-a9e131088276','vendor_id'=>'24517e8e-564e-4aee-ac50-c06669257413','cat_slug'=>'irrigation','name'=>'Drip Irrigation Lateral Pipe 16mm','slug'=>'drip-irrigation-lateral-pipe-16mm','desc'=>'UV stabilized LDPE pipe 1000m roll','sku'=>'SKU-DRP-001','mrp'=>3200,'price'=>2650,'stock'=>80,'unit'=>'Roll','gst'=>5,'rating'=>4.2,'reviews'=>15,'sold'=>170,'featured'=>false],
    ['id'=>'c1b80bb7-f834-4593-a00c-a0962f55b9b3','vendor_id'=>'24517e8e-564e-4aee-ac50-c06669257413','cat_slug'=>'irrigation','name'=>'PVC Agricultural Pipe 2 inch','slug'=>'pvc-agricultural-pipe-2-inch','desc'=>'High pressure resistant 6m length','sku'=>'SKU-PVC-001','mrp'=>450,'price'=>360,'stock'=>200,'unit'=>'Piece','gst'=>5,'rating'=>4.6,'reviews'=>44,'sold'=>17,'featured'=>false],
    ['id'=>'db912075-29e0-40e2-895c-0cd0cac73fa0','vendor_id'=>'24517e8e-564e-4aee-ac50-c06669257413','cat_slug'=>'irrigation','name'=>'Complete Drip Kit 1 Acre','slug'=>'complete-drip-kit-1-acre','desc'=>'Full drip irrigation kit for 1 acre','sku'=>'SKU-DKT-001','mrp'=>8500,'price'=>6999,'stock'=>40,'unit'=>'Set','gst'=>5,'rating'=>4.8,'reviews'=>11,'sold'=>131,'featured'=>true],
    ['id'=>'8333f7a8-eca3-4744-8306-56448e08425c','vendor_id'=>'256dbb4a-a35e-4015-87f0-eda0c3f56117','cat_slug'=>'gardening','name'=>'Tomato Hybrid Seeds 10g','slug'=>'tomato-hybrid-seeds-10g','desc'=>'F1 hybrid high yield disease resistant','sku'=>'SKU-TOM-001','mrp'=>180,'price'=>149,'stock'=>499,'unit'=>'Pack','gst'=>5,'rating'=>4.3,'reviews'=>19,'sold'=>192,'featured'=>true],
    ['id'=>'6ec574de-3a88-4cce-9f4a-96b7cadf6df4','vendor_id'=>'256dbb4a-a35e-4015-87f0-eda0c3f56117','cat_slug'=>'gardening','name'=>'Brinjal Seeds Premium 5g','slug'=>'brinjal-seeds-premium-5g','desc'=>'Long purple variety 90-day crop','sku'=>'SKU-BRJ-001','mrp'=>120,'price'=>99,'stock'=>400,'unit'=>'Pack','gst'=>5,'rating'=>4.0,'reviews'=>30,'sold'=>124,'featured'=>false],
    ['id'=>'6909ac93-07db-4b78-9fce-5de7e66e07d1','vendor_id'=>'256dbb4a-a35e-4015-87f0-eda0c3f56117','cat_slug'=>'gardening','name'=>'NPK 19-19-19 Fertilizer 1kg','slug'=>'npk-19-19-19-fertilizer-1kg','desc'=>'Water soluble all crop stages','sku'=>'SKU-NPK-001','mrp'=>320,'price'=>275,'stock'=>300,'unit'=>'Kg','gst'=>5,'rating'=>4.1,'reviews'=>44,'sold'=>69,'featured'=>true],
    ['id'=>'b7e554c8-a706-4d05-8f0f-fc4dbaef7411','vendor_id'=>'256dbb4a-a35e-4015-87f0-eda0c3f56117','cat_slug'=>'gardening','name'=>'Urea 46% 50kg Bag','slug'=>'urea-46-50kg-bag','desc'=>'High nitrogen for vegetative growth','sku'=>'SKU-URE-001','mrp'=>1400,'price'=>1250,'stock'=>100,'unit'=>'Bag','gst'=>5,'rating'=>4.2,'reviews'=>48,'sold'=>78,'featured'=>false],
    ['id'=>'06bda882-8078-4765-a24e-4a5d5fb3d507','vendor_id'=>'256dbb4a-a35e-4015-87f0-eda0c3f56117','cat_slug'=>'gardening','name'=>'Cypermethrin 25% EC 500ml','slug'=>'cypermethrin-25-ec-500ml','desc'=>'Broad spectrum insecticide','sku'=>'SKU-CYP-001','mrp'=>380,'price'=>320,'stock'=>250,'unit'=>'Bottle','gst'=>5,'rating'=>4.3,'reviews'=>42,'sold'=>186,'featured'=>false],
    ['id'=>'f7954d7d-eb1b-4359-b69d-823b196378f4','vendor_id'=>'48fc16a2-f324-4b5a-86c0-df2c067040f0','cat_slug'=>'gardening','name'=>'Battery Sprayer 16L','slug'=>'battery-sprayer-16l','desc'=>'Rechargeable 4 spray modes ergonomic','sku'=>'SKU-BSP-001','mrp'=>2800,'price'=>2299,'stock'=>60,'unit'=>'Piece','gst'=>5,'rating'=>4.4,'reviews'=>38,'sold'=>151,'featured'=>true],
    ['id'=>'54411763-0f5f-44a5-acf6-d1e9cff2781a','vendor_id'=>'48fc16a2-f324-4b5a-86c0-df2c067040f0','cat_slug'=>'gardening','name'=>'Soil Testing Kit','slug'=>'soil-testing-kit','desc'=>'Tests NPK and pH 50 tests','sku'=>'SKU-STK-001','mrp'=>1200,'price'=>980,'stock'=>120,'unit'=>'Kit','gst'=>5,'rating'=>4.5,'reviews'=>35,'sold'=>29,'featured'=>false],
    ['id'=>'47e799e4-eac9-4f79-9d48-c8f1f4bc601e','vendor_id'=>'48fc16a2-f324-4b5a-86c0-df2c067040f0','cat_slug'=>'gardening','name'=>'HDPE Grow Bags 12x12 inch 10pcs','slug'=>'hdpe-grow-bags-12x12-inch-10pcs','desc'=>'UV stabilized 500gsm reusable','sku'=>'SKU-GRB-001','mrp'=>350,'price'=>280,'stock'=>200,'unit'=>'Pack','gst'=>5,'rating'=>4.8,'reviews'=>29,'sold'=>199,'featured'=>false],
    ['id'=>'f87e07e1-d897-4dea-ba8c-c5540d8e678b','vendor_id'=>'48fc16a2-f324-4b5a-86c0-df2c067040f0','cat_slug'=>'gardening','name'=>'Cocopeat Block 5kg','slug'=>'cocopeat-block-5kg','desc'=>'Compressed expands 8x RHP certified','sku'=>'SKU-CCP-001','mrp'=>299,'price'=>249,'stock'=>180,'unit'=>'Piece','gst'=>5,'rating'=>4.9,'reviews'=>5,'sold'=>153,'featured'=>false],
    ['id'=>'cf590771-9e45-4902-8b12-be7258141cb5','vendor_id'=>'256dbb4a-a35e-4015-87f0-eda0c3f56117','cat_slug'=>'cattle-bird-care','name'=>'Bajra Napier Hybrid Fodder Seeds 1kg','slug'=>'bajra-napier-hybrid-fodder-seeds-1kg','desc'=>'High biomass perennial 4 cuts per year','sku'=>'SKU-BNH-001','mrp'=>450,'price'=>380,'stock'=>150,'unit'=>'Kg','gst'=>5,'rating'=>3.6,'reviews'=>48,'sold'=>142,'featured'=>true],
    ['id'=>'36a48b2a-542c-44a9-b533-aad0d90d2392','vendor_id'=>'48fc16a2-f324-4b5a-86c0-df2c067040f0','cat_slug'=>'cattle-bird-care','name'=>'Premium Bird Seed Mix 5kg','slug'=>'premium-bird-seed-mix-5kg','desc'=>'Sunflower millet wheat blend','sku'=>'SKU-BRD-001','mrp'=>550,'price'=>449,'stock'=>90,'unit'=>'Bag','gst'=>5,'rating'=>4.3,'reviews'=>48,'sold'=>49,'featured'=>false],
    ['id'=>'d1468bbc-1521-491b-b39a-043feb05d0cc','vendor_id'=>'48fc16a2-f324-4b5a-86c0-df2c067040f0','cat_slug'=>'gardening','name'=>'Organic Neem Cake Fertilizer 5kg','slug'=>'organic-neem-cake-fertilizer-5kg','desc'=>'100% organic neem cake fertilizer','sku'=>'NMC-ORG-5KG','mrp'=>450,'price'=>380,'stock'=>100,'unit'=>'Piece','gst'=>5,'rating'=>0,'reviews'=>0,'sold'=>0,'featured'=>false],
];

$stmt = $pdo->prepare("INSERT INTO products (id,vendor_id,category_id,name,slug,description,sku,mrp,selling_price,stock_qty,unit,gst_rate,avg_rating,review_count,sold_count,is_active,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,true,?) ON CONFLICT (id) DO NOTHING");

$ok = 0;
foreach ($products as $p) {
    $catId = $catBySlug[$p['cat_slug']] ?? $fallbackCat;
    $stmt->execute([$p['id'],$p['vendor_id'],$catId,$p['name'],$p['slug'],$p['desc'],$p['sku'],$p['mrp'],$p['price'],$p['stock'],$p['unit'],$p['gst'],$p['rating'],$p['reviews'],$p['sold'],$p['featured'] ? 'true' : 'false']);
    $ok++;
}
echo "Inserted $ok products\n";

// Step 5: Insert product images
$images = [
    ['a02f57ea-d6c6-469a-8017-43e79dc09736','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80'],
    ['4e3443a7-1fc4-4f22-9da0-a9e131088276','https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=400&q=80'],
    ['c1b80bb7-f834-4593-a00c-a0962f55b9b3','https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=400&q=80'],
    ['db912075-29e0-40e2-895c-0cd0cac73fa0','https://images.unsplash.com/photo-1574943320219-553eb213f72d?w=400&q=80'],
    ['8333f7a8-eca3-4744-8306-56448e08425c','https://images.unsplash.com/photo-1592982537447-7440770cbfc9?w=400&q=80'],
    ['6ec574de-3a88-4cce-9f4a-96b7cadf6df4','https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=400&q=80'],
    ['6909ac93-07db-4b78-9fce-5de7e66e07d1','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80'],
    ['b7e554c8-a706-4d05-8f0f-fc4dbaef7411','https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=400&q=80'],
    ['06bda882-8078-4765-a24e-4a5d5fb3d507','https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=400&q=80'],
    ['f7954d7d-eb1b-4359-b69d-823b196378f4','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80'],
    ['54411763-0f5f-44a5-acf6-d1e9cff2781a','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80'],
    ['47e799e4-eac9-4f79-9d48-c8f1f4bc601e','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80'],
    ['f87e07e1-d897-4dea-ba8c-c5540d8e678b','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80'],
    ['cf590771-9e45-4902-8b12-be7258141cb5','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80'],
    ['36a48b2a-542c-44a9-b533-aad0d90d2392','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80'],
    ['d1468bbc-1521-491b-b39a-043feb05d0cc','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80'],
];

$imgStmt = $pdo->prepare("INSERT INTO product_images (product_id,image_url,alt_text,sort_order,is_primary) VALUES (?,?,'Product Image',0,true) ON CONFLICT DO NOTHING");
foreach ($images as [$pid, $url]) $imgStmt->execute([$pid, $url]);
echo "Images inserted\n";

echo "\nDone! Verifying...\n";
$count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
echo "Total products in Render DB: $count\n";
