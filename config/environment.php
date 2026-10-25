<?php
declare(strict_types=1);

/** Environment values come from the host; never load a public .env file. */
function saludEnv(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function saludIsLocal(): bool
{
    return saludEnv('APP_ENV') === 'local' && getenv('VERCEL') === false;
}

/** Validate before opening a socket, so production cannot fall back to XAMPP. */
function saludDatabaseConfig(): array
{
    $local = saludIsLocal();
    $values = [];
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $name) {
        $value = saludEnv($name);
        if ($value === null || ($value === '' && !($local && $name === 'DB_PASSWORD'))) {
            throw new RuntimeException('Falta configuración obligatoria de la base de datos.');
        }
        $values[$name] = $value;
    }
    if (!preg_match('/\A[a-zA-Z0-9.:-]+\z/D', $values['DB_HOST'])
        || !preg_match('/\A[a-zA-Z0-9_]+\z/D', $values['DB_NAME'])
        || str_contains($values['DB_USER'], "\0")
        || !ctype_digit($values['DB_PORT'])
        || (int) $values['DB_PORT'] < 1 || (int) $values['DB_PORT'] > 65535) {
        throw new RuntimeException('La configuración de la base de datos no es válida.');
    }
    $mode = saludEnv('DB_SSL_MODE', 'verify-full');
    if ($mode !== 'verify-full' && !($local && $mode === 'disabled')) {
        throw new RuntimeException('La base de datos requiere TLS con verificación de certificado.');
    }
    $values['tls'] = $mode === 'verify-full';
    return $values;
}

/** Materialize only the public CA, using a fresh private temporary file. */
function saludDatabaseCaPath(): string
{
    static $temporaryPath = null;
    if ($temporaryPath !== null) return $temporaryPath;
    $encoded = saludEnv('DB_SSL_CA_BASE64');
    $configuredPath = saludEnv('DB_SSL_CA');
    if ($encoded !== null && $encoded !== '') {
        $pem = base64_decode($encoded, true);
        if ($pem === false || strlen($pem) > 131072) {
            throw new RuntimeException('El certificado CA de la base de datos no es válido.');
        }
    } elseif ($configuredPath !== null && $configuredPath !== ''
        && is_file($configuredPath) && is_readable($configuredPath)) {
        $pem = @file_get_contents($configuredPath);
        if ($pem === false || strlen($pem) > 131072) {
            throw new RuntimeException('El certificado CA de la base de datos no se puede leer.');
        }
    } else {
        throw new RuntimeException('Falta el certificado CA de la base de datos.');
    }
    if (!extension_loaded('openssl') || !str_contains($pem, '-----BEGIN CERTIFICATE-----')
        || str_contains($pem, 'PRIVATE KEY') || @openssl_x509_read($pem) === false) {
        throw new RuntimeException('El certificado CA de la base de datos no es válido.');
    }
    if ($encoded === null || $encoded === '') return $configuredPath;
    $path = @tempnam(sys_get_temp_dir(), 'salud-ca-');
    if ($path === false || @file_put_contents($path, $pem, LOCK_EX) !== strlen($pem)) {
        if (is_string($path)) @unlink($path);
        throw new RuntimeException('No se ha podido preparar el certificado CA.');
    }
    @chmod($path, 0600);
    $temporaryPath = $path;
    register_shutdown_function(static function () use ($path): void { @unlink($path); });
    return $temporaryPath;
}

/** Reuse one nonpersistent connection per request, including the session lock. */
function saludDatabase(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    $config = saludDatabaseConfig();
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('El servidor no dispone del controlador de base de datos.');
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => 5,
        PDO::MYSQL_ATTR_LOCAL_INFILE => false,
    ];
    if ($config['tls']) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = saludDatabaseCaPath();
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    try {
        $dsn = 'mysql:host=' . $config['DB_HOST'] . ';port=' . $config['DB_PORT']
            . ';dbname=' . $config['DB_NAME'] . ';charset=utf8mb4';
        $candidate = new PDO($dsn, $config['DB_USER'], $config['DB_PASSWORD'], $options);
        if ($config['tls']) {
            $status = $candidate->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch();
            if (!$status || empty($status['Value'])) {
                throw new RuntimeException('La base de datos requiere una conexión TLS verificada.');
            }
        }
        $connection = $candidate;
        return $connection;
    } catch (Throwable) {
        // Original exceptions can contain hosts and users; never expose them.
        throw new RuntimeException('No se ha podido conectar de forma segura con la base de datos.');
    }
}
