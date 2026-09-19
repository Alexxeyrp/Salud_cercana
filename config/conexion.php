<?php
/**
 * Conexión centralizada a MySQL mediante PDO.
 * Puedes definir DB_HOST, DB_NAME, DB_USER y DB_PASSWORD en tu entorno.
 */


declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$database = getenv('DB_NAME') ?: 'sitio_web';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3307;dbname=sitio_web;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    // No se muestran detalles de conexión al visitante por seguridad.
    exit('No se ha podido conectar con la base de datos. Revisa la configuración.');
}
