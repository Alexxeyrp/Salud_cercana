<?php
/**
 * Conexión centralizada a MySQL mediante PDO.
 * Puedes definir DB_HOST, DB_NAME, DB_USER y DB_PASSWORD en tu entorno.
 */


declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3307';
$dbname = getenv('DB_NAME') ?: 'sitio_web';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

$isRemote = $host !== 'localhost'
    && $host !== '127.0.0.1';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ];

    if ($isRemote) {
        $caPath = getenv('DB_SSL_CA');
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
    error_log('Error de conexión: ' . $exception->getMessage());
    http_response_code(503);
    exit('No se ha podido conectar con la base de datos.');
}
