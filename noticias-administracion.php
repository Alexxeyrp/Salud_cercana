<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/news_images.php';

$errors = [];
$message = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['idNoticia'] ?? 0);
    if (!csrfValid('admin_news', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'La solicitud no es válida.';
    } elseif (!in_array($action, ['create', 'update', 'delete'], true)) {
        $errors[] = 'La acción no es válida.';
    } elseif ($action !== 'create' && $id < 1) {
        $errors[] = 'La noticia no es válida.';
    } elseif ($action === 'delete') {
        try {
            // Keep historical image files/BLOBs: public URLs may still be in use.
            $query = $pdo->prepare('DELETE FROM noticias WHERE idNoticia = ?');
            $query->execute([$id]);
            if ($query->rowCount() === 0) {
                $errors[] = 'La noticia no existe.';
            } else {
                flash('Noticia eliminada.');
                header('Location: noticias-administracion.php');
                exit;
            }
        } catch (PDOException $exception) {
            $errors[] = 'No se pudo eliminar la noticia.';
        }
    } else {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $texto = trim((string) ($_POST['texto'] ?? ''));
        $fecha = trim((string) ($_POST['fecha'] ?? ''));
        if ($titulo === '' || $texto === '' || !validDate($fecha, false)) {
            $errors[] = 'Título, texto y fecha válida son obligatorios.';
        }
        if (mb_strlen($titulo) > 255) {
            $errors[] = 'El título no puede superar 255 caracteres.';
        }
        $file = $_FILES['imagen'] ?? null;
        $hasImage = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($action === 'create' && !$hasImage) {
            $errors[] = 'Debes seleccionar una imagen.';
        }
        // No uploads or writes take place until every form/CSRF/action check passes.
        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $imagePath = null;
                if ($action === 'update') {
                    // Lock the row while preserving its old image unless replaced.
                    $query = $pdo->prepare('SELECT imagen FROM noticias WHERE idNoticia = ? FOR UPDATE');
                    $query->execute([$id]);
                    $old = $query->fetch();
                    if (!$old) {
                        throw new InvalidArgumentException('La noticia no existe.');
                    }
                    $imagePath = $old['imagen'];
                }
                if ($hasImage) {
                    $imagePath = newsImageUpload($pdo, $file);
                }
                if ($action === 'create') {
                    $query = $pdo->prepare('INSERT INTO noticias (titulo, imagen, texto, fecha, idUser) VALUES (?, ?, ?, ?, ?)');
                    $query->execute([$titulo, $imagePath, $texto, $fecha, (int) $_SESSION['idUser']]);
                } else {
                    $query = $pdo->prepare('UPDATE noticias SET titulo = ?, imagen = ?, texto = ?, fecha = ? WHERE idNoticia = ?');
                    $query->execute([$titulo, $imagePath, $texto, $fecha, $id]);
                }
                $pdo->commit();
                flash($action === 'create' ? 'Noticia publicada.' : 'Noticia actualizada.');
                header('Location: noticias-administracion.php');
                exit;
            } catch (InvalidArgumentException | PDOException $exception) {
                if ($pdo->inTransaction()) {
                    // This only rolls back the image created in this operation.
                    $pdo->rollBack();
                }
                $errors[] = $exception instanceof InvalidArgumentException ? $exception->getMessage()
                    : 'No se pudo guardar la noticia. Revisa que el título sea único y la migración de imágenes esté aplicada.';
            }
        }
    }
}
$news = $pdo->query('SELECT n.*, d.nombre, d.apellidos FROM noticias n INNER JOIN users_data d ON d.idUser = n.idUser ORDER BY n.fecha DESC')->fetchAll();
$editId = (int) ($_GET['editar'] ?? 0);
$editing = null;
foreach ($news as $item) {
    if ((int) $item['idNoticia'] === $editId) {
        $editing = $item;
    }
}
$tituloPagina = 'Administración de noticias | Salud Cercana';
require __DIR__ . '/includes/header.php';
?>
<main class="content-section">
    <div class="section-heading"><p class="eyebrow">Administración</p><h1>Noticias</h1></div>
    <?php if ($message): ?><p class="alert alert-success"><?= e($message) ?></p><?php endif; ?>
    <?php foreach ($errors as $error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endforeach; ?>
    <section class="form-card">
        <h2><?= $editing ? 'Editar noticia' : 'Crear noticia' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken('admin_news')) ?>">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?><input type="hidden" name="idNoticia" value="<?= (int) $editing['idNoticia'] ?>"><?php endif; ?>
            <div class="form-grid">
                <label class="full-width">Título<input name="titulo" maxlength="255" required value="<?= e($editing['titulo'] ?? '') ?>"></label>
                <label>Fecha<input type="date" name="fecha" required value="<?= e($editing['fecha'] ?? date('Y-m-d')) ?>"></label>
                <label>Imagen (JPG, PNG o WEBP; máximo 2 MiB)<input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" <?= $editing ? '' : 'required' ?>></label>
                <label class="full-width">Texto<textarea name="texto" required><?= e($editing['texto'] ?? '') ?></textarea></label>
            </div>
            <button class="button"><?= $editing ? 'Guardar cambios' : 'Publicar noticia' ?></button>
            <?php if ($editing): ?> <a href="noticias-administracion.php">Cancelar</a><?php endif; ?>
        </form>
    </section>
    <section class="spaced-card">
        <h2>Noticias publicadas</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Imagen</th><th>Título</th><th>Fecha</th><th>Autor</th><th>Acciones</th></tr></thead>
            <tbody><?php foreach ($news as $item): ?><tr>
                <td><img class="thumb" src="<?= e($item['imagen']) ?>" alt=""></td>
                <td><?= e($item['titulo']) ?></td><td><?= e($item['fecha']) ?></td>
                <td><?= e($item['nombre'] . ' ' . $item['apellidos']) ?></td>
                <td><a href="noticias-administracion.php?editar=<?= (int) $item['idNoticia'] ?>">Editar</a>
                    <form class="inline-form" method="post" onsubmit="return confirm('¿Eliminar esta noticia?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken('admin_news')) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="idNoticia" value="<?= (int) $item['idNoticia'] ?>">
                        <button class="link-button">Eliminar</button>
                    </form>
                </td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
