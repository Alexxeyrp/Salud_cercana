<?php
declare(strict_types=1);
require_once __DIR__ . '/config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensaje = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$tituloPagina = 'Noticias | Salud Cercana';
try {
    $consulta = $pdo->query(
        'SELECT noticias.titulo, noticias.fecha, noticias.texto, noticias.imagen,
                users_data.nombre, users_data.apellidos
         FROM noticias
         INNER JOIN users_data ON noticias.idUser = users_data.idUser
         ORDER BY noticias.fecha DESC'
    );
    $noticias = $consulta->fetchAll();
} catch (PDOException $exception) {
    $noticias = [];
    $errorNoticias = 'No se han podido cargar las noticias en este momento.';
}

require __DIR__ . '/includes/header.php';
?>
<main class="content-section">
    <?php if ($mensaje): ?>
        <p class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="section-heading">
        <p class="eyebrow">Actualidad</p>
        <h1>Noticias de Salud Cercana</h1>
        <p>Información y novedades publicadas por nuestro equipo.</p>
    </div>
    <?php if (isset($errorNoticias)): ?>
        <p class="alert alert-error"><?= htmlspecialchars($errorNoticias, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif (empty($noticias)): ?>
        <p class="empty-state">Todavía no hay noticias publicadas.</p>
    <?php else: ?>
        <div class="news-grid">
            <?php foreach ($noticias as $noticia): ?>
                <article class="news-card">
                    <img src="<?= htmlspecialchars($noticia['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagen de la noticia: <?= htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="news-card-content">
                        <p class="news-meta"><?= htmlspecialchars(date('d/m/Y', strtotime($noticia['fecha'])), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellidos'], ENT_QUOTES, 'UTF-8') ?></p>
                        <h2><?= htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p><?= nl2br(htmlspecialchars($noticia['texto'], ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
