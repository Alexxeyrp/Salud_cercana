<?php
/**
 * Conexión centralizada a MySQL mediante PDO.
 * Puedes definir DB_HOST, DB_NAME, DB_USER y DB_PASSWORD en tu entorno.
 */


declare(strict_types=1);

// Shared hosting often cannot configure process environment variables.
// Keep this file outside htdocs/public_html, never inside the public package.
$privateFile = dirname(__DIR__, 2) . '/salud-private/config.php';
// Some shared hosts restrict PHP reads to the document root.
// config/ is denied by both its .htaccess and the public root rules.
if (!@is_file($privateFile)) {
    $privateFile = __DIR__ . '/private.php';
}
$privateConfig = is_file($privateFile) ? require $privateFile : [];
$dbSetting = static function (string $key, string $default = '') use ($privateConfig): string {
    $value = getenv($key);
    return $value !== false ? $value : (string) ($privateConfig[$key] ?? $default);
};
$host = $dbSetting('DB_HOST', 'localhost');
$port = $dbSetting('DB_PORT', '3307');
$dbname = $dbSetting('DB_NAME', 'sitio_web');
$user = $dbSetting('DB_USER', 'root');
$password = $dbSetting('DB_PASSWORD');

$isRemote = $host !== 'localhost'
    && $host !== '127.0.0.1';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

try {
    if (getenv('VERCEL')) {
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $variable) {
            if (getenv($variable) === false || getenv($variable) === '') {
                throw new RuntimeException('Falta una variable de conexión requerida.');
            }
        }
        if (!$isRemote) {
            throw new RuntimeException('Vercel requiere una base de datos externa con TLS.');
        }
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ];

    // Opt-in only for the provider's private database network, never for Aiven.
    if ($isRemote && $dbSetting('DB_SSL_MODE') !== 'provider-internal') {
        $caPath = $dbSetting('DB_SSL_CA');
        $encodedCa = $dbSetting('DB_SSL_CA_BASE64');
        if ($encodedCa !== false && $encodedCa !== '') {
            $certificate = base64_decode($encodedCa, true);
            if ($certificate === false || !openssl_x509_read($certificate)
                || str_contains($certificate, 'PRIVATE KEY')) {
                throw new RuntimeException('El certificado CA configurado no es válido.');
            }
            $caPath = tempnam(sys_get_temp_dir(), 'aiven-ca-');
            if ($caPath === false) {
                throw new RuntimeException('No se pudo preparar el certificado CA.');
            }
            register_shutdown_function(static function () use ($caPath): void {
                if (is_file($caPath)) unlink($caPath);
            });
            if (file_put_contents($caPath, $certificate) !== strlen($certificate)) {
                throw new RuntimeException('No se pudo escribir el certificado CA.');
            }
            chmod($caPath, 0600);
        }
        if (!$caPath || !is_readable($caPath)) {
            throw new RuntimeException(
                'Falta el certificado CA de MySQL'
            );
        }
        $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    $pdo = new PDO($dsn, $user, $password, $options);
} catch (Throwable $exception) {
    error_log('Error de conexión MySQL (' . get_class($exception) . ').');
    http_response_code(503);
    exit('No se ha podido conectar con la base de datos.');
}
