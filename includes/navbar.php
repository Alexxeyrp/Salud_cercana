<?php
declare(strict_types=1);
$rol = $_SESSION['rol'] ?? null;
$estaActiva = static fn (string $archivo): string => $paginaActual === $archivo ? 'active' : '';
?>
<header class="site-header"><nav class="navbar" aria-label="Navegación principal">
<a class="brand" href="index.php">Salud<span>Cercana</span></a><button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-principal">Menú</button><ul class="nav-links" id="menu-principal">
<li><a class="<?= $estaActiva('index.php') ?>" href="index.php">Inicio</a></li><li><a class="<?= $estaActiva('noticias.php') ?>" href="noticias.php">Noticias</a></li>
<?php if ($rol === null): ?><li><a class="<?= $estaActiva('registro.php') ?>" href="registro.php">Registro</a></li><li><a class="<?= $estaActiva('login.php') ?>" href="login.php">Login</a></li>
<?php elseif ($rol === 'admin'): ?><li><a class="<?= $estaActiva('usuarios-administracion.php') ?>" href="usuarios-administracion.php">Usuarios administración</a></li><li><a class="<?= $estaActiva('citaciones-administracion.php') ?>" href="citaciones-administracion.php">Citaciones administración</a></li><li><a class="<?= ($estaActiva('noticias-administracion.php') || $estaActiva('crear_noticia.php')) ? 'active' : '' ?>" href="noticias-administracion.php">Noticias administración</a></li><li><a class="<?= $estaActiva('perfil.php') ?>" href="perfil.php">Perfil</a></li><li><a class="<?= $estaActiva('logout.php') ?>" href="logout.php">Cerrar sesión</a></li>
<?php else: ?><li><a class="<?= $estaActiva('citaciones.php') ?>" href="citaciones.php">Citaciones</a></li><li><a class="<?= $estaActiva('perfil.php') ?>" href="perfil.php">Perfil</a></li><li><a class="<?= $estaActiva('logout.php') ?>" href="logout.php">Cerrar sesión</a></li><?php endif; ?>
</ul></nav></header>
