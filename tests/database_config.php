<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/environment.php';

$names = ['APP_ENV', 'VERCEL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_SSL_MODE', 'DB_SSL_CA', 'DB_SSL_CA_BASE64'];
$saved = [];
foreach ($names as $name) { $saved[$name] = getenv($name); putenv($name); }
$checks = 0;
function expectConfigFailure(callable $operation): void
{
    global $checks;
    try { $operation(); } catch (RuntimeException) { $checks++; return; }
    throw new RuntimeException('Se aceptó una configuración que debía fallar.');
}
try {
    expectConfigFailure(fn() => saludDatabaseConfig());
    foreach (['DB_HOST' => 'database.example.invalid', 'DB_PORT' => '3306', 'DB_NAME' => 'application', 'DB_USER' => 'application', 'DB_PASSWORD' => 'test-placeholder'] as $key => $value) {
        putenv($key . '=' . $value);
    }
    if (saludDatabaseConfig()['tls'] !== true) throw new RuntimeException('TLS no está activado por defecto.');
    $checks++;
    putenv('DB_SSL_MODE=disabled');
    expectConfigFailure(fn() => saludDatabaseConfig());
    putenv('APP_ENV=local');
    if (saludDatabaseConfig()['tls'] !== false) throw new RuntimeException('No funciona el modo local explícito.');
    $checks++;
    putenv('VERCEL=1');
    expectConfigFailure(fn() => saludDatabaseConfig());
    putenv('VERCEL');
    putenv('APP_ENV=production');
    putenv('DB_SSL_MODE=verify-full');
    putenv('DB_PASSWORD=');
    expectConfigFailure(fn() => saludDatabaseConfig());
    putenv('DB_PASSWORD=test-placeholder');
    putenv('DB_HOST=localhost;dbname=other');
    expectConfigFailure(fn() => saludDatabaseConfig());
    putenv('DB_HOST=database.example.invalid');
    putenv('DB_PORT=0');
    expectConfigFailure(fn() => saludDatabaseConfig());
    putenv('DB_PORT=3306');
    expectConfigFailure(fn() => saludDatabaseCaPath());
    putenv('DB_SSL_CA_BASE64=%%%not-base64%%%');
    expectConfigFailure(fn() => saludDatabaseCaPath());
    putenv('DB_SSL_CA_BASE64=' . base64_encode('not a certificate'));
    expectConfigFailure(fn() => saludDatabaseCaPath());
    echo "OK database configuration: $checks checks; no database connection opened.\n";
} finally {
    foreach ($saved as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
}
