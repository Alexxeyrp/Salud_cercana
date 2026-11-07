<?php
declare(strict_types=1);

// Run through CLI only, against a dedicated fictitious demo database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('DEMO_SETUP') !== '1') {
    exit("Set DEMO_SETUP=1 only for a separate demo database.\n");
}
$username = $argv[1] ?? '';
$role = $argv[2] ?? 'user';
$password = getenv('DEMO_PASSWORD') ?: '';
if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)
    || !in_array($role, ['user', 'admin'], true) || strlen($password) < 16) {
    exit("Usage: php scripts/create-demo-user.php USERNAME user|admin\nSet a unique DEMO_PASSWORD (at least 16 characters) privately.\n");
}
require __DIR__ . '/../config/conexion.php';
try {
    $pdo->beginTransaction();
    $q = $pdo->prepare('INSERT INTO users_data (nombre,apellidos,email,telefono,fecha_nacimiento,direccion,sexo) VALUES (?,?,?,?,?,?,?)');
    $q->execute(['Persona', 'Ficticia', $username . '@example.invalid', '600000000', '1990-01-01', 'Calle ficticia', 'otro']);
    $id = $pdo->lastInsertId();
    $q = $pdo->prepare('INSERT INTO users_login (idUser,usuario,password,rol) VALUES (?,?,?,?)');
    $q->execute([$id, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
    $pdo->commit();
    echo "Demo account created. Password withheld.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Account was not created; check uniqueness and database schema.\n");
    exit(1);
}
