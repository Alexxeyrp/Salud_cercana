# Validación local

Las pruebas no necesitan credenciales de Aiven. Nunca ejecutes los scripts de
integración contra una base de datos real o una copia con datos personales.
Requieren PHP 8.2 o superior y las extensiones PDO, pdo_mysql, pdo_sqlite,
fileinfo, mbstring y curl. El despliegue de Vercel usa su runtime por separado.

## Pruebas sin red ni datos de usuarios

Desde la raíz del repositorio:

```powershell
php tests/database_config.php
php tests/images.php
php tests/images_http.php
php tests/router_shutdown.php
```

`images.php` usa fixtures binarios y SQLite en memoria. `images_http.php` arranca
un servidor PHP temporal en loopback y copia exclusivamente el endpoint de
imágenes y su helper; usa una conexión SQLite ficticia. Comprueba GET, HEAD,
ETag/304, MIME, caché y ausencia de cookies sin cargar la conexión de producción.

`router_shutdown.php` ejecuta el despachador real en un sandbox HTTP sin base de
datos. Comprueba que un fallo al guardar/cerrar la sesión antes de una redirección
produce 503 sin cookie, redirección ni contenido parcial; también cubre HEAD,
redirección correcta, rutas codificadas y archivos internos.

La revisión de GitHub ejecuta lint y estas pruebas con PHP 8.4 y MySQL 8 en un
servicio efímero, incluyendo sesiones y flujos completos en dos procesos. Los
valores de ese servicio son fixtures públicos, no credenciales de producción.
Las pruebas TLS locales con certificados propios se ejecutan por separado.

## MySQL aislado

Proporciona por entorno `APP_ENV=local`, `SALUD_TEST_DB=1`, `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, `DB_PASSWORD` y `DB_SSL_MODE`. El host debe ser
`127.0.0.1`, `localhost` o `::1`; el nombre de base de datos debe empezar por
`salud_test_`. Los puertos habituales de desarrollo 3306 y 3307 se rechazan.
Usa una cuenta y una contraseña sintéticas para esa instancia.
La base de datos debe existir. No apuntes las pruebas a XAMPP con datos reales.
La cuenta de preparación necesita `CREATE` y `ALTER` para importar el esquema
limpio; la cuenta de ejecución necesita los permisos de lectura y escritura de
la aplicación. Un fallo de importación puede dejar un esquema parcial: debe
completarse mediante sus sentencias aditivas antes de volver a ejecutar.

Para una instancia local sin TLS, `DB_SSL_MODE=disabled` sólo está permitido
con `APP_ENV=local` y sin `VERCEL`. La prueba de TLS requiere además la CA
pública correcta. No copies archivos de entorno ni contraseñas al repositorio.

```powershell
php tests/session_mysql.php
php tests/database_tls.php
```

Consulta cada prueba de base de datos para sus variables y escenarios. Las
pruebas de sesión usan identificadores sintéticos propios; no vacían la tabla.

## Navegación completa en dos procesos

Con las variables de la instancia aislada ya definidas:

```powershell
./tests/run-e2e.ps1
```

También puedes pasar un archivo local de variables de prueba mediante
`-Fixture <ruta>` y una instalación PHP mediante `-Php <ruta>`. Ese archivo
debe ser código de confianza y contener exclusivamente la configuración de la
instancia aislada. No se distribuye con el repositorio. Para esta sesión se
utilizó un fixture externo en `..\mysql-isolated\fixture.ps1`.

El lanzador comprueba los límites locales, aplica el esquema limpio sólo si no
hay tablas de la aplicación y aplica las migraciones aditivas 001 y 002. Se
detiene ante un esquema parcial o semillas/destrucción en el SQL. Nunca borra
tablas ni filas existentes. Arranca dos servidores PHP ocultos en loopback
(puertos 18081 y 18082 por defecto), ejecuta las pruebas y los cierra al terminar.
Puedes cambiar los puertos con `-PortA` y `-PortB`.

Las pruebas generan cuentas y noticias sintéticas únicas. Comprueban registro,
login, denegación a usuarios sin permisos, CSRF, publicación multipart, lectura
de imágenes en el segundo proceso, rotación e invalidación de sesiones,
revocación de permisos, edición y eliminación de su propia noticia de prueba,
rollback ante títulos duplicados, conservación de imágenes históricas y
ocultación de archivos internos/traversal. Eliminar una noticia de prueba no
elimina su imagen. No se imprimen contraseñas, cookies ni datos de usuarios.

Los fixtures sintéticos quedan en la base aislada para inspección. No hay una
limpieza masiva automática. Los logs temporales del servidor y la imagen
temporal se eliminan al terminar. La persistencia entre procesos y la semántica
HTTP se verifican localmente; un despliegue Preview de Vercel sigue siendo
necesario para validar su infraestructura y su runtime comunitario.
