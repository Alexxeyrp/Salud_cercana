<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/news_images.php';

$datos = ['titulo' => '', 'texto' => '', 'fecha' => date('Y-m-d')];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos['titulo'] = trim((string) ($_POST['titulo'] ?? ''));
    $datos['texto'] = trim((string) ($_POST['texto'] ?? ''));
    $datos['fecha'] = trim((string) ($_POST['fecha'] ?? ''));
    if (!csrfValid('crear_noticia', $_POST['csrf_token'] ?? null)) {
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

    if (!$errores) {
        try {
            $pdo->beginTransaction();
            $rutaPublica = newsImageUpload($pdo, $_FILES['imagen'] ?? null);
            $insertar = $pdo->prepare(
                'INSERT INTO noticias (titulo, imagen, texto, fecha, idUser) VALUES (?, ?, ?, ?, ?)'
            );
            $insertar->execute([
                $datos['titulo'], $rutaPublica, $datos['texto'], $datos['fecha'], (int) $_SESSION['idUser'],
            ]);
            $pdo->commit();
            unset($_SESSION['csrf_crear_noticia']);
            flash('La noticia se ha publicado correctamente.');
            header('Location: noticias.php');
            exit;
        } catch (InvalidArgumentException | PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = $exception instanceof InvalidArgumentException ? $exception->getMessage()
                : 'No se ha podido publicar la noticia. Revisa que el título sea único y la migración de imágenes esté aplicada.';
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
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken('crear_noticia')) ?>">
            <div class="form-grid">
                <label class="full-width">Título de la noticia
                    <input name="titulo" maxlength="255" required value="<?= htmlspecialchars($datos['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Fecha de publicación
                    <input name="fecha" type="date" required value="<?= htmlspecialchars($datos['fecha'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Imagen (JPG, PNG o WEBP; máx. 2 MiB)
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
