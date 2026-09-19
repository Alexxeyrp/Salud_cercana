<?php
declare(strict_types=1);
require_once __DIR__ . '/config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$campos = ['nombre', 'apellidos', 'email', 'telefono', 'fecha_nacimiento', 'direccion', 'sexo', 'usuario'];
$datos = array_fill_keys($campos, '');
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($campos as $campo) {
        $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $contrasena = (string) ($_POST['password'] ?? '');

    if (in_array('', $datos, true) || $contrasena === '') {
        $errores[] = 'Todos los campos son obligatorios.';
    }
    if ($datos['email'] !== '' && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El formato del email no es válido.';
    }
    if ($datos['nombre'] !== '' && !preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} '-]{1,79}$/u", $datos['nombre'])) {
        $errores[] = 'El nombre debe tener entre 2 y 80 caracteres y solo puede contener letras, espacios, apóstrofes y guiones.';
    }
    if ($datos['apellidos'] !== '' && !preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} '-]{1,119}$/u", $datos['apellidos'])) {
        $errores[] = 'Los apellidos deben tener entre 2 y 120 caracteres y solo pueden contener letras, espacios, apóstrofes y guiones.';
    }
    if ($datos['telefono'] !== '' && !preg_match('/^[0-9]{9}$/', $datos['telefono'])) {
        $errores[] = 'El teléfono debe contener exactamente 9 dígitos.';
    }
    if ($datos['fecha_nacimiento'] !== '') {
        $fechaNacimiento = DateTimeImmutable::createFromFormat('!Y-m-d', $datos['fecha_nacimiento']);
        $erroresFecha = DateTimeImmutable::getLastErrors();
        $fechaValida = $fechaNacimiento instanceof DateTimeImmutable
            && ($erroresFecha === false || ($erroresFecha['warning_count'] === 0 && $erroresFecha['error_count'] === 0))
            && $fechaNacimiento->format('Y-m-d') === $datos['fecha_nacimiento'];

        if (!$fechaValida) {
            $errores[] = 'La fecha de nacimiento no es válida.';
        } elseif ($fechaNacimiento > new DateTimeImmutable('today')) {
            $errores[] = 'La fecha de nacimiento no puede ser futura.';
        }
    }
    if ($datos['usuario'] !== '' && !preg_match('/^[A-Za-z0-9_]{3,30}$/', $datos['usuario'])) {
        $errores[] = 'El nombre de usuario debe tener entre 3 y 30 caracteres y solo puede contener letras, números y guion bajo, sin espacios.';
    }
    if ($contrasena !== '' && mb_strlen($contrasena, 'UTF-8') < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    }
    if ($datos['sexo'] !== '' && !in_array($datos['sexo'], ['femenino', 'masculino', 'otro'], true)) {
        $errores[] = 'El sexo indicado no es válido.';
    }

    if (!$errores) {
        try {
            $comprobarEmail = $pdo->prepare('SELECT 1 FROM users_data WHERE email = ?');
            $comprobarEmail->execute([$datos['email']]);
            $comprobarUsuario = $pdo->prepare('SELECT 1 FROM users_login WHERE usuario = ?');
            $comprobarUsuario->execute([$datos['usuario']]);
            if ($comprobarEmail->fetchColumn()) $errores[] = 'El email ya existe.';
            if ($comprobarUsuario->fetchColumn()) $errores[] = 'El nombre de usuario ya existe.';

            if (!$errores) {
                $pdo->beginTransaction();
                $insertarDatos = $pdo->prepare('INSERT INTO users_data (nombre, apellidos, email, telefono, fecha_nacimiento, direccion, sexo) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $insertarDatos->execute([$datos['nombre'], $datos['apellidos'], $datos['email'], $datos['telefono'], $datos['fecha_nacimiento'], $datos['direccion'], $datos['sexo']]);
                $idUser = (int) $pdo->lastInsertId();

                $insertarLogin = $pdo->prepare('INSERT INTO users_login (idUser, usuario, password, rol) VALUES (?, ?, ?, ?)');
                $insertarLogin->execute([$idUser, $datos['usuario'], password_hash($contrasena, PASSWORD_DEFAULT), 'user']);
                $pdo->commit();

                $_SESSION['flash'] = 'Registro realizado correctamente. Ya puedes iniciar sesión.';
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = 'Ha ocurrido un error durante el registro. Inténtalo de nuevo.';
        }
    }
}

$tituloPagina = 'Registro | Salud Cercana';
require __DIR__ . '/includes/header.php';
?>
<main class="form-page">
    <section class="form-card" aria-labelledby="titulo-registro">
        <p class="eyebrow">Crear cuenta</p>
        <h1 id="titulo-registro">Regístrate</h1>
        <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a>.</p>
        <?php foreach ($errores as $error): ?>
            <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; ?>
        <form method="post" action="registro.php">
            <div class="form-grid">
                <label>Nombre<input name="nombre" required value="<?= htmlspecialchars($datos['nombre'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Apellidos<input name="apellidos" required value="<?= htmlspecialchars($datos['apellidos'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Email<input name="email" type="email" required value="<?= htmlspecialchars($datos['email'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Teléfono<input name="telefono" type="tel" required value="<?= htmlspecialchars($datos['telefono'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Fecha de nacimiento<input name="fecha_nacimiento" type="date" required value="<?= htmlspecialchars($datos['fecha_nacimiento'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Sexo<select name="sexo" required><option value="">Selecciona una opción</option><?php foreach (['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'] as $valor => $texto): ?><option value="<?= $valor ?>" <?= $datos['sexo'] === $valor ? 'selected' : '' ?>><?= $texto ?></option><?php endforeach; ?></select></label>
                <label class="full-width">Dirección<input name="direccion" required value="<?= htmlspecialchars($datos['direccion'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Nombre de usuario<input name="usuario" required value="<?= htmlspecialchars($datos['usuario'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Contraseña<input name="password" type="password" required></label>
            </div>
            <button class="button" type="submit">Crear cuenta</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
