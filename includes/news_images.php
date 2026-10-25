<?php
declare(strict_types=1);

// Images are immutable and live in MySQL, never in a function's temporary disk.
// Keep this below Vercel's complete request limit, which also includes the form.
const NEWS_IMAGE_MAX_BYTES = 2 * 1024 * 1024;
const NEWS_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/** Validate bytes independently of a browser-supplied filename or Content-Type. */
function newsImageInspectFile(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new InvalidArgumentException('El archivo de imagen recibido no es válido.');
    }
    $data = file_get_contents($path, false, null, 0, NEWS_IMAGE_MAX_BYTES + 1);
    if ($data === false || $data === '' || strlen($data) > NEWS_IMAGE_MAX_BYTES) {
        throw new InvalidArgumentException('La imagen debe pesar entre 1 byte y 2 MiB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
    $dimensions = @getimagesizefromstring($data);
    if (!in_array($mime, NEWS_IMAGE_MIME_TYPES, true)
        || $dimensions === false
        || ($dimensions['mime'] ?? null) !== $mime
        || $dimensions[0] < 1 || $dimensions[1] < 1) {
        throw new InvalidArgumentException('El archivo debe ser una imagen JPG, PNG o WEBP válida.');
    }
    return ['data' => $data, 'mime' => $mime];
}

/** Must run inside the same transaction as the news INSERT/UPDATE. */
function newsImageStore(PDO $pdo, array $image): string
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('La imagen debe guardarse en la transacción de la noticia.');
    }
    $id = bin2hex(random_bytes(16));
    $statement = $pdo->prepare(
        'INSERT INTO app_news_images (id, mime_type, byte_size, sha256, image_data) VALUES (?, ?, ?, ?, ?)'
    );
    $statement->bindValue(1, $id);
    $statement->bindValue(2, $image['mime']);
    $statement->bindValue(3, strlen($image['data']), PDO::PARAM_INT);
    $statement->bindValue(4, hash('sha256', $image['data']));
    $statement->bindValue(5, $image['data'], PDO::PARAM_LOB);
    $statement->execute();
    return 'imagen.php?id=' . $id;
}

function newsImageUpload(PDO $pdo, mixed $file): string
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Debes seleccionar una imagen.');
    }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No se ha podido recibir la imagen. El máximo es 2 MiB.');
    }
    if (!is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('El archivo de imagen recibido no es válido.');
    }
    return newsImageStore($pdo, newsImageInspectFile($file['tmp_name']));
}

function newsImageIdValid(mixed $id): bool
{
    return is_string($id) && preg_match('/\A[a-f0-9]{32}\z/D', $id) === 1;
}

function newsImageEtagMatches(string $header, string $etag): bool
{
    foreach (explode(',', $header) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '*' || $candidate === $etag || $candidate === 'W/' . $etag) {
            return true;
        }
    }
    return false;
}
