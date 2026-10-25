<?php
declare(strict_types=1);
// Run against an explicitly selected disposable local MySQL with a trusted CA.
// First use localhost and the correct CA; then change host/CA and use "reject".
require_once __DIR__ . '/../config/environment.php';
if (getenv('SALUD_TEST_DB') !== '1'
    || !in_array(getenv('DB_HOST'), ['localhost', '127.0.0.1'], true)
    || !str_starts_with((string) getenv('DB_NAME'), 'salud_test_')
    || in_array((string) getenv('DB_PORT'), ['3306', '3307'], true)
    || getenv('DB_SSL_MODE') !== 'verify-full') {
    throw new RuntimeException('Usa una base de pruebas local aislada con TLS verificado.');
}
$reject = ($argv[1] ?? '') === 'reject';
try {
    $database = saludDatabase();
} catch (RuntimeException $exception) {
    if (!$reject) throw $exception;
    echo "OK TLS rejected invalid server trust/identity.\n";
    exit(0);
}
if ($reject) throw new RuntimeException('La conexión TLS insegura fue aceptada.');
$cipher = $database->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch()['Value'] ?? '';
if ($cipher === '') throw new RuntimeException('No se ha negociado TLS.');
echo "OK verified TLS connection, encrypted cipher negotiated.\n";
