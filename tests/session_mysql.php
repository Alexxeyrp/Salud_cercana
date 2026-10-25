<?php
declare(strict_types=1);
define('SALUD_SESSION_NO_AUTOSTART', true);
require_once __DIR__ . '/../includes/session.php';

// Never execute this mutating test against production or an ordinary local DB.
if (getenv('SALUD_TEST_DB') !== '1'
    || !saludIsLocal()
    || !in_array(getenv('DB_HOST'), ['localhost', '127.0.0.1'], true)
    || !str_starts_with((string) getenv('DB_NAME'), 'salud_test_')
    || in_array((string) getenv('DB_PORT'), ['3306', '3307'], true)) {
    throw new RuntimeException('Usa una base de pruebas local aislada (SALUD_TEST_DB=1).');
}
$database = saludDatabase();
if (($argv[1] ?? '') === 'worker') {
    $worker = new SaludMysqlSessionHandler($database, 1800, 5);
    $id = $argv[2] ?? '';
    $count = (int) $worker->read($id);
    usleep(250000);
    $worker->write($id, (string) ($count + 1));
    $worker->close();
    exit(0);
}
$database->exec((string) file_get_contents(__DIR__ . '/../database/001_sessions.sql'));
// Applying the additive migration twice must leave existing sessions untouched.
$database->exec((string) file_get_contents(__DIR__ . '/../database/001_sessions.sql'));
$ids = [];
$checks = 0;
function sessionCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function freshId(): string
{
    global $ids;
    return $ids[] = bin2hex(random_bytes(32));
}
function secondDatabase(): PDO
{
    $config = saludDatabaseConfig();
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
    if ($config['tls']) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = saludDatabaseCaPath();
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    return new PDO('mysql:host=' . $config['DB_HOST'] . ';port=' . $config['DB_PORT'] . ';dbname=' . $config['DB_NAME'], $config['DB_USER'], $config['DB_PASSWORD'], $options);
}
$first = new SaludMysqlSessionHandler($database);
$second = new SaludMysqlSessionHandler(secondDatabase(), 1800, 0);
try {
    sessionCheck(!$first->validateId('../invalid'), 'Se aceptó un ID de sintaxis inválida.');
    $id = freshId();
    sessionCheck(!$first->validateId($id), 'Se aceptó una sesión desconocida.');
    sessionCheck($first->read($id) === '', 'Una sesión desconocida contiene datos.');
    $first->write($id, 'idUser|i:42;rol|s:5:"admin";');
    $first->close();
    sessionCheck($second->validateId($id), 'No persiste una sesión entre conexiones.');
    sessionCheck(str_contains($second->read($id), 'idUser|i:42'), 'Los datos de sesión han cambiado.');
    $second->close();
    $first->read($id);
    $blocked = false;
    try { $second->read($id); } catch (RuntimeException) { $blocked = true; }
    sessionCheck($blocked, 'Dos conexiones entraron a la vez en una sesión.');
    $first->close();
    sessionCheck($second->read($id) !== '', 'No se libera el bloqueo al cerrar.');
    $second->close();
    $statement = $database->prepare('UPDATE app_sessions SET expires_at = 0 WHERE session_key = ?');
    $statement->execute([hash('sha256', $id)]);
    sessionCheck(!$first->validateId($id), 'Se aceptó una sesión caducada.');
    sessionCheck($first->read($id) === '', 'Se leen datos de una sesión caducada.');
    $first->close();
    sessionCheck($first->gc(1800) >= 1, 'No se limpia una sesión caducada.');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '0');
    ini_set('session.gc_probability', '0');
    session_set_save_handler($first, true);
    $unknown = freshId();
    session_id($unknown);
    session_start();
    $old = session_id();
    $ids[] = $old;
    sessionCheck($old !== $unknown, 'PHP aceptó un ID impuesto y desconocido.');
    $_SESSION['idUser'] = 42;
    session_write_close();
    session_id($old);
    session_start();
    sessionCheck(($_SESSION['idUser'] ?? null) === 42, 'No se recupera sesión PHP persistida.');
    session_regenerate_id(true);
    $new = session_id();
    $ids[] = $new;
    sessionCheck($new !== $old && preg_match('/^[a-f0-9]{64}$/D', $new) === 1, 'No se regeneró un ID seguro al iniciar sesión.');
    session_write_close();
    sessionCheck(!$first->validateId($old), 'La sesión anterior sobrevive a su regeneración.');
    $first->close();
    sessionCheck($first->validateId($new), 'La sesión regenerada no persiste.');
    $first->close();

    $counterId = freshId();
    $first->write($counterId, '0');
    $first->close();
    $workers = [];
    for ($index = 0; $index < 2; $index++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $counterId], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('No se pudo arrancar un proceso de prueba.');
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    foreach ($workers as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        sessionCheck(proc_close($process) === 0, 'Falló un proceso de sesión concurrente: ' . $stdout . $stderr);
    }
    sessionCheck($first->read($counterId) === '2', 'Se perdió una escritura concurrente de sesión.');
    $first->close();

    $database->beginTransaction();
    $refused = false;
    try { $first->write($counterId, 'rollback-data'); } catch (RuntimeException) { $refused = true; }
    $database->rollBack();
    $first->close();
    sessionCheck($refused, 'Una transacción app puede revertir el guardado de sesión.');
    echo "OK MySQL sessions: $checks checks, independent connections and concurrent PHP processes.\n";
} finally {
    if ($database->inTransaction()) $database->rollBack();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $first->close(); $second->close();
    $delete = $database->prepare('DELETE FROM app_sessions WHERE session_key = ?');
    foreach ($ids as $id) $delete->execute([hash('sha256', $id)]);
}
