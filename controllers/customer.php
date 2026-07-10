<?php
// ====================================================================
// DRITHI AGRO — CUSTOMER CONTROLLER (UPDATED)
// File: backend/controllers/customer.php
// ====================================================================

// Force OPcache reset so Apache registers script updates immediately
if (function_exists('opcache_reset')) {
    opcache_reset();
}

// Disable browser caching for API calls
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();

// Fetch customer details (joined with users table to fetch new profile columns)
$customer = $db->fetchOne(
    "SELECT c.*, u.first_name, u.last_name, u.email, u.mobile,
            u.gender, u.occupation, u.farm_size, u.primary_crop, u.dob
     FROM customers c 
     JOIN users u ON c.user_id=u.id 
     WHERE c.user_id=?", 
    $auth['user_id']
);

if (!$customer) {
    Response::error('Customer not found', 404);
}

// Set full_name helper
$customer['full_name'] = trim($customer['first_name'] . ' ' . $customer['last_name']);

$section = $_GET['section'] ?? '';

if ($method === 'GET' && $section === 'profile') {
    $stats = $db->fetchOne(
        "SELECT
            COUNT(DISTINCT o.id)                                                  AS total_orders,
            COUNT(DISTINCT w.id)                                                  AS wishlist_count,
            COUNT(DISTINCT r.id)                                                  AS review_count,
            COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN o.final_amount ELSE 0 END),0) AS total_spent
         FROM customers c
         LEFT JOIN orders o   ON o.customer_id=c.id
         LEFT JOIN wishlist w ON w.customer_id=c.id
         LEFT JOIN reviews r  ON r.customer_id=c.id
         WHERE c.id=?", $customer['id']
    );
    Response::success('Profile fetched', array_merge($customer, ['stats' => $stats]));
}

if ($method === 'PUT' && $section === 'profile') {
    $db->begin();
    try {
        // Update profile fields on the Users Table
        $userFields = ['first_name', 'last_name', 'email', 'mobile', 'gender', 'dob', 'occupation', 'farm_size', 'primary_crop'];
        
        // Split full name if passed
        if (isset($body['full_name'])) {
            $parts = explode(' ', trim($body['full_name']), 2);
            $body['first_name'] = $parts[0];
            $body['last_name']  = $parts[1] ?? '';
        }
        
        $userSets = []; $userParams = [];
        foreach ($userFields as $f) {
            if (!array_key_exists($f, $body)) continue;
            
            $val = $body[$f];
            if ($f === 'email') {
                if (!empty($val) && !Validator::email($val)) {
                    Response::error('Invalid email address');
                }
                $val = !empty($val) ? trim($val) : null;
            } elseif ($f === 'mobile') {
                if (!empty($val) && !Validator::mobile($val)) {
                    Response::error('Invalid mobile number');
                }
                $val = !empty($val) ? trim($val) : null;
            } elseif ($f === 'gender') {
                $val = !empty($val) ? strtolower(trim($val)) : null;
                if ($val !== null && !in_array($val, ['male', 'female', 'other'])) {
                    Response::error('Invalid gender selection');
                }
            } elseif ($f === 'dob') {
                $val = !empty($val) ? trim($val) : null;
            } else {
                $val = ($val === '' || $val === null) ? null : trim($val);
            }
            
            $userSets[] = "$f=?";
            $userParams[] = $val;
        }
        
        if ($userSets) {
            $userParams[] = $auth['user_id'];
            $db->query("UPDATE users SET " . implode(',', $userSets) . ", updated_at=NOW() WHERE id=?", ...$userParams);
        }

        $db->commit();

        // Fetch updated details to return to the frontend
        $updatedCustomer = $db->fetchOne(
            "SELECT c.*, u.first_name, u.last_name, u.email, u.mobile,
                    u.gender, u.occupation, u.farm_size, u.primary_crop, u.dob
             FROM customers c 
             JOIN users u ON c.user_id=u.id 
             WHERE c.user_id=?", 
            $auth['user_id']
        );
        if ($updatedCustomer) {
            $updatedCustomer['full_name'] = trim($updatedCustomer['first_name'] . ' ' . $updatedCustomer['last_name']);
        }

        Response::success('Profile updated', $updatedCustomer);
    } catch (Exception $e) {
        $db->rollback();
        Response::error('Failed to update profile: ' . $e->getMessage());
    }
}

if ($method === 'GET' && $section === 'addresses') {
    Response::success('Addresses fetched',
        $db->fetchAll("SELECT * FROM customer_addresses WHERE customer_id=? ORDER BY is_default DESC, created_at DESC", $customer['id']));
}

if ($method === 'POST' && $section === 'addresses') {
    $err = Validator::required($body, ['full_name','mobile','address_line1','city','state','pincode']);
    if ($err) Response::error($err);
    
    if (!empty($body['is_default'])) {
        $db->query("UPDATE customer_addresses SET is_default=FALSE WHERE customer_id=?", $customer['id']);
    }
    
    $type = strtolower($body['address_type'] ?? 'home');
    // Map custom types to matching DB constraints ('home', 'work', 'other')
    if ($type === 'office' || $type === 'farm' || $type === 'work') {
        $type = 'work';
    } elseif ($type === 'home') {
        $type = 'home';
    } else {
        $type = 'other';
    }
    
    // Explicit string conversion of boolean value to prevent PDO empty string binding bug
    $isDefaultStr = !empty($body['is_default']) ? 'true' : 'false';
    
    $db->query(
        "INSERT INTO customer_addresses (id,customer_id,full_name,mobile,address_line1,address_line2,city,state,pincode,country,address_type,is_default)
         VALUES (gen_random_uuid(),?,?,?,?,?,?,?,?,?,?,?)",
        $customer['id'], $body['full_name'], $body['mobile'], $body['address_line1'],
        $body['address_line2'] ?? '', $body['city'], $body['state'], $body['pincode'],
        $body['country'] ?? 'India', $type, $isDefaultStr
    );
    
    $addr = $db->fetchOne("SELECT id FROM customer_addresses WHERE customer_id=? ORDER BY created_at DESC LIMIT 1", $customer['id']);
    Response::success('Address added', ['id' => $addr['id']], 201);
}

if ($method === 'DELETE' && $section === 'addresses') {
    $addrId = $_GET['id'] ?? '';
    if (!$addrId) Response::error('Address ID required');
    $db->query("DELETE FROM customer_addresses WHERE id=? AND customer_id=?", $addrId, $customer['id']);
    Response::success('Address deleted');
}

Response::error('Invalid request', 404);
