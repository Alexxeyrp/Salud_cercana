<?php
declare(strict_types=1);

// Public binary endpoint: do not include auth/header or start a session here.
require_once __DIR__ . '/includes/news_images.php';

function imageNotFound(): never
{
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Cache-Control: no-store');
    exit;
}
$id = $_GET['id'] ?? null;
if (!newsImageIdValid($id)) {
    imageNotFound();
}

require_once __DIR__ . '/config/conexion.php';
try {
    $query = $pdo->prepare('SELECT mime_type, byte_size, sha256 FROM app_news_images WHERE id = ?');
    $query->execute([$id]);
    $image = $query->fetch(PDO::FETCH_ASSOC);
    if (!$image || !in_array($image['mime_type'], NEWS_IMAGE_MIME_TYPES, true)
        || (int) $image['byte_size'] < 1 || (int) $image['byte_size'] > NEWS_IMAGE_MAX_BYTES
        || preg_match('/\A[a-f0-9]{64}\z/D', $image['sha256']) !== 1) {
        imageNotFound();
    }
    $etag = '"' . $image['sha256'] . '"';
    header('Content-Type: ' . $image['mime_type']);
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
    if (newsImageEtagMatches($_SERVER['HTTP_IF_NONE_MATCH'] ?? '', $etag)) {
        http_response_code(304);
        exit;
    }
    header('Content-Length: ' . (int) $image['byte_size']);
    if ($method === 'HEAD') {
        exit;
    }
    $query = $pdo->prepare('SELECT image_data FROM app_news_images WHERE id = ?');
    $query->execute([$id]);
    $data = $query->fetchColumn();
    if (is_resource($data)) {
        fpassthru($data);
    } elseif (is_string($data)) {
        echo $data;
    }
} catch (PDOException $exception) {
    http_response_code(503);
    header('Cache-Control: no-store');
    header_remove('Content-Length');
    header_remove('ETag');
    // Keep database details out of the public response.
}
