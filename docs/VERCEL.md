# Salud Cercana: preparación para Vercel Hobby

## Estado y alcance

Trabajo en la rama `chore/vercel-hobby`. No se ha hecho push ni despliegue. No se ha conectado a Aiven durante esta revisión ni ejecutado SQL. No se ha modificado la base de datos ni eliminado funcionalidad.

La configuración y el enrutamiento están preparados; **el uso completo en producción queda bloqueado por la persistencia de sesiones e imágenes**. No basta con configurar las credenciales para tener una aplicación funcional en serverless.

## Estructura y dependencias

Aplicación PHP sin framework, Composer, npm ni compilación. PHP 8 con PDO MySQL, OpenSSL, mbstring, fileinfo y sesiones. Se conserva la ejecución directa en XAMPP.

| Entradas públicas | Dependencias y comportamiento |
| --- | --- |
| `index.php` | Cabecera, navegación y pie; no consulta MySQL. |
| `noticias.php` | Conexión PDO, consulta noticias/autores, componentes HTML e imágenes. |
| `login.php`, `registro.php` | PDO, sesiones propias y componentes comunes; login regenera el ID de sesión. |
| `logout.php` | Destruye la sesión y crea otra para el mensaje de salida. |
| `perfil.php`, `citaciones.php` | `includes/auth.php`, autorización de usuario, PDO, CSRF y componentes. |
| Las tres páginas `*-administracion.php` | Autorización admin, PDO, CSRF y componentes; noticias también escribe/elimina imágenes. |
| `crear_noticia.php` | Sesión/autorización y CSRF propios, PDO, validación y subida de imagen. |

Los `require` usan `__DIR__`, por lo que siguen funcionando desde el despachador. La cabecera inicia sesiones incluso en páginas públicas. La navegación usa `PHP_SELF`; el despachador lo ajusta al nombre público. CSS y JS se cargan mediante rutas relativas en la cabecera y el pie. Las noticias usan la ruta de imagen guardada en MySQL.

## Archivos preparados

- [../vercel.json](../vercel.json): una función PHP, límite de 30 segundos, rutas públicas explícitas y recursos estáticos permitidos; el resto devuelve 404. Una sola función evita crear una por cada una de las once páginas. Sin `handle: filesystem`, para impedir servir fuentes PHP y archivos internos como estáticos.
- [../api/index.php](../api/index.php): despachador con lista cerrada; conserva query strings, POST, formularios y redirecciones relativas. Respuestas dinámicas con `private, no-store`.
- [../api/php.ini](../api/php.ini): errores ocultos, cookies Secure/HttpOnly/SameSite=Lax, modo estricto y zona Europe/Paris. No configura almacenamiento persistente de sesión.
- [../config/conexion.php](../config/conexion.php): conserva PDO/TLS y opciones locales, exige variables explícitas en Vercel y permite CA pública en base64. La decodifica a un archivo temporal único con permisos 0600 y limpieza al finalizar. Nunca desactiva la verificación del servidor. Los errores públicos y logs no contienen detalles PDO.
- [../.env.example](../.env.example): nombres de variables sin valores secretos; no se carga automáticamente.
- [../.gitignore](../.gitignore) y [../.vercelignore](../.vercelignore): excluyen secretos, certificados, pruebas, volcados y archivos locales del flujo habitual. El segundo también excluye el SQL ya versionado del paquete enviado a Vercel.
- [../README.md](../README.md): conexión corregida y enlace a este informe.

Ya existían sin seguimiento `vercel.json`, `ca.pem` y `test_conexion.php`. Se adapta la configuración existente y se conservan los otros dos en disco, excluidos. No se han mostrado ni copiado sus contenidos. El certificado CA público de Aiven no es una clave privada; aun así se entrega por variable para evitar incluir certificados en el repositorio.

**Límite de las exclusiones:** `.gitignore` no elimina archivos ya versionados ni limpia el historial. El volcado `sitio_web.sql` sigue versionado para conservar el proyecto original; no se envía a Vercel. Antes de publicar el repositorio, revisar en privado el volcado y el historial; si contienen datos reales, retirarlos del seguimiento y sanear el historial según corresponda. Ninguna exclusión sustituye revisar el diff antes de un commit.

## Incompatibilidades pendientes

1. **Sesiones:** el manejador actual `files` no comparte estado entre instancias y no ofrece persistencia tras un arranque en frío. El directorio por defecto puede ser de solo lectura. Moverlo a `/tmp` tampoco resuelve la persistencia. Afecta al login, roles, mensajes y CSRF. Antes de producción, integrar un almacén externo con TTL, bloqueo y regeneración/destrucción correctas, por ejemplo Redis mediante TLS. No se ha introducido una tabla de sesiones ni otra modificación de MySQL. Las cookies seguras por sí solas no solucionan este problema. No usar almacenamiento de autorización en una cookie sin un diseño seguro.
2. **Imágenes nuevas:** `crear_noticia.php` y `noticias-administracion.php` escriben con `move_uploaded_file` y borran con `unlink` bajo `uploads/noticias`. El paquete desplegado es de solo lectura; `/tmp` no es un alojamiento público persistente. Elegir un servicio de objetos (por ejemplo S3 compatible o Blob) e integrar alta, sustitución y borrado en ambos flujos, conservando las rutas existentes o resolviéndolas mediante una capa de almacenamiento. Las dos WEBP versionadas seguirán sirviéndose como estáticos. No se han cambiado los valores de MySQL.
3. **Tamaño de subida:** la aplicación permite 5 MB, pero Vercel Functions limita el cuerpo de solicitud a 4,5 MB, incluido multipart. Subir 5 MB al PHP fallará antes de llegar al validador aunque `php.ini` lo permita. Para conservar esa funcionalidad, usar subidas directas al almacenamiento externo con autorización de administrador y validación real de tipo/tamaño antes de publicar la noticia. No se ha reducido el límite original.
4. **Aiven:** revisar conectividad de salida y reglas de acceso de Aiven. Hobby no garantiza una IP de salida fija; una lista limitada a la IP doméstica no permitirá conectar desde Vercel. Conservar TLS y limitar privilegios del usuario MySQL. Vigilar conexiones concurrentes y latencia; no se activan conexiones PDO persistentes.
5. **Runtime:** `vercel-php@0.9.0` es comunitario, no soporte oficial PHP de Vercel. Su documentación indica PHP 8.5 y Node 22.x. Localmente se dispone de PHP 8.2.12: resta verificar comportamiento, extensiones y advertencias en el build real de PHP 8.5. No se ha ejecutado un build remoto.

