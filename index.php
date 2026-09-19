<?php
declare(strict_types=1);
$tituloPagina = 'Inicio | Salud Cercana';
require __DIR__ . '/includes/header.php';
$mensaje = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>
<main>
    <?php if ($mensaje): ?>
        <div class="content-section flash-container"><p class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p></div>
    <?php endif; ?>
    <section class="hero">
        <div class="hero-content">
            <p class="eyebrow">Cuidamos de ti</p>
            <h1>Tu salud, más cerca que nunca.</h1>
            <p>Información clara, atención humana y recursos para acompañarte en cada paso.</p>
            <a class="button" href="noticias.php">Ver noticias</a>
        </div>
    </section>

    <section class="content-section" aria-labelledby="servicios">
        <div class="section-heading">
            <p class="eyebrow">Nuestros valores</p>
            <h2 id="servicios">Atención pensada para las personas</h2>
        </div>
        <div class="card-grid">
            <article class="info-card">
                <h3>Información fiable</h3>
                <p>Consulta las novedades y consejos publicados por nuestro equipo.</p>
            </article>
            <article class="info-card">
                <h3>Gestión sencilla</h3>
                <p>Crea tu cuenta para mantener tus datos de acceso organizados.</p>
            </article>
            <article class="info-card">
                <h3>Cerca de ti</h3>
                <p>Un espacio digital limpio y accesible para cuidar de tu bienestar.</p>
            </article>
        </div>
    </section>

    <section class="cta-section">
        <div>
            <h2>¿Aún no tienes una cuenta?</h2>
            <p>Regístrate en unos minutos para formar parte de Salud Cercana.</p>
        </div>
        <?php if (empty($_SESSION['idUser'])): ?>
            <a class="button button-light" href="registro.php">Crear cuenta</a>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
