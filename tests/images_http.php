<?php
declare(strict_types=1);

// Exercise the real endpoint in an isolated temporary app using SQLite fixtures.
// The production connection file is neither loaded nor copied.
$checks = 0;
function checkImageHttp(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function requestImageHttp(string $url, string $method = 'GET', array $headers = []): array
{
    $context = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'timeout' => 5,
        'follow_location' => 0, 'header' => implode("\r\n", $headers),
    ]]);
    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s([0-9]{3})\s/', $responseHeaders[0] ?? '', $status);
    $parsed = [];
    foreach (array_slice($responseHeaders, 1) as $header) {
        $pair = explode(':', $header, 2);
        if (count($pair) === 2) {
            $parsed[strtolower(trim($pair[0]))] = trim($pair[1]);
        }
    }
    return ['status' => (int) ($status[1] ?? 0), 'headers' => $parsed, 'body' => $body];
}
$testRoot = sys_get_temp_dir() . '/news-images-http-' . bin2hex(random_bytes(8));
mkdir($testRoot . '/includes', 0700, true);
mkdir($testRoot . '/config', 0700);
$process = null;
try {
    copy(__DIR__ . '/../imagen.php', $testRoot . '/imagen.php');
    copy(__DIR__ . '/../includes/news_images.php', $testRoot . '/includes/news_images.php');
    $databasePath = $testRoot . '/fixtures.sqlite';
    file_put_contents($testRoot . '/config/conexion.php', '<?php $pdo = new PDO(' . var_export('sqlite:' . $databasePath, true)
        . ', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);');
    $pdo = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE app_news_images (id TEXT PRIMARY KEY, mime_type TEXT, byte_size INTEGER, sha256 TEXT, image_data BLOB)');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
    $id = str_repeat('a', 32);
    $insert = $pdo->prepare('INSERT INTO app_news_images VALUES (?, ?, ?, ?, ?)');
    $insert->bindValue(1, $id);
    $insert->bindValue(2, 'image/png');
    $insert->bindValue(3, strlen($png), PDO::PARAM_INT);
    $insert->bindValue(4, hash('sha256', $png));
    $insert->bindValue(5, $png, PDO::PARAM_LOB);
    $insert->execute();
    $insert = null;
    $pdo = null;

    $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($listener === false) {
        throw new RuntimeException('Could not choose a loopback test port.');
    }
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $process = proc_open([PHP_BINARY, '-S', $address, '-t', $testRoot], [
        0 => ['pipe', 'r'], 1 => ['file', $testRoot . '/server.log', 'a'], 2 => ['file', $testRoot . '/server.log', 'a'],
    ], $pipes, $testRoot, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the temporary PHP server.');
    }
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $socket = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
        if ($socket !== false) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(20000);
    }
    checkImageHttp($ready, 'Temporary HTTP server did not start.');
    $base = 'http://' . $address . '/imagen.php';
    $response = requestImageHttp($base . '?id=' . $id);
    checkImageHttp($response['status'] === 200 && $response['body'] === $png, 'GET must return exact fixture bytes.');
    checkImageHttp(($response['headers']['content-type'] ?? '') === 'image/png', 'Image MIME must be explicit.');
    checkImageHttp(($response['headers']['x-content-type-options'] ?? '') === 'nosniff', 'Images must disable MIME sniffing.');
    checkImageHttp(str_contains($response['headers']['cache-control'] ?? '', 'immutable'), 'Immutable image URLs must be cacheable.');
    checkImageHttp(!isset($response['headers']['set-cookie']), 'Public images must not create a session.');
    $etag = $response['headers']['etag'] ?? '';
    checkImageHttp($etag === '"' . hash('sha256', $png) . '"', 'ETag must identify exact content.');
    $head = requestImageHttp($base . '?id=' . $id, 'HEAD');
    checkImageHttp($head['status'] === 200 && $head['body'] === '' && (int) ($head['headers']['content-length'] ?? 0) === strlen($png), 'HEAD must expose metadata without a body.');
    $cached = requestImageHttp($base . '?id=' . $id, 'GET', ['If-None-Match: W/' . $etag]);
    checkImageHttp($cached['status'] === 304 && $cached['body'] === '', 'Conditional GET must avoid retransmitting image bytes.');
    checkImageHttp(requestImageHttp($base . '?id=' . $id, 'POST')['status'] === 405, 'An image URL must reject writes.');
    checkImageHttp(requestImageHttp($base . '?id=' . str_repeat('b', 32))['status'] === 404, 'Missing images must be 404.');
    $invalid = requestImageHttp($base . '?id%5B%5D=x');
    checkImageHttp($invalid['status'] === 404 && $invalid['body'] === '' && ($invalid['headers']['cache-control'] ?? '') === 'no-store', 'Malformed IDs must fail without details or cache.');
    echo "Images HTTP: {$checks} isolated checks passed.\n";
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    // Delete only the exact generated fixtures, never production/user data.
    foreach (['imagen.php', 'includes/news_images.php', 'config/conexion.php', 'fixtures.sqlite', 'server.log'] as $fixture) {
        if (is_file($testRoot . '/' . $fixture)) {
            unlink($testRoot . '/' . $fixture);
        }
    }
    rmdir($testRoot . '/includes');
    rmdir($testRoot . '/config');
    rmdir($testRoot);
}
