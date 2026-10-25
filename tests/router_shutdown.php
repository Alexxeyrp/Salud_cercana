<?php
declare(strict_types=1);

// Use the real dispatcher, with an isolated page and session handler. No app
// credentials, production session table or production login page are loaded.
$checks = 0;
function checkRouterShutdown(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function requestRouterShutdown(string $url, string $method = 'GET'): array
{
    $context = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'timeout' => 5,
        'follow_location' => 0,
    ]]);
    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s([0-9]{3})\s/', $responseHeaders[0] ?? '', $status);
    $headers = [];
    foreach (array_slice($responseHeaders, 1) as $header) {
        $pair = explode(':', $header, 2);
        if (count($pair) === 2) $headers[strtolower(trim($pair[0]))][] = trim($pair[1]);
    }
    return ['status' => (int) ($status[1] ?? 0), 'headers' => $headers, 'body' => $body];
}

$testRoot = sys_get_temp_dir() . '/router-shutdown-' . bin2hex(random_bytes(8));
mkdir($testRoot . '/api', 0700, true);
mkdir($testRoot . '/config', 0700);
mkdir($testRoot . '/css', 0700);
$process = null;
$fixtures = ['api/index.php', 'login.php', 'config/private.txt', 'css/style.css', '.env', 'server.log'];
try {
    copy(__DIR__ . '/../api/index.php', $testRoot . '/api/index.php');
    file_put_contents($testRoot . '/config/private.txt', 'PRIVATE_FIXTURE');
    file_put_contents($testRoot . '/.env', 'PRIVATE_ENV_FIXTURE');
    file_put_contents($testRoot . '/css/style.css', '/* public fixture */');
    file_put_contents($testRoot . '/login.php', <<<'PHP'
<?php
declare(strict_types=1);

final class FixtureSessionHandler implements SessionHandlerInterface
{
    public function __construct(private string $mode) {}
    public function open(string $path, string $name): bool { return true; }
    public function read(string $id): string|false { return ''; }
    public function write(string $id, string $data): bool
    {
        if ($this->mode === 'write-fail') throw new RuntimeException('Controlled write failure');
        file_put_contents(__DIR__ . '/saved-' . $this->mode . '.txt', $data);
        return true;
    }
    public function close(): bool
    {
        if ($this->mode === 'close-fail') throw new RuntimeException('Controlled close failure');
        file_put_contents(__DIR__ . '/closed-' . $this->mode . '.txt', 'closed');
        return true;
    }
    public function destroy(string $id): bool { return true; }
    public function gc(int $lifetime): int|false { return 0; }
}
$mode = $_GET['mode'] ?? 'success';
if (!in_array($mode, ['success', 'write-fail', 'close-fail'], true)) throw new RuntimeException('Invalid fixture mode');
session_name('router_fixture');
session_set_save_handler(new FixtureSessionHandler($mode), true);
session_start();
$_SESSION['saved_marker'] = 'SESSION_SAVED';
header('Location: index.php');
header('Set-Cookie: extra_fixture=present; HttpOnly', false);
header('ETag: "fixture-entity"');
if ($mode !== 'success') header('Content-Length: 65536');
echo $mode === 'success' ? 'SUCCESS_BODY' : 'PARTIAL_BODY';
exit;
PHP);

    $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($listener === false) throw new RuntimeException('Could not choose a loopback test port.');
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $process = proc_open([PHP_BINARY, '-S', $address, '-t', $testRoot, $testRoot . '/api/index.php'], [
        0 => ['pipe', 'r'], 1 => ['file', $testRoot . '/server.log', 'a'], 2 => ['file', $testRoot . '/server.log', 'a'],
    ], $pipes, $testRoot, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Could not start the temporary PHP server.');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $socket = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
        if ($socket !== false) { fclose($socket); $ready = true; break; }
        usleep(20000);
    }
    checkRouterShutdown($ready, 'Temporary HTTP server did not start.');
    $base = 'http://' . $address;

    foreach (['write-fail', 'close-fail'] as $mode) {
        foreach (['GET', 'HEAD'] as $method) {
            $response = requestRouterShutdown($base . '/login.php?mode=' . $mode, $method);
            checkRouterShutdown($response['status'] === 503, "$mode on $method must replace a redirect with 503.");
            foreach (['location', 'set-cookie', 'content-length', 'etag'] as $header) {
                checkRouterShutdown(!isset($response['headers'][$header]), "$mode on $method must remove stale $header.");
            }
            $expectedBody = $method === 'HEAD' ? '' : 'Servicio temporalmente no disponible. Inténtalo más tarde.';
            checkRouterShutdown($response['body'] === $expectedBody, "$mode on $method must discard partial output.");
            checkRouterShutdown(($response['headers']['cache-control'][0] ?? '') === 'private, no-store', 'Failures must not be cacheable.');
        }
    }
    checkRouterShutdown(!is_file($testRoot . '/saved-write-fail.txt'), 'A failed write must not claim to have saved the fixture.');

    $success = requestRouterShutdown($base . '/login.php?mode=success');
    checkRouterShutdown($success['status'] === 302 && ($success['headers']['location'][0] ?? '') === 'index.php', 'A saved session must preserve its redirect.');
    checkRouterShutdown(isset($success['headers']['set-cookie']), 'Successful session creation must retain the cookie.');
    checkRouterShutdown($success['body'] === 'SUCCESS_BODY', 'Successful exit output must flush exactly once.');
    checkRouterShutdown(str_contains((string) file_get_contents($testRoot . '/saved-success.txt'), 'SESSION_SAVED'), 'The session must be saved before a redirect completes.');
    checkRouterShutdown(is_file($testRoot . '/closed-success.txt'), 'A redirect must close the session handler.');
    $headSuccess = requestRouterShutdown($base . '/login.php?mode=success', 'HEAD');
    checkRouterShutdown($headSuccess['status'] === 302 && $headSuccess['body'] === '', 'A successful HEAD redirect must have no body.');

    foreach (['/config/private.txt', '/.env', '/%2eenv', '/css%2f..%2fconfig%2fprivate.txt', '/login.php%00', '/api/index.php'] as $path) {
        $response = requestRouterShutdown($base . $path);
        checkRouterShutdown($response['status'] === 404 && !str_contains((string) $response['body'], 'PRIVATE_'), 'The dispatcher must reject non-public paths, including encoded paths.');
    }
    $static = requestRouterShutdown($base . '/css/style.css');
    checkRouterShutdown($static['status'] === 200 && $static['body'] === '/* public fixture */' && !isset($static['headers']['set-cookie']), 'An allowed static file must work without creating a session.');
    echo "Router shutdown: {$checks} isolated HTTP checks passed.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    foreach (['success', 'write-fail', 'close-fail'] as $mode) {
        $fixtures[] = 'saved-' . $mode . '.txt';
        $fixtures[] = 'closed-' . $mode . '.txt';
    }
    // Remove only the exact files generated above; never traverse user folders.
    foreach ($fixtures as $fixture) if (is_file($testRoot . '/' . $fixture)) unlink($testRoot . '/' . $fixture);
    foreach (['api', 'config', 'css'] as $directory) rmdir($testRoot . '/' . $directory);
    rmdir($testRoot);
}
