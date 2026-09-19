<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['idUser'])) {
    $_SESSION['flash'] = 'Debes iniciar sesión para acceder a esta página.';
    header('Location: login.php');
    exit;
}

if (($_SESSION['rol'] ?? null) !== 'admin') {
    $_SESSION['flash'] = 'No tienes permiso para crear noticias.';
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/conexion.php';

if (empty($_SESSION['csrf_crear_noticia'])) {
    $_SESSION['csrf_crear_noticia'] = bin2hex(random_bytes(32));
}

$datos = ['titulo' => '', 'texto' => '', 'fecha' => date('Y-m-d')];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos['titulo'] = trim((string) ($_POST['titulo'] ?? ''));
    $datos['texto'] = trim((string) ($_POST['texto'] ?? ''));
    $datos['fecha'] = trim((string) ($_POST['fecha'] ?? ''));
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_crear_noticia'], $token)) {
        $errores[] = 'La solicitud no es válida. Inténtalo de nuevo.';
    }
    if ($datos['titulo'] === '' || $datos['texto'] === '' || $datos['fecha'] === '') {
        $errores[] = 'Todos los campos son obligatorios.';
    }
    if (mb_strlen($datos['titulo']) > 255) {
        $errores[] = 'El título no puede superar los 255 caracteres.';
    }
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $datos['fecha']);
    if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha']) {
        $errores[] = 'La fecha de publicación no es válida.';
    }

    $imagen = $_FILES['imagen'] ?? null;
    if (!is_array($imagen) || ($imagen['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errores[] = 'Debes seleccionar una imagen.';
    } elseif (($imagen['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errores[] = 'No se ha podido subir la imagen.';
    } elseif (($imagen['size'] ?? 0) > 5 * 1024 * 1024) {
        $errores[] = 'La imagen no puede superar los 5 MB.';
    }

    $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $extension = null;
    if (!$errores && is_uploaded_file($imagen['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $tipo = $finfo->file($imagen['tmp_name']);
        $infoImagen = @getimagesize($imagen['tmp_name']);
        if ($infoImagen === false || !isset($tiposPermitidos[$tipo])) {
            $errores[] = 'La imagen debe ser JPG, PNG o WEBP.';
        } else {
            $extension = $tiposPermitidos[$tipo];
        }
    } elseif (!$errores) {
        $errores[] = 'El archivo de imagen recibido no es válido.';
    }

    if (!$errores) {
        $directorioSubidas = __DIR__ . '/uploads/noticias';
        if (!is_dir($directorioSubidas) && !mkdir($directorioSubidas, 0755, true) && !is_dir($directorioSubidas)) {
            $errores[] = 'No se ha podido preparar el directorio para la imagen.';
        } else {
            $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
            $rutaFisica = $directorioSubidas . '/' . $nombreArchivo;
            $rutaPublica = 'uploads/noticias/' . $nombreArchivo;

            if (!move_uploaded_file($imagen['tmp_name'], $rutaFisica)) {
                $errores[] = 'No se ha podido guardar la imagen.';
            } else {
                try {
                    $insertar = $pdo->prepare(
                        'INSERT INTO noticias (titulo, imagen, texto, fecha, idUser) VALUES (?, ?, ?, ?, ?)'
                    );
                    $insertar->execute([
                        $datos['titulo'],
                        $rutaPublica,
                        $datos['texto'],
                        $datos['fecha'],
                        (int) $_SESSION['idUser'],
                    ]);

                    unset($_SESSION['csrf_crear_noticia']);
                    $_SESSION['flash'] = 'La noticia se ha publicado correctamente.';
                    header('Location: noticias.php');
                    exit;
                } catch (PDOException $exception) {
                    if (is_file($rutaFisica)) {
                        unlink($rutaFisica);
                    }
                    $errores[] = 'No se ha podido publicar la noticia. El título podría estar repetido.';
                }
            }
        }
    }
}

$tituloPagina = 'Crear noticia | Salud Cercana';
require __DIR__ . '/includes/header.php';
?>
<main class="form-page">
    <section class="form-card" aria-labelledby="titulo-crear-noticia">
        <p class="eyebrow">Administración</p>
        <h1 id="titulo-crear-noticia">Crear noticia</h1>
        <p>Completa los datos para publicar una noticia en Salud Cercana.</p>
        <?php foreach ($errores as $error): ?>
            <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; ?>
        <form method="POST" action="crear_noticia.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_crear_noticia'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-grid">
                <label class="full-width">Título de la noticia
                    <input name="titulo" maxlength="255" required value="<?= htmlspecialchars($datos['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Fecha de publicación
                    <input name="fecha" type="date" required value="<?= htmlspecialchars($datos['fecha'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Imagen (JPG, PNG o WEBP; máx. 5 MB)
                    <input name="imagen" type="file" accept="image/jpeg,image/png,image/webp" required>
                </label>
                <label class="full-width">Texto de la noticia
                    <textarea name="texto" required><?= htmlspecialchars($datos['texto'], ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>
            </div>
            <button class="button" type="submit">Publicar noticia</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
