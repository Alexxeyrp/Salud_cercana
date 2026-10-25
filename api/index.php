<?php
declare(strict_types=1);

// Every public URL goes through this allowlist, including static files.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
// Exceptions may contain SQL or credentials: only our safe messages are logged below.
ini_set('log_errors', '0');
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: private, no-store');

set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $level)) return false;
    throw new ErrorException('Application warning', 0, $level, $file, $line);
});

$requestFailed = false;
$failRequest = static function () use (&$requestFailed): void {
    if ($requestFailed) return;
    $requestFailed = true;
    if (session_status() === PHP_SESSION_ACTIVE) {
        try { session_abort(); } catch (Throwable) { /* Connection closes at request end. */ }
    }
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(503);
    header_remove('Location');
    header_remove('Set-Cookie');
    header_remove('Content-Length');
    header_remove('ETag');
    header('Cache-Control: private, no-store');
    header('Content-Type: text/plain; charset=utf-8');
    error_log('Salud Cercana: request failed; check configuration, migrations and database availability.');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo 'Servicio temporalmente no disponible. Inténtalo más tarde.';
    }
};
set_exception_handler(static function (Throwable $error) use ($failRequest): void {
    $failRequest();
});

// Register BEFORE session bootstrap. Redirects use exit, so finalization must
// also run on that path, before PHP's own session and output shutdown callbacks.
register_shutdown_function(static function () use (&$requestFailed, $failRequest): void {
    if ($requestFailed) return;
    $lastError = error_get_last();
    if ($lastError !== null && ($lastError['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        $failRequest();
        return;
    }
    try {
        if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
            throw new RuntimeException('Session could not be saved.');
        }
        while (ob_get_level() > 0) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') ob_end_clean();
            else ob_end_flush();
        }
    } catch (Throwable $error) {
        $failRequest();
    }
});

$root = dirname(__DIR__);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($requestPath) ? rawurldecode($requestPath) : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    exit;
}

$staticFiles = [
    '/css/style.css' => 'text/css; charset=utf-8',
    '/js/script.js' => 'text/javascript; charset=utf-8',
];
if (preg_match('~\A/uploads/noticias/[a-f0-9]{32}\.(jpg|jpeg|png|webp)\z~D', $path, $match)) {
    $staticFiles[$path] = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$match[1]];
}
if (isset($staticFiles[$path]) && is_file($root . $path)) {
    if ($method === 'POST') { http_response_code(405); header('Allow: GET, HEAD'); exit; }
    header('Content-Type: ' . $staticFiles[$path]);
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . filesize($root . $path));
    if ($method !== 'HEAD') readfile($root . $path);
    exit;
}

$pages = [
    '/' => 'index.php',
    '/index.php' => 'index.php',
    '/noticias.php' => 'noticias.php',
    '/registro.php' => 'registro.php',
    '/login.php' => 'login.php',
    '/logout.php' => 'logout.php',
    '/perfil.php' => 'perfil.php',
    '/citaciones.php' => 'citaciones.php',
    '/citaciones-administracion.php' => 'citaciones-administracion.php',
    '/noticias-administracion.php' => 'noticias-administracion.php',
    '/usuarios-administracion.php' => 'usuarios-administracion.php',
    '/crear_noticia.php' => 'crear_noticia.php',
    '/imagen.php' => 'imagen.php',
];
if (!isset($pages[$path])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    if ($method !== 'HEAD') echo 'Página no encontrada.';
    exit;
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 3 * 1024 * 1024) {
    http_response_code(413);
    header('Content-Type: text/plain; charset=utf-8');
    exit('La solicitud supera el tamaño permitido. La imagen debe ocupar como máximo 2 MB.');
}

$_SERVER['PHP_SELF'] = '/' . $pages[$path];
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
ob_start();
require $root . '/' . $pages[$path];
