<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/environment.php';

/** MySQL is the shared store; no session is written to ephemeral function disk. */
final class SaludMysqlSessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    private ?string $lockedKey = null;
    private string $lockPrefix;

    public function __construct(private PDO $database, private int $ttl = 1800, private int $lockWait = 5)
    {
        if ($ttl < 60 || $ttl > 86400 || $lockWait < 0 || $lockWait > 10) {
            throw new InvalidArgumentException('La configuración de sesión no es válida.');
        }
        // GET_LOCK names are server-wide. Scope them to the selected database.
        $schema = (string) $database->query('SELECT DATABASE()')->fetchColumn();
        $this->lockPrefix = substr(hash('sha256', $schema), 0, 16);
    }

    public function open(string $path, string $name): bool { return true; }

    public function close(): bool
    {
        $this->release();
        return true;
    }

    public function create_sid(): string { return bin2hex(random_bytes(32)); }

    public function validateId(string $id): bool
    {
        if (!$this->validSyntax($id)) return false;
        $key = $this->acquire($id);
        try {
            $statement = $this->database->prepare('SELECT 1 FROM app_sessions WHERE session_key = ? AND expires_at > UNIX_TIMESTAMP()');
            $statement->execute([$key]);
            return $statement->fetchColumn() !== false;
        } catch (Throwable) {
            throw new RuntimeException('No se ha podido validar la sesión.');
        }
    }

    public function read(string $id): string|false
    {
        $key = $this->acquire($id);
        try {
            $statement = $this->database->prepare('SELECT data FROM app_sessions WHERE session_key = ? AND expires_at > UNIX_TIMESTAMP()');
            $statement->execute([$key]);
            $value = $statement->fetchColumn();
            return $value === false ? '' : (string) $value;
        } catch (Throwable) {
            throw new RuntimeException('No se ha podido leer la sesión.');
        }
    }

    public function write(string $id, string $data): bool
    {
        $key = $this->acquire($id);
        try {
            // Session changes must never be rolled back with an app transaction.
            if ($this->database->inTransaction()) {
                throw new RuntimeException('Hay una transacción pendiente al guardar la sesión.');
            }
            $statement = $this->database->prepare(
                'INSERT INTO app_sessions (session_key, data, expires_at) VALUES (?, ?, UNIX_TIMESTAMP() + ?) '
                . 'ON DUPLICATE KEY UPDATE data = ?, expires_at = UNIX_TIMESTAMP() + ?'
            );
            $statement->execute([$key, $data, $this->ttl, $data, $this->ttl]);
            return true;
        } catch (Throwable) {
            throw new RuntimeException('No se ha podido guardar la sesión.');
        }
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        // Upsert also restores a row removed by expiry cleanup during a request.
        return $this->write($id, $data);
    }

    public function destroy(string $id): bool
    {
        $key = $this->acquire($id);
        try {
            if ($this->database->inTransaction()) {
                throw new RuntimeException('Hay una transacción pendiente al cerrar la sesión.');
            }
            $statement = $this->database->prepare('DELETE FROM app_sessions WHERE session_key = ?');
            $statement->execute([$key]);
            return true;
        } catch (Throwable) {
            throw new RuntimeException('No se ha podido cerrar la sesión.');
        } finally {
            $this->release();
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            if ($this->database->inTransaction()) return 0;
            // Bound work so cleanup cannot consume an entire function request.
            return $this->database->exec('DELETE FROM app_sessions WHERE expires_at <= UNIX_TIMESTAMP() LIMIT 100');
        } catch (Throwable) {
            throw new RuntimeException('No se ha podido limpiar las sesiones caducadas.');
        }
    }

    private function validSyntax(string $id): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/D', $id) === 1;
    }

    private function acquire(string $id): string
    {
        if (!$this->validSyntax($id)) {
            throw new RuntimeException('El identificador de sesión no es válido.');
        }
        $key = hash('sha256', $id);
        if ($this->lockedKey === $key) return $key;
        $this->release();
        try {
            $statement = $this->database->prepare('SELECT GET_LOCK(?, ?)');
            $statement->execute([$this->lockName($key), $this->lockWait]);
            if ((string) $statement->fetchColumn() !== '1') {
                throw new RuntimeException('La sesión está ocupada.');
            }
            $this->lockedKey = $key;
            return $key;
        } catch (Throwable) {
            throw new RuntimeException('No se ha podido bloquear la sesión. Inténtalo de nuevo.');
        }
    }

    private function release(): void
    {
        if ($this->lockedKey === null) return;
        $key = $this->lockedKey;
        $this->lockedKey = null;
        try {
            $statement = $this->database->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$this->lockName($key)]);
        } catch (Throwable) {
            throw new RuntimeException('No se ha podido liberar la sesión.');
        }
    }

    private function lockName(string $key): string
    {
        return 'sc:' . $this->lockPrefix . ':' . substr($key, 0, 44);
    }
}

function saludStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (headers_sent()) throw new RuntimeException('No se ha podido iniciar la sesión.');
    $ttl = saludEnv('SESSION_TTL', '1800');
    if ($ttl === null || !ctype_digit($ttl) || (int) $ttl < 60 || (int) $ttl > 86400) {
        throw new RuntimeException('La duración de sesión no es válida.');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', $ttl);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_name(saludIsLocal() ? 'salud_session' : '__Host-salud_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => !saludIsLocal(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    $handler = new SaludMysqlSessionHandler(saludDatabase(), (int) $ttl);
    session_set_save_handler($handler, true);
    if (!session_start()) throw new RuntimeException('No se ha podido iniciar la sesión.');
}

// Tests may define this flag before requiring this file to instantiate the handler.
if (!defined('SALUD_SESSION_NO_AUTOSTART')) saludStartSession();
