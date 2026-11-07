<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValid('logout', $_POST['csrf_token'] ?? null)) {
    http_response_code(405);
    header('Allow: POST');
    exit('Utiliza el boton de cerrar sesion del menu.');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
session_id('');
session_start();
session_regenerate_id(true);
$_SESSION['flash'] = 'Has cerrado sesión correctamente.';
header('Location: login.php');
exit;
