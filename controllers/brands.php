<?php
require_once __DIR__ . '/../helpers/helpers.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $brands = $db->fetchAll(
        "SELECT id, name, slug, logo_url, description
         FROM brands
         WHERE is_active = TRUE
         ORDER BY name ASC"
    );
    Response::success('Brands fetched', $brands);
}

Response::error('Invalid request', 404);
