<?php
require_once __DIR__ . '/../helpers/helpers.php';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = isset($_GET['parent_only'])
        ? "SELECT * FROM categories WHERE is_active=1 AND parent_id IS NULL ORDER BY sort_order"
        : "SELECT c.*, p.name AS parent_name FROM categories c LEFT JOIN categories p ON c.parent_id=p.id WHERE c.is_active=1 ORDER BY c.sort_order";
    Response::success('Categories fetched', $db->fetchAll($sql));
}

Response::error('Invalid request', 404);
