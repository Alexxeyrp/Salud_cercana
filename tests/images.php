<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/news_images.php';

// Offline fixtures only: no network connection and no application/user database.
$checks = 0;
function checkImage(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function imageRejects(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException $exception) {
        checkImage(true, $message);
        return;
    }
    throw new RuntimeException($message);
}
$temp = tempnam(sys_get_temp_dir(), 'news-image-test-');
if ($temp === false) {
    throw new RuntimeException('Cannot create fixture.');
}
try {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
    file_put_contents($temp, $png);
    $image = newsImageInspectFile($temp);
    checkImage($image['mime'] === 'image/png' && $image['data'] === $png, 'PNG bytes must survive validation.');
    imageRejects(fn() => newsImageUpload(new PDO('sqlite::memory:'), [
        'tmp_name' => $temp, 'error' => UPLOAD_ERR_OK, 'size' => 1,
    ]), 'A local path must never masquerade as an HTTP upload.');
    imageRejects(fn() => newsImageUpload(new PDO('sqlite::memory:'), null), 'A required image must exist.');
    imageRejects(fn() => newsImageUpload(new PDO('sqlite::memory:'), ['error' => UPLOAD_ERR_INI_SIZE]), 'PHP upload errors must fail safely.');
    file_put_contents($temp, '<?php echo "not an image"; ?>');
    imageRejects(fn() => newsImageInspectFile($temp), 'An executable renamed JPG must fail.');
    file_put_contents($temp, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    imageRejects(fn() => newsImageInspectFile($temp), 'SVG must fail.');
    file_put_contents($temp, '');
    imageRejects(fn() => newsImageInspectFile($temp), 'An empty file must fail.');
    file_put_contents($temp, $png . str_repeat('x', NEWS_IMAGE_MAX_BYTES));
    imageRejects(fn() => newsImageInspectFile($temp), 'Actual bytes, not a browser size claim, enforce 2 MiB.');
    file_put_contents($temp, $png . str_repeat('x', NEWS_IMAGE_MAX_BYTES - strlen($png)));
    checkImage(strlen(newsImageInspectFile($temp)['data']) === NEWS_IMAGE_MAX_BYTES, 'The exact size boundary must work.');

    checkImage(newsImageIdValid(str_repeat('a', 32)), 'Valid opaque ID rejected.');
    foreach (['../config/conexion.php', str_repeat('a', 33), str_repeat('a', 31), [], null, "\n" . str_repeat('a', 32)] as $badId) {
        checkImage(!newsImageIdValid($badId), 'Malformed ID accepted.');
    }
    checkImage(newsImageEtagMatches('"old", W/"test"', '"test"'), 'Weak conditional ETag must match for GET.');
    checkImage(newsImageEtagMatches('*', '"test"'), 'Wildcard ETag must match.');
    checkImage(!newsImageEtagMatches('"different"', '"test"'), 'Distinct ETag must not match.');

    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE app_news_images (id TEXT PRIMARY KEY, mime_type TEXT NOT NULL, byte_size INTEGER NOT NULL, sha256 TEXT NOT NULL, image_data BLOB NOT NULL)');
    $pdo->exec('CREATE TABLE noticias (titulo TEXT UNIQUE NOT NULL, imagen TEXT NOT NULL)');
    try {
        newsImageStore($pdo, $image);
        throw new RuntimeException('Image storage outside a transaction must fail.');
    } catch (LogicException $exception) {
        checkImage(true, 'Transaction is mandatory.');
    }
    $pdo->beginTransaction();
    $oldUrl = newsImageStore($pdo, $image);
    $query = $pdo->prepare('INSERT INTO noticias (titulo, imagen) VALUES (?, ?)');
    $query->execute(['fixture-news', $oldUrl]);
    $pdo->commit();
    checkImage(strlen($oldUrl) <= 255 && newsImageIdValid(substr($oldUrl, strlen('imagen.php?id='))), 'Stored URL must fit noticias.imagen.');
    $stored = $pdo->query('SELECT * FROM app_news_images')->fetch(PDO::FETCH_ASSOC);
    checkImage($stored['image_data'] === $png && (int) $stored['byte_size'] === strlen($png), 'Binary data and size must round-trip.');
    checkImage($stored['sha256'] === hash('sha256', $png), 'ETag hash must match the stored bytes.');

    $pdo->beginTransaction();
    $failedUrl = newsImageStore($pdo, $image);
    try {
        $query->execute(['fixture-news', $failedUrl]);
        throw new RuntimeException('Duplicate fixture title must fail.');
    } catch (PDOException $exception) {
        $pdo->rollBack();
    }
    checkImage((int) $pdo->query('SELECT COUNT(*) FROM app_news_images')->fetchColumn() === 1, 'Failed news insertion must roll back only its new BLOB.');
    checkImage($pdo->query('SELECT imagen FROM noticias')->fetchColumn() === $oldUrl, 'A failed replacement must retain the original URL.');
    $pdo->beginTransaction();
    $newUrl = newsImageStore($pdo, $image);
    $query = $pdo->prepare('UPDATE noticias SET imagen = ?');
    $query->execute([$newUrl]);
    $pdo->commit();
    checkImage($oldUrl !== $newUrl, 'Even identical content must receive a new immutable ID.');
    checkImage((int) $pdo->query('SELECT COUNT(*) FROM app_news_images')->fetchColumn() === 2, 'Replacement must retain the historical BLOB.');
    $pdo->exec('DELETE FROM noticias');
    checkImage((int) $pdo->query('SELECT COUNT(*) FROM app_news_images')->fetchColumn() === 2, 'News deletion must retain historical BLOBs.');
    echo "Images: {$checks} offline checks passed.\n";
} finally {
    if (is_file($temp)) {
        unlink($temp);
    }
}
