<?php
require_once __DIR__ . '/../helpers/helpers.php';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = isset($_GET['parent_only'])
        ? "SELECT id, name, slug, icon, image_url, sort_order FROM categories WHERE is_active=TRUE AND parent_id IS NULL ORDER BY sort_order"
        : "SELECT c.id, c.name, c.slug, c.icon, c.image_url, c.sort_order, c.parent_id, c.is_featured,
                  p.name AS parent_name
           FROM categories c LEFT JOIN categories p ON c.parent_id=p.id
           WHERE c.is_active=TRUE ORDER BY c.sort_order";
    Response::success('Categories fetched', $db->fetchAll($sql));
}

Response::error('Invalid request', 404);
