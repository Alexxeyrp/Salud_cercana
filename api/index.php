<?php
declare(strict_types=1);

$pages = [
    'index.php', 'login.php', 'logout.php', 'registro.php', 'perfil.php',
    'noticias.php', 'crear_noticia.php', 'citaciones.php',
    'usuarios-administracion.php', 'noticias-administracion.php',
    'citaciones-administracion.php',
];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$page = $path === '/' ? 'index.php' : ltrim((string) $path, '/');
if (!in_array($page, $pages, true)) {
    http_response_code(404);
    exit('Página no encontrada.');
}
// Navbar depends on the public filename, rather than the internal entrypoint.
$_SERVER['PHP_SELF'] = '/' . $page;
header('Cache-Control: private, no-store');
require dirname(__DIR__) . '/' . $page;
