<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid('logout', $_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('La solicitud no es válida.');
    }
    $_SESSION = [];
    session_regenerate_id(true);
    flash('Has cerrado sesión correctamente.');
    header('Location: login.php');
    exit;
}

$tituloPagina = 'Cerrar sesión | Salud Cercana';
require __DIR__ . '/includes/header.php';
?>
<main class="form-page"><section class="form-card form-card-small">
    <h1>Cerrar sesión</h1>
    <form method="post" action="logout.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken('logout')) ?>">
        <button class="button" type="submit">Confirmar cierre de sesión</button>
    </form>
</section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
