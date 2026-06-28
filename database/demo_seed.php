<?php
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/helpers/helpers.php';

$p = new PDO('pgsql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME, DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function uid() { return OtpHelper::uuid(); }
function q($p, $sql, $params=[]) { $s=$p->prepare($sql); $s->execute($params); return $s; }
function get($p,$sql,$params=[]) { $s=$p->prepare($sql); $s->execute($params); return $s->fetch(PDO::FETCH_ASSOC); }

$log = [];

try {
    // ── ROLES & PERMISSIONS ──────────────────────────────────────
    $roleIds = [];
    foreach(['admin','vendor','customer','owner'] as $r){
        $id=uid();
        q($p,"INSERT INTO roles(id,role_name) VALUES(?,?) ON CONFLICT(role_name) DO UPDATE SET id=roles.id",[$id,$r]);
        $roleIds[$r] = get($p,"SELECT id FROM roles WHERE role_name=?",[$r])['id'];
    }
    $permIds = [];
    foreach(['manage_users','manage_products','manage_orders','manage_vendors','view_reports','manage_coupons'] as $perm){
        $id=uid();
        q($p,"INSERT INTO permissions(id,permission_name) VALUES(?,?) ON CONFLICT(permission_name) DO UPDATE SET id=permissions.id",[$id,$perm]);
        $permIds[$perm] = get($p,"SELECT id FROM permissions WHERE permission_name=?",[$perm])['id'];
    }
    foreach($permIds as $pid2){
        q($p,"INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?) ON CONFLICT(role_id,permission_id) DO NOTHING",[$roleIds['admin'],$pid2]);
    }
    $log[]="Roles & permissions seeded";

    // ── USERS ────────────────────────────────────────────────────
    $hash = password_hash('Demo@123', PASSWORD_DEFAULT);
    $usersData = [
        ['u1','Ravi','Kumar','ravi.kumar@gmail.com','9876543210','customer'],
        ['u2','Priya','Sharma','priya.sharma@gmail.com','9876543211','customer'],
        ['u3','Amit','Patel','amit.patel@gmail.com','9876543212','customer'],
        ['u4','Sunita','Verma','sunita.verma@gmail.com','9876543213','customer'],
        ['u5','Deepak','Singh','deepak.singh@gmail.com','9876543214','customer'],
        ['u6','Jain','Agro','jain@jainagro.com','9800000001','vendor'],
        ['u7','Krishna','Seeds','info@krishnaseeds.com','9800000002','vendor'],
        ['u8','AgroTech','Solutions','contact@agrotech.com','9800000003','vendor'],
    ];
    $uids = [];
    foreach($usersData as $u){
        $id=uid();
        q($p,"INSERT INTO users(id,first_name,last_name,email,mobile,password_hash,role,is_active) VALUES(?,?,?,?,?,?,?,TRUE) ON CONFLICT(email) DO NOTHING",
            [$id,$u[1],$u[2],$u[3],$u[4],$hash,$u[5]]);
        $uids[$u[0]] = get($p,"SELECT id FROM users WHERE email=?",[$u[3]])['id'];
    }
    $log[]="Users seeded: ".count($usersData);

    // ── CUSTOMERS ────────────────────────────────────────────────
    $cids = [];
    foreach(['u1','u2','u3','u4','u5'] as $uk){
        $id=uid();
        q($p,"INSERT INTO customers(id,user_id,customer_code,total_orders,total_spent,loyalty_points) VALUES(?,?,?,?,?,?) ON CONFLICT(user_id) DO NOTHING",
            [$id,$uids[$uk],'CUS-'.strtoupper(substr($id,0,8)),rand(1,10),(float)rand(500,10000),rand(10,500)]);
        $cids[$uk] = get($p,"SELECT id FROM customers WHERE user_id=?",[$uids[$uk]])['id'];
    }
    $log[]="Customers seeded: ".count($cids);

    // ── CUSTOMER ADDRESSES ───────────────────────────────────────
    $addrIds = [];
    $addrData = [
        ['u1','Ravi Kumar','9876543210','12 Farm Road Sector 4','','Pune','Maharashtra','411001','home'],
        ['u2','Priya Sharma','9876543211','45 Green Valley','Near Temple','Nashik','Maharashtra','422001','home'],
        ['u3','Amit Patel','9876543212','78 Kisan Nagar','','Surat','Gujarat','395001','work'],
        ['u4','Sunita Verma','9876543213','23 Agri Colony Block B','','Lucknow','Uttar Pradesh','226001','home'],
        ['u5','Deepak Singh','9876543214','56 Rural Township','','Jaipur','Rajasthan','302001','home'],
    ];
    foreach($addrData as $a){
        $existing = get($p,"SELECT id FROM customer_addresses WHERE customer_id=? AND address_type=?",[$cids[$a[0]],$a[8]]);
        if($existing){ $addrIds[$a[0]]=$existing['id']; continue; }
        $id=uid(); $addrIds[$a[0]]=$id;
        q($p,"INSERT INTO customer_addresses(id,customer_id,full_name,mobile,address_line1,address_line2,city,state,pincode,country,address_type,is_default) VALUES(?,?,?,?,?,?,?,?,?,?,?,TRUE)",
            [$id,$cids[$a[0]],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6],$a[7],'India',$a[8]]);
    }
    $log[]="Customer addresses seeded";

    // ── VENDORS ──────────────────────────────────────────────────
    $vids = [];
    $vendorData = [
        ['u6','Jain Irrigation Systems','Rajesh Jain','jain@jainagro.com','9800000001','22AAACJ1234A1ZK','AAACJ1234A','Pune Maharashtra','Pune','Maharashtra','411001'],
        ['u7','Krishna Seeds & Agro','Mohan Krishna','info@krishnaseeds.com','9800000002','22AAACK5678B1ZP','AAACK5678B','Hyderabad Telangana','Hyderabad','Telangana','500001'],
        ['u8','AgroTech Solutions','Suresh Mehta','contact@agrotech.com','9800000003','22AAACM9012C1ZR','AAACM9012C','Ahmedabad Gujarat','Ahmedabad','Gujarat','380001'],
    ];
    foreach($vendorData as $v){
        $id=uid();
        q($p,"INSERT INTO vendors(id,user_id,vendor_code,business_name,owner_name,email,mobile,gst_number,pan_number,address,city,state,pincode,status,is_verified) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'approved',TRUE) ON CONFLICT(user_id) DO NOTHING",
            [$id,$uids[$v[0]],'DA-VND-'.strtoupper(substr($id,0,6)),$v[1],$v[2],$v[3],$v[4],$v[5],$v[6],$v[7],$v[8],$v[9],$v[10]]);
        $vids[$v[0]] = get($p,"SELECT id FROM vendors WHERE user_id=?",[$uids[$v[0]]])['id'];
    }
    $log[]="Vendors seeded: ".count($vids);

    // ── VENDOR BANK ACCOUNTS ─────────────────────────────────────
    foreach($vids as $uk=>$vid){
        $exists = get($p,"SELECT id FROM vendor_bank_accounts WHERE vendor_id=?",[$vid]);
        if(!$exists){
            q($p,"INSERT INTO vendor_bank_accounts(id,vendor_id,account_holder_name,bank_name,account_number,ifsc_code,is_primary) VALUES(?,?,?,?,?,?,TRUE)",
                [uid(),$vid,'Account Holder','State Bank of India','3'.rand(10000000,99999999),'SBIN00'.rand(10000,99999)]);
        }
    }
    $log[]="Vendor bank accounts seeded";

    // ── VENDOR DOCUMENTS ─────────────────────────────────────────
    foreach($vids as $uk=>$vid){
        foreach(['GST','PAN','AADHAAR'] as $dtype){
            $exists = get($p,"SELECT id FROM vendor_documents WHERE vendor_id=? AND document_type=?",[$vid,$dtype]);
            if(!$exists){
                q($p,"INSERT INTO vendor_documents(id,vendor_id,document_type,document_number,document_url,verification_status) VALUES(?,?,?,?,?,?)",
                    [uid(),$vid,$dtype,$dtype.'-'.rand(100000,999999),'https://placehold.co/400x300?text='.$dtype,'approved']);
            }
        }
    }
    $log[]="Vendor documents seeded";

    // ── CATEGORIES MAP ───────────────────────────────────────────
    $catMap=[];
    foreach($p->query("SELECT id,slug FROM categories")->fetchAll(PDO::FETCH_ASSOC) as $c) $catMap[$c['slug']]=$c['id'];

    // ── PRODUCTS ─────────────────────────────────────────────────
    $productsData = [
        ['p1','u6','sprinkler','Rain Bird Sprinkler System','High efficiency rotating sprinkler for large farms',2500,1899,150,'Set','SKU-SPK-001',true],
        ['p2','u6','drip-irrigation-accessories','Drip Irrigation Lateral Pipe 16mm','UV stabilized LDPE pipe 1000m roll',3200,2650,80,'Roll','SKU-DRP-001',false],
        ['p3','u6','pipe-fitting','PVC Agricultural Pipe 2 inch','High pressure resistant 6m length',450,360,200,'Piece','SKU-PVC-001',false],
        ['p4','u6','drip-irrigation-kit','Complete Drip Kit 1 Acre','Full drip irrigation kit for 1 acre',8500,6999,40,'Set','SKU-DKT-001',true],
        ['p5','u7','gardening-seeds','Tomato Hybrid Seeds 10g','F1 hybrid high yield disease resistant',180,149,500,'Pack','SKU-TOM-001',true],
        ['p6','u7','gardening-seeds','Brinjal Seeds Premium 5g','Long purple variety 90-day crop',120,99,400,'Pack','SKU-BRJ-001',false],
        ['p7','u7','gardening-fertilizer','NPK 19-19-19 Fertilizer 1kg','Water soluble all crop stages',320,275,300,'Kg','SKU-NPK-001',true],
        ['p8','u7','gardening-fertilizer','Urea 46% 50kg Bag','High nitrogen for vegetative growth',1400,1250,100,'Bag','SKU-URE-001',false],
        ['p9','u7','gardening-pesticides','Cypermethrin 25% EC 500ml','Broad spectrum insecticide',380,320,250,'Bottle','SKU-CYP-001',false],
        ['p10','u8','spray-pumps','Battery Sprayer 16L','Rechargeable 4 spray modes ergonomic',2800,2299,60,'Piece','SKU-BSP-001',true],
        ['p11','u8','gardening-tools','Soil Testing Kit','Tests NPK and pH 50 tests',1200,980,120,'Kit','SKU-STK-001',false],
        ['p12','u8','grow-bag','HDPE Grow Bags 12x12 inch 10pcs','UV stabilized 500gsm reusable',350,280,200,'Pack','SKU-GRB-001',false],
        ['p13','u8','coco-peat','Cocopeat Block 5kg','Compressed expands 8x RHP certified',299,249,180,'Piece','SKU-CCP-001',false],
        ['p14','u7','fodder-seed','Bajra Napier Hybrid Fodder Seeds 1kg','High biomass perennial 4 cuts per year',450,380,150,'Kg','SKU-BNH-001',true],
        ['p15','u8','bird-food','Premium Bird Seed Mix 5kg','Sunflower millet wheat blend',550,449,90,'Bag','SKU-BRD-001',false],
    ];
    $pids=[];
    foreach($productsData as $prod){
        $id=uid();
        $catId=$catMap[$prod[2]] ?? array_values($catMap)[0];
        $vid=$vids[$prod[1]];
        $slug=strtolower(preg_replace('/[^a-z0-9]+/','-',$prod[3])).'-'.substr($id,0,6);
        q($p,"INSERT INTO products(id,vendor_id,category_id,name,slug,description,sku,mrp,selling_price,stock_qty,unit,gst_rate,avg_rating,review_count,sold_count,is_active,is_featured) VALUES(?,?,?,?,?,?,?,?,?,?,?,5.0,?,?,?,TRUE,?) ON CONFLICT(sku) DO NOTHING",
            [$id,$vid,$catId,$prod[3],$slug,$prod[4],$prod[9],(float)$prod[5],(float)$prod[6],(int)$prod[7],$prod[8],round(3.5+lcg_value()*1.5,1),rand(5,50),rand(10,200),(int)$prod[10]]);
        $pids[$prod[0]] = get($p,"SELECT id FROM products WHERE sku=?",[$prod[9]])['id'];
    }
    $log[]="Products seeded: ".count($pids);

    // ── PRODUCT IMAGES ───────────────────────────────────────────
    foreach($pids as $pk=>$pid){
        $exists = get($p,"SELECT id FROM product_images WHERE product_id=? AND is_primary=TRUE",[$pid]);
        if(!$exists){
            q($p,"INSERT INTO product_images(id,product_id,image_url,alt_text,sort_order,is_primary) VALUES(?,?,?,?,0,TRUE)",
                [uid(),$pid,'https://placehold.co/600x400/4CAF50/white?text='.urlencode($pk),'Product Image']);
            q($p,"INSERT INTO product_images(id,product_id,image_url,alt_text,sort_order,is_primary) VALUES(?,?,?,?,1,FALSE)",
                [uid(),$pid,'https://placehold.co/600x400/2196F3/white?text='.urlencode($pk.'+2'),'Product Image 2']);
        }
    }
    $log[]="Product images seeded";

    // ── PRODUCT VARIANTS ─────────────────────────────────────────
    foreach(['p5','p6','p7'] as $pk){
        foreach([['250g',180,150],['500g',320,270],['1kg',580,490]] as $v){
            $sku='VAR-'.strtoupper($pk).'-'.$v[0];
            $exists=get($p,"SELECT id FROM product_variants WHERE sku=?",[$sku]);
            if(!$exists) q($p,"INSERT INTO product_variants(id,product_id,variant_name,sku,mrp,selling_price,stock_qty) VALUES(?,?,?,?,?,?,?)",
                [uid(),$pids[$pk],$v[0],$sku,(float)$v[1],(float)$v[2],rand(20,100)]);
        }
    }
    $log[]="Product variants seeded";

    // ── INVENTORY ────────────────────────────────────────────────
    foreach($pids as $pid){
        q($p,"INSERT INTO inventory(id,product_id,current_stock,reserved_stock,low_stock_threshold) VALUES(?,?,?,?,?) ON CONFLICT(product_id) DO NOTHING",
            [uid(),$pid,rand(50,200),rand(0,10),10]);
    }
    $log[]="Inventory seeded";

    // ── ORDERS + ITEMS + PAYMENTS + STATUS HISTORY ───────────────
    $orderStatuses=['placed','confirmed','packed','shipped','delivered'];
    $payMethods=['cod','upi','card','net_banking'];
    $orderIds=[]; $orderItemIds=[];
    $custList=array_keys($cids);
    $prodList=array_keys($pids);

    for($i=1;$i<=15;$i++){
        $oid    = uid();
        $uk     = $custList[($i-1) % count($custList)];
        $cid    = $cids[$uk];
        $status = $orderStatuses[($i-1) % count($orderStatuses)];
        $isPaid = in_array($status,['shipped','delivered']);
        $method = $payMethods[($i-1) % count($payMethods)];
        $daysAgo= rand(1,60);
        $onum   = 'DA-2025-'.str_pad($i,5,'0',STR_PAD_LEFT);

        $exists = get($p,"SELECT id FROM orders WHERE order_number=?",[$onum]);
        if($exists){ $orderIds["o$i"]=$exists['id']; continue; }

        $selProds=array_slice($prodList,($i-1)%count($prodList),rand(1,3));
        $items=[]; $subtotal=0;
        foreach($selProds as $pk){
            $pr=get($p,"SELECT selling_price FROM products WHERE id=?",[$pids[$pk]]);
            $qty=rand(1,3); $price=(float)$pr['selling_price'];
            $items[]=[$pids[$pk],$qty,$price]; $subtotal+=$price*$qty;
        }
        $shipping=(float)($subtotal>=499?0:49);
        $final=(float)($subtotal+$shipping);

        q($p,"INSERT INTO orders(id,order_number,customer_id,total_amount,discount_amount,shipping_charge,final_amount,payment_status,order_status,created_at) VALUES(?,?,?,?,0,?,?,?,?,NOW()-INTERVAL '{$daysAgo} days')",
            [$oid,$onum,$cid,(float)$subtotal,$shipping,$final,$isPaid?'paid':'pending',$status]);
        $orderIds["o$i"]=$oid;

        foreach($items as $item){
            $iid=uid(); $orderItemIds[]=$iid;
            q($p,"INSERT INTO order_items(id,order_id,product_id,quantity,price,total) VALUES(?,?,?,?,?,?)",
                [$iid,$oid,$item[0],$item[1],(float)$item[2],(float)($item[2]*$item[1])]);
            q($p,"UPDATE products SET sold_count=sold_count+? WHERE id=?",[$item[1],$item[0]]);
        }

        $txn=$isPaid?'TXN'.rand(100000,999999):null;
        $paidAt=$isPaid?date('Y-m-d H:i:s',strtotime("-{$daysAgo} days")):null;
        q($p,"INSERT INTO payments(id,order_id,payment_method,transaction_id,amount,payment_status,paid_at) VALUES(?,?,?,?,?,?,?)",
            [uid(),$oid,$method,$txn,$final,$isPaid?'success':'pending',$paidAt]);

        q($p,"INSERT INTO order_status_history(id,order_id,status,remarks,changed_at) VALUES(?,?,?,?,NOW()-INTERVAL '{$daysAgo} days')",
            [uid(),$oid,'placed','Order placed successfully']);
        if($status!='placed'){
            q($p,"INSERT INTO order_status_history(id,order_id,status,remarks,changed_at) VALUES(?,?,?,?,NOW()-INTERVAL '".($daysAgo-1)." days')",
                [uid(),$oid,'confirmed','Order confirmed']);
        }
    }
    $log[]="Orders seeded: 15";

    // ── REVIEWS ──────────────────────────────────────────────────
    $reviewTexts=['Excellent product very useful for my farm','Good quality fast delivery','Value for money highly recommend','Works as described satisfied','Average quality but decent price'];
    $ri=0;
    foreach($pids as $pk=>$pid){
        $uk=$custList[$ri%count($custList)]; $ri++;
        $exists=get($p,"SELECT id FROM reviews WHERE product_id=? AND customer_id=?",[$pid,$cids[$uk]]);
        if(!$exists) q($p,"INSERT INTO reviews(id,product_id,customer_id,rating,review_text,is_approved) VALUES(?,?,?,?,?,TRUE)",
            [uid(),$pid,$cids[$uk],rand(3,5),$reviewTexts[array_rand($reviewTexts)]]);
    }
    $log[]="Reviews seeded";

    // ── CART ─────────────────────────────────────────────────────
    foreach(['u1'=>'p3','u2'=>'p7','u3'=>'p10','u4'=>'p5','u5'=>'p12'] as $uk=>$pk){
        q($p,"INSERT INTO cart(id,customer_id,product_id,quantity) VALUES(?,?,?,?) ON CONFLICT(customer_id,product_id) DO NOTHING",
            [uid(),$cids[$uk],$pids[$pk],rand(1,3)]);
    }
    $log[]="Cart seeded";

    // ── WISHLIST ─────────────────────────────────────────────────
    foreach(['u1'=>'p5','u2'=>'p1','u3'=>'p12','u4'=>'p8','u5'=>'p10'] as $uk=>$pk){
        q($p,"INSERT INTO wishlist(id,customer_id,product_id) VALUES(?,?,?) ON CONFLICT(customer_id,product_id) DO NOTHING",
            [uid(),$cids[$uk],$pids[$pk]]);
    }
    $log[]="Wishlist seeded";

    // ── BANNERS ──────────────────────────────────────────────────
    if(!get($p,"SELECT id FROM banners LIMIT 1")){
        foreach([
            ['Kharif Season Sale 2025','https://placehold.co/1200x400/FF6B35/white?text=Kharif+Season+Sale','/pages/categories.html','sale'],
            ['Premium Drip Irrigation','https://placehold.co/1200x400/4CAF50/white?text=Drip+Irrigation+Deals','/pages/categories.html','category'],
            ['New Seeds Collection','https://placehold.co/1200x400/2196F3/white?text=New+Seeds','/pages/categories.html','product'],
        ] as $b){
            q($p,"INSERT INTO banners(id,title,image_url,redirect_url,target_type,start_date,end_date,is_active) VALUES(?,?,?,?,?,NOW(),NOW()+INTERVAL '90 days',TRUE)",
                [uid(),$b[0],$b[1],$b[2],$b[3]]);
        }
    }
    $log[]="Banners seeded";

    // ── NOTIFICATIONS ────────────────────────────────────────────
    foreach($uids as $uk=>$uid2){
        q($p,"INSERT INTO notifications(id,user_id,title,message,is_read) VALUES(?,?,?,?,FALSE)",
            [uid(),$uid2,'Welcome to Drithi Agro!','Explore our wide range of agricultural products.']);
    }
    foreach($orderIds as $ok=>$oid){
        $row=get($p,"SELECT customer_id,order_number FROM orders WHERE id=?",[$oid]);
        $cust=get($p,"SELECT user_id FROM customers WHERE id=?",[$row['customer_id']]);
        q($p,"INSERT INTO notifications(id,user_id,title,message,is_read) VALUES(?,?,?,?,FALSE)",
            [uid(),$cust['user_id'],'Order Placed','Your order '.$row['order_number'].' placed successfully.']);
    }
    $log[]="Notifications seeded";

    // ── VENDOR COMMISSIONS ───────────────────────────────────────
    $usedItems=array_slice($orderItemIds,0,count($vids));
    foreach($vids as $uk=>$vid){
        $iid=array_shift($usedItems); if(!$iid) break;
        q($p,"INSERT INTO vendor_commissions(id,vendor_id,order_item_id,order_amount,commission_percentage,commission_amount) VALUES(?,?,?,?,?,?)",
            [uid(),$vid,$iid,(float)rand(500,5000),10.0,(float)rand(50,500)]);
    }
    $log[]="Vendor commissions seeded";

    // ── VENDOR PAYOUTS ───────────────────────────────────────────
    foreach($vids as $uk=>$vid){
        q($p,"INSERT INTO vendor_payouts(id,vendor_id,payout_amount,payout_status,transaction_reference,payout_date) VALUES(?,?,?,?,?,NOW()-INTERVAL '15 days')",
            [uid(),$vid,(float)rand(1000,10000),'completed','PAY-'.rand(100000,999999)]);
    }
    $log[]="Vendor payouts seeded";

    // ── STOCK MOVEMENTS ──────────────────────────────────────────
    foreach(array_slice(array_values($pids),0,5) as $pid){
        q($p,"INSERT INTO stock_movements(id,product_id,movement_type,quantity,notes) VALUES(?,?,?,?,?)",
            [uid(),$pid,'purchase',rand(50,200),'Initial stock purchase']);
        q($p,"INSERT INTO stock_movements(id,product_id,movement_type,quantity,notes) VALUES(?,?,?,?,?)",
            [uid(),$pid,'sale',rand(5,30),'Order fulfillment']);
    }
    $log[]="Stock movements seeded";

    // ── COUPON USAGE ─────────────────────────────────────────────
    $couponRow=get($p,"SELECT id FROM coupons WHERE code='AGRO10'");
    if($couponRow && !empty($orderIds)){
        q($p,"INSERT INTO coupon_usage(id,coupon_id,customer_id,order_id) VALUES(?,?,?,?)",
            [uid(),$couponRow['id'],array_values($cids)[0],array_values($orderIds)[0]]);
    }
    $log[]="Coupon usage seeded";

    // ── AUDIT LOGS ───────────────────────────────────────────────
    $adminRow=get($p,"SELECT id FROM users WHERE email='admin@drithiagro.com'");
    if($adminRow){
        foreach(['vendor_approved','product_reviewed','order_updated'] as $action){
            q($p,"INSERT INTO audit_logs(id,user_id,action,entity_type,entity_id,new_data) VALUES(?,?,?,?,?,?::jsonb)",
                [uid(),$adminRow['id'],$action,'system',uid(),'{"status":"done"}']);
        }
    }
    $log[]="Audit logs seeded";

    // ── FINAL COUNTS ─────────────────────────────────────────────
    $log[]=""; $log[]="=== FINAL TABLE COUNTS ===";
    foreach(['users','customers','vendors','products','categories','orders','order_items','payments',
             'reviews','cart','wishlist','banners','notifications','inventory','coupons',
             'product_images','product_variants','vendor_documents','stock_movements','audit_logs'] as $t){
        $c=$p->query("SELECT COUNT(*) AS c FROM $t")->fetch(PDO::FETCH_ASSOC)['c'];
        $log[]="  $t: $c rows";
    }
    $log[]=""; $log[]="✅ ALL DEMO DATA SEEDED SUCCESSFULLY!";
    $log[]=""; $log[]="Test login credentials:";
    $log[]="  Admin  — email: admin@drithiagro.com  (OTP login)";
    $log[]="  Customer — email: ravi.kumar@gmail.com  mobile: 9876543210";
    $log[]="  Vendor — email: jain@jainagro.com  mobile: 9800000001";

} catch(Exception $e){
    $log[]="ERROR: ".$e->getMessage();
}

echo implode(PHP_EOL,$log).PHP_EOL;
