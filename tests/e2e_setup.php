<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/environment.php';

/** Refuse every database except an explicitly enabled local disposable fixture. */
function e2eDatabase(): PDO
{
    if (getenv('SALUD_TEST_DB') !== '1' || !saludIsLocal()
        || !in_array(getenv('DB_HOST'), ['127.0.0.1', 'localhost', '::1'], true)
        || in_array((string) getenv('DB_PORT'), ['3306', '3307'], true)
        || !str_starts_with((string) getenv('DB_NAME'), 'salud_test_')) {
        throw new RuntimeException('E2E requires an explicitly enabled local salud_test_ database.');
    }
    return saludDatabase();
}

function e2ePrepareSchema(): PDO
{
    $pdo = e2eDatabase();
    $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $baseTables = ['citas', 'noticias', 'users_data', 'users_login'];
    $present = array_intersect($baseTables, $existing);
    if ($present && count($present) !== count($baseTables)) {
        throw new RuntimeException('Partial fixture schema: choose a fresh isolated test database.');
    }
    if (!$present) {
        $schema = file_get_contents(__DIR__ . '/../sitio_web.sql');
        $statements = preg_replace('/--[^\r\n]*/', '', $schema);
        if (preg_match('/\b(?:DROP|TRUNCATE|DELETE|INSERT|REPLACE)\b/i', $statements)) {
            throw new RuntimeException('Fixture setup accepts only a schema without seed data or destructive statements.');
        }
        try {
            $pdo->exec($schema);
        } catch (PDOException) {
            throw new RuntimeException('Could not initialize the fixture schema. Its setup account needs CREATE and ALTER; complete any partial schema before retrying.');
        }
    }
    $identityColumns = ['citas' => 'idCita', 'noticias' => 'idNoticia', 'users_data' => 'idUser', 'users_login' => 'idLogin'];
    $query = $pdo->prepare('SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    foreach ($identityColumns as $table => $column) {
        $query->execute([$table, $column]);
        if (!str_contains((string) $query->fetchColumn(), 'auto_increment')) {
            throw new RuntimeException('Incomplete fixture schema: expected identity columns and keys. Complete the additive schema before retrying.');
        }
    }
    foreach (['001_sessions.sql', '002_news_images.sql'] as $migration) {
        $pdo->exec(file_get_contents(__DIR__ . '/../database/' . $migration));
    }
    return $pdo;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    e2ePrepareSchema();
    echo "E2E isolated schema ready. No existing rows or tables removed.\n";
}