Los enlaces relativos, acciones explícitas y formularios sin `action` conservan su destino al mantener los nombres `.php` en la raíz. Se conservan GET de selección/edición, POST, CSRF y redirecciones. No se añade `cleanUrls` ni se publica bajo un subdirectorio. La imagen de portada remota requiere acceso a Unsplash desde el navegador.

## Comprobaciones locales realizadas

- `php -l` sobre los 18 PHP presentes, incluido el archivo de prueba local: todos sin errores de sintaxis; el archivo de prueba no se ejecutó.
- PHP CLI 8.2.12: `pdo_mysql`, `openssl`, `mbstring`, `fileinfo` y `session` disponibles.
- Parseo de `vercel.json` mediante PowerShell: correcto. `git diff --check`: sin errores de espacios, solo aviso Git sobre conversión LF/CRLF.
- `git check-ignore`: CA, prueba local y archivos `.env`/`.env.production` excluidos.
- Servidor local con `php -S 127.0.0.1:8765 api/index.php`: `/`, `/index.php` y query string devuelven 200. Perfil, citas, creación de noticias y las tres administraciones sin sesión devuelven 302 a login.
- Despachador: solicitudes a conexión interna, CA, prueba, SQL, entrada interna y ruta de recorrido devuelven 404. Estas pruebas verifican el despachador PHP; no sustituyen validar las reglas de la plataforma en Vercel.
- Conexión en entorno Vercel simulado: variables ausentes y CA base64 inválida devuelven 503 genérico antes de intentar conectar. Sin credenciales reales y sin consultas a MySQL.

No se han probado login real, CRUD, TLS con Aiven, persistencia entre instancias, subidas ni el build Vercel. No hay suite automatizada existente ni CLI Vercel instalada. Los recursos estáticos se deben comprobar con el enrutamiento de Vercel; el servidor PHP utilizado en esta prueba despacha solamente páginas.

## Configurar Vercel cuando se resuelvan los bloqueos

1. Revisar cambios y resolver sesiones e imágenes externas antes de un despliegue funcional completo. Guardar los cambios en esta rama cuando se decida; esta tarea no crea commits ni hace push.
2. Crear/importar un proyecto Hobby con raíz en este repositorio, Framework Preset **Other**, Node **22.x**, sin comandos personalizados de instalación/build ni Output Directory personalizado. Mantener la configuración del runtime incluida. Comprobar que el uso cumple las condiciones de Hobby (uso personal no comercial).
3. En Settings → Environment Variables, definir `DB_HOST`, `DB_PORT` (puerto de Aiven), `DB_NAME`, `DB_USER`, `DB_PASSWORD` y `DB_SSL_CA_BASE64`. Elegir explícitamente Production y, si procede, Preview/Development. No reutilizar datos reales en previews sin valorar su acceso. No usar prefijos públicos. No configurar manualmente `VERCEL`: la plataforma lo establece.
4. Obtener la CA pública desde Aiven y convertirla localmente en PowerShell, pegando solo el resultado en la variable privada de Vercel:

   ```powershell
   [Convert]::ToBase64String([IO.File]::ReadAllBytes((Resolve-Path ./ca.pem)))
   ```

   `DB_SSL_CA_BASE64` tiene prioridad sobre `DB_SSL_CA`. No pegar rutas Windows en Vercel ni usar claves privadas. En XAMPP puede seguir usándose `DB_SSL_CA` con ruta absoluta; PHP no lee `.env` por sí mismo. Los valores locales por defecto siguen siendo localhost:3307, base sitio_web, usuario root sin contraseña.

5. Configurar las variables y permisos del futuro almacenamiento de sesiones e imágenes. No importar el SQL ni ejecutar migraciones sobre la base Aiven existente.
6. Cuando se autorice publicar, ejecutar primero un build y Preview. Verificar las once rutas, CSS/JS/WEBP, 404 para archivos internos y SQL, cookies, login/logout entre instancias, CSRF, todos los formularios y ciclo completo de imágenes; comprobar logs y conexión TLS. Evitar operaciones de prueba que alteren datos reales. Actualizar las variables exige un nuevo deployment.
7. Promover a producción únicamente después de resolver y validar esos puntos. En esta tarea no se ha creado proyecto remoto ni deployment.

Referencias consultadas: [runtime comunitario](https://github.com/vercel-community/php/tree/master), [límites de Vercel Functions](https://vercel.com/docs/functions/limitations), [Hobby](https://vercel.com/docs/plans/hobby).
