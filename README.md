# Salud Cercana

Proyecto educativo PHP + MySQL: noticias, registro/login, perfil, citas y administración con roles. Esta rama prepara su ejecución en Vercel y conserva el funcionamiento de las páginas PHP.

## Despliegue preparado

Lee [la guía Vercel + Aiven](docs/DEPLOY_VERCEL.md), [la auditoría de seguridad](docs/SECURITY_AUDIT.md) y [las pruebas](tests/README.md) antes de publicar.

- PHP 8.4 mediante `vercel-php@0.8.0` (runtime comunitario), Node 22.x.
- Una función y rutas públicas permitidas explícitamente; archivos internos y pruebas no se sirven.
- PDO por variables de entorno, TLS verificado con la CA de Aiven.
- Sesiones persistentes MySQL, cookies seguras, CSRF y comprobación de roles.
- Imágenes nuevas JPG/PNG/WEBP hasta 2 MiB en MySQL; originales versionados conservados.
- No hay credenciales incluidas ni administrador predeterminado. El SQL actual sólo contiene estructura, sin semillas.

## Base existente en Aiven

No importes `sitio_web.sql` sobre una base con datos. Con una cuenta de migración y copia de seguridad, aplica únicamente `database/001_sessions.sql` y `database/002_news_images.sql`. La aplicación no crea tablas automáticamente.

Configura las variables indicadas en `.env.example` de forma privada en Vercel. El código lee el entorno del proceso y no carga archivos `.env`. Mantén separados Preview y Production.

## Desarrollo local

Requiere PHP 8.2 o superior, MySQL 8/InnoDB y las extensiones PDO MySQL, OpenSSL, mbstring, fileinfo y session. El destino de Vercel es PHP 8.4.

En una base **nueva y vacía**, importa `sitio_web.sql` y ambas migraciones. Para una base ya funcional, conserva sus datos y aplica sólo las migraciones nuevas. Configura DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD en el entorno. Usa TLS con la CA correspondiente. La excepción sin TLS exige `APP_ENV=local` y `DB_SSL_MODE=disabled`; nunca se admite si existe la variable VERCEL.

Arranca con el despachador, que aplica las mismas restricciones de rutas:

```text
php -S 127.0.0.1:8000 api/index.php
```

No expongas la raíz de la aplicación como servidor estático. Las copias XAMPP existentes no se modifican al preparar esta rama. Para servir esta versión con Apache, configura la entrada por el despachador y las mismas restricciones de acceso; el servidor de desarrollo anterior permite probar sin cambiar Apache.

## Comprobación sin mostrar secretos

```text
php scripts/preflight.php
php scripts/preflight.php --database
```

La segunda opción realiza lecturas sobre la conexión y el esquema. Las pruebas de escritura están protegidas para usar únicamente una base local aislada con nombre `salud_test_…`.

Vercel Hobby es para uso personal no comercial y Aiven Free tiene capacidad limitada. Esta preparación es para una demo con datos ficticios; consulta condiciones, cuotas y pendientes de validación en la guía.
