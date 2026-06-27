<?php
// save-reviews-cache.php
// Permite guardar las reseñas obtenidas de Google en un cache local para mostrar en el sitio corporativo.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!isset($data['reviews'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data. Must contain "reviews" array.']);
    exit;
}

$filePath = __DIR__ . '/google-reviews-cache.json';
if (file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true, 'message' => 'Cache actualizado exitosamente.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo escribir el archivo de cache en el servidor.']);
}
