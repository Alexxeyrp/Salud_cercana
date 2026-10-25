<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');

try {
    foreach (['pdo_mysql', 'openssl', 'mbstring', 'fileinfo', 'session'] as $extension) {
        if (!extension_loaded($extension)) throw new RuntimeException('Falta una extensión requerida.');
    }
    require_once __DIR__ . '/../config/environment.php';
    saludDatabaseConfig();
    if ((saludEnv('DB_SSL_MODE', 'verify-full')) === 'verify-full') saludDatabaseCaPath();
    echo "OK: extensiones y configuración presentes; valores ocultos.\n";
    if (!in_array('--database', $argv, true)) {
        echo "Sin conexión de red. Añade --database para comprobar MySQL y esquema en modo lectura.\n";
        exit;
    }
    $pdo = saludDatabase();
    $required = [
        'users_data' => ['idUser', 'nombre', 'apellidos', 'email', 'telefono', 'fecha_nacimiento', 'direccion', 'sexo'],
        'users_login' => ['idUser', 'usuario', 'password', 'rol'],
        'noticias' => ['idNoticia', 'titulo', 'imagen', 'texto', 'fecha', 'idUser'],
        'citas' => ['idCita', 'idUser', 'fecha_cita', 'motivo_cita'],
        'app_sessions' => ['session_key', 'data', 'expires_at'],
        'app_news_images' => ['id', 'mime_type', 'byte_size', 'sha256', 'image_data'],
    ];
    foreach ($required as $table => $columns) {
        $query = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $query->execute([$table]);
        if (array_diff($columns, $query->fetchAll(PDO::FETCH_COLUMN))) {
            throw new RuntimeException('Falta una tabla o columna requerida; revisa las migraciones.');
        }
        $query = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $query->execute([$table]);
        if (strcasecmp((string) $query->fetchColumn(), 'InnoDB') !== 0) throw new RuntimeException('Las tablas deben usar InnoDB.');
    }
    $packet = (int) $pdo->query('SELECT @@max_allowed_packet')->fetchColumn();
    if ($packet < 4 * 1024 * 1024) throw new RuntimeException('max_allowed_packet debe admitir las imágenes de 2 MiB.');
    echo "OK: conexión, TLS según configuración, tablas/columnas, InnoDB y tamaño de paquetes.\n";
    echo "Pendiente comprobar permisos de escritura y flujos completos en una base de pruebas.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR: preflight no superado. Revisa extensiones, variables, CA, conectividad y migraciones. No se muestran detalles sensibles.\n");
    exit(1);
}
