<?php
declare(strict_types=1);
require_once __DIR__ . '/config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$usuario = '';
$mensaje = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $contrasena = (string) ($_POST['password'] ?? '');

    if ($usuario === '' || $contrasena === '') {
        $error = 'Usuario y contraseña son obligatorios.';
    } else {
        $consulta = $pdo->prepare('SELECT idUser, usuario, password, rol FROM users_login WHERE usuario = ?');
        $consulta->execute([$usuario]);
        $cuenta = $consulta->fetch();

        if (!$cuenta || !password_verify($contrasena, $cuenta['password'])) {
            $error = 'Usuario o contraseña incorrectos.';
        } else {
            session_regenerate_id(true);
            $_SESSION['idUser'] = (int) $cuenta['idUser'];
            $_SESSION['usuario'] = $cuenta['usuario'];
            $_SESSION['rol'] = $cuenta['rol'];
            $_SESSION['flash'] = 'Has iniciado sesión correctamente.';
            header('Location: index.php');
            exit;
        }
    }
}

$tituloPagina = 'Login | Salud Cercana';
require __DIR__ . '/includes/header.php';
?>
<main class="form-page">
    <section class="form-card form-card-small" aria-labelledby="titulo-login">
        <p class="eyebrow">Bienvenido de nuevo</p>
        <h1 id="titulo-login">Inicia sesión</h1>
        <p>¿Todavía no tienes cuenta? <a href="registro.php">Regístrate</a>.</p>
        <?php if ($mensaje): ?><p class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <form method="post" action="login.php">
            <label>Usuario<input name="usuario" required value="<?= htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') ?>"></label>
            <label>Contraseña<input name="password" type="password" required></label>
            <button class="button" type="submit">Entrar</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
