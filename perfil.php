<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/config/conexion.php';

$idUser = (int) $_SESSION['idUser'];
$errors = [];
$message = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$stmt = $pdo->prepare('SELECT d.*, l.usuario, l.password FROM users_data d INNER JOIN users_login l ON l.idUser=d.idUser WHERE d.idUser=?');
$stmt->execute([$idUser]);
$user = $stmt->fetch();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid('perfil', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'La solicitud no es válida.';
    } elseif (($_POST['action'] ?? '') === 'data') {
        $fields = ['nombre','apellidos','email','telefono','fecha_nacimiento','direccion','sexo'];
        $data = [];
        foreach ($fields as $field) $data[$field] = trim((string) ($_POST[$field] ?? ''));
        $errors = validatePersonalData($data);
        if (!$errors) {
            $email = $pdo->prepare('SELECT 1 FROM users_data WHERE email=? AND idUser<>?');
            $email->execute([$data['email'], $idUser]);
            if ($email->fetchColumn()) $errors[] = 'El email ya está siendo utilizado por otro usuario.';
        }
        if (!$errors) {
            $update = $pdo->prepare('UPDATE users_data SET nombre=?, apellidos=?, email=?, telefono=?, fecha_nacimiento=?, direccion=?, sexo=? WHERE idUser=?');
            $update->execute([...array_values($data), $idUser]);
            flash('Datos personales actualizados.'); header('Location: perfil.php'); exit;
        }
        $user = array_merge($user, $data);
    } elseif (($_POST['action'] ?? '') === 'password') {
        $current = (string) ($_POST['password_actual'] ?? '');
        $new = (string) ($_POST['password_nueva'] ?? '');
        $confirm = (string) ($_POST['password_confirmacion'] ?? '');
        if (!password_verify($current, $user['password'])) $errors[] = 'La contraseña actual no es correcta.';
        if (mb_strlen($new) < 8) $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        if ($new !== $confirm) $errors[] = 'La confirmación de contraseña no coincide.';
        if (!$errors) {
            $pdo->prepare('UPDATE users_login SET password=? WHERE idUser=?')->execute([password_hash($new, PASSWORD_DEFAULT), $idUser]);
            flash('Contraseña actualizada correctamente.'); header('Location: perfil.php'); exit;
        }
    }
}
$tituloPagina = 'Mi perfil | Salud Cercana'; require __DIR__ . '/includes/header.php';
?>
<main class="content-section"><div class="section-heading"><p class="eyebrow">Cuenta</p><h1>Mi perfil</h1><p>El nombre de usuario no se puede modificar.</p></div>
<?php if ($message): ?><p class="alert alert-success"><?= e($message) ?></p><?php endif; ?>
<?php foreach ($errors as $error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endforeach; ?>
<section class="form-card"><h2>Datos personales</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken('perfil')) ?>"><input type="hidden" name="action" value="data"><div class="form-grid">
<?php foreach (['nombre'=>'Nombre','apellidos'=>'Apellidos','email'=>'Email','telefono'=>'Teléfono','fecha_nacimiento'=>'Fecha de nacimiento','direccion'=>'Dirección'] as $field=>$label): ?><label<?= $field === 'direccion' ? ' class="full-width"' : '' ?>><?= $label ?><input name="<?= $field ?>" <?= $field === 'email' ? 'type="email"' : ($field === 'fecha_nacimiento' ? 'type="date"' : '') ?> required value="<?= e($user[$field]) ?>"></label><?php endforeach; ?>
<label>Sexo<select name="sexo" required><?php foreach (['femenino'=>'Femenino','masculino'=>'Masculino','otro'=>'Otro'] as $value=>$label): ?><option value="<?= $value ?>" <?= $user['sexo']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label>Usuario<input readonly value="<?= e($user['usuario']) ?>"></label></div><button class="button">Guardar datos</button></form></section>
<section class="form-card spaced-card"><h2>Cambiar contraseña</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken('perfil')) ?>"><input type="hidden" name="action" value="password"><div class="form-grid"><label>Contraseña actual<input name="password_actual" type="password" required></label><label>Nueva contraseña<input name="password_nueva" type="password" minlength="8" required></label><label>Confirmar nueva contraseña<input name="password_confirmacion" type="password" minlength="8" required></label></div><button class="button">Cambiar contraseña</button></form></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
