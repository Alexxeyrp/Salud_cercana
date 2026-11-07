# Estado del despliegue y mantenimiento

Revisión del 7 de noviembre de 2026. **Demo publicada: [https://salud-cercana.infinityfree.io/](https://salud-cercana.infinityfree.io/).**

## Estado actual verificado en el alojamiento

- Sitio publicado en el plan gratuito de InfinityFree, con HTTPS. La base de demo es independiente del proyecto del carrito; no se ha reutilizado su contenido.
- El alojamiento ejecuta PHP 8.4.27 y dispone de `pdo_mysql`, `mbstring` y `fileinfo`. `uploads/noticias` es escribible. Los límites PHP observados son `upload_max_filesize=20M` y `post_max_size=30M`; la aplicación conserva su límite propio de 5 MB. Estos valores no sustituyen una prueba real con una imagen próxima al máximo.
- PHP recibe HTTPS directamente; la cookie de sesión observada incluye `Secure`, `HttpOnly` y `SameSite=Lax`. No se usa `X-Forwarded-Proto` para decidir si una petición es segura.
- En este alojamiento PHP no puede leer la configuración de la carpeta hermana situada fuera de `htdocs`. Por eso la configuración real se instaló en `config/private.php`, protegida por `config/.htaccess` y las reglas de la raíz. Se comprobó respuesta HTTP 403 al intentar acceder a la configuración por la web. Ese archivo contiene secretos, se genera únicamente para el alojamiento y no se incluye en Git ni en los paquetes públicos.
- Comprobaciones públicas realizadas: registro de una cuenta ficticia, rechazo de contraseña incorrecta, inicio de sesión correcto, denegación de administración a un usuario normal, creación de una cita y edición de esa cita con persistencia después de recargar.
- Siguen pendientes las pruebas públicas administrativas y de imágenes, el borrado de citas, los cambios de perfil y contraseña, la revocación de roles, el comportamiento móvil y la persistencia después de un reinicio o nuevo despliegue. No se dan por superadas por haber pasado las pruebas locales.

## Historial de la evaluación previa al despliegue

Los siguientes hallazgos corresponden a la revisión inicial, antes de publicar la demo anterior.

- GitHub redirige el antiguo nombre `Salud_carcana` a `Alexxeyrp/Salud_cercana`. La rama remota principal sigue en `e83b8f3`; el commit local `3237be5` y los preparativos locales de Vercel no están allí. Se conservaron los cambios existentes.
- La API pública de GitHub no devuelve deployments. Se ve una ejecución de Actions satisfactoria, pero no acredita alojamiento.
- Existe autenticación local de Vercel, pero su API devuelve 403 Forbidden; no permite inventariar proyectos. No hay proyecto enlazado localmente ni URL pública encontrada. El host de acceso a navegadores no está disponible.
- La conexión local ya utilizaba DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD. No se reprodujo el antiguo DSN fijo. MySQL local no escuchaba en 3307.
- No se ejecutó el comprobador local con credenciales ni se accedió a Aiven. No se modificó ninguna base existente ni alojamiento. Las pruebas usan una instancia MariaDB nueva en 127.0.0.1:13317 y una base vacía `salud_demo_test`, sin importar datos personales del SQL original.

## Proveedor utilizado y límites pendientes

[InfinityFree](https://www.infinityfree.com/), condiciones verificadas en su página oficial durante esta revisión: 0 €, sin tarjeta, sin prueba temporal ni caducidad anunciada; PHP 8.4, MySQL 8.0/MariaDB 11.4, 5 GB de disco, subdominio gratuito y certificados SSL gratuitos. Anuncia 400 bases y ancho de banda ilimitado. Esto no significa CPU, conexiones o recursos ilimitados. Requiere algunas visitas al mes para considerar activo el sitio.

Queda comprobar en la cuenta asignada las cuotas de CPU, hits, inodos, tamaño por archivo/base y proceso de renovación SSL. Los límites PHP efectivos observados se indican en el estado actual. No se dan otras cifras no verificadas. Usar solamente el plan Free; no activar upgrades, pruebas ni servicios con tarjeta. No usar esta demo para información sanitaria real. Mantener copias propias; no asumir backups del proveedor.

La aplicación actual encaja mejor en alojamiento PHP con disco persistente que en Vercel: las sesiones de archivos y `uploads/noticias` requieren persistencia. Los preparativos de Vercel se conservan, pero sus limitaciones siguen documentadas en [VERCEL.md](VERCEL.md).

## Preparación y publicación

1. Iniciar sesión en el panel del alojamiento elegido. Hace falta acceso autorizado al panel, al gestor de archivos/FTP y a phpMyAdmin. No enviar secretos por chat ni añadirlos a GitHub.
2. **Antes de cambiar cualquier alojamiento existente**, descargar todos sus archivos, incluidos ocultos, configuración privada e imágenes, y exportar todas las tablas desde phpMyAdmin con estructura y datos. Guardar fuera del directorio público y verificar que ambas copias se abren y que el SQL incluye las tablas y filas esperadas. Documentar fecha y destino. No continuar si la copia falla. No reemplazar datos existentes sin autorización.
3. Crear un subdominio y una **base nueva vacía para la demo**. Importar [schema-demo.sql](../scripts/schema-demo.sql), que contiene solo estructura. No importar el volcado original con personas/cuentas. No ejecutar el esquema sobre una base existente.
4. Ejecutar `powershell -NoProfile -File scripts/build-package.ps1`. Produce un ZIP en el temporal local con lista explícita de páginas y recursos, sin SQL, pruebas, certificados, configuración privada ni documentación. Revisar su inventario antes de subir. Si hay archivos existentes, combinar tras backup; conservar las imágenes ya alojadas, sin sincronización con borrado.
5. Subir el contenido del ZIP al document root. Conservar todos los `.htaccess`. Preferir la configuración real **fuera de htdocs/public_html**, en la carpeta hermana `salud-private/config.php`, usando [config.example.php](../scripts/config.example.php) como plantilla. Si PHP no puede leerla por las restricciones del alojamiento, la conexión admite `config/private.php` como alternativa. Antes de usar esa alternativa, comprobar por HTTP que tanto `config/.htaccess` como las reglas de la raíz deniegan el acceso al directorio y que la URL del archivo responde 403. Las variables del entorno tienen prioridad; no se cargan archivos `.env` automáticamente. No poner credenciales en el ZIP: `/config/private.php` está ignorado por Git y excluido explícitamente por el empaquetador, que además lo rechaza si apareciera en el staging.
6. Configurar DB_HOST/PORT/NAME/USER/PASSWORD del proveedor. TLS verificado es obligatorio por defecto para hosts remotos. Solo si el proveedor documenta que su MySQL está en su red interna sin TLS, establecer explícitamente DB_SSL_MODE=`provider-internal`. No usar ese modo para Aiven ni una base accesible por Internet. Aiven conserva CA y verificación de certificado.
7. Activar SSL del subdominio y comprobar el certificado válido antes de forzar HTTPS en el panel. No confiar en cabeceras proxy sin verificar la infraestructura. La cookie Secure se activa cuando PHP recibe HTTPS. Confirmarlo en el alojamiento antes de publicar.
8. `uploads/noticias` debe ser persistente y escribible por PHP (habitualmente 0755; usar 0775 solo si el grupo lo requiere, nunca 0777). Verificar límites upload_max_filesize >=5M y post_max_size >=8M. El proveedor puede imponer límites menores: comprobar una imagen próxima al máximo. No permitir ejecución de scripts en uploads.

### HTTPS exclusivo del alojamiento público

El `.htaccess` local se conserva sin un redireccionamiento al dominio público. Aplicar una redirección solamente en la copia alojada y después de comprobar el certificado y que Apache reconoce HTTPS directamente. Con el nombre fijo del sitio, una regla inicial de prueba es:

```apache
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://salud-cercana.infinityfree.io%{REQUEST_URI} [R=302,L,NE]
```

Comprobar que HTTP redirige a HTTPS sin bucle antes de adoptar una redirección permanente. No copiar esta regla al entorno local ni confiar en cabeceras proxy enviadas por el visitante. Si el proveedor termina TLS en un proxy y Apache no reconoce HTTPS, usar la configuración acreditada del panel; esta regla por sí sola provocaría un bucle.

## Probar roles sin contraseñas públicas

Registrar dos usuarios ficticios desde la web. Para el administrador, en una base de demo vacía se puede usar por CLI [create-demo-user.php](../scripts/create-demo-user.php), con DEMO_SETUP=1, una DEMO_PASSWORD privada aleatoria de al menos 16 caracteres y argumento `demo_admin admin`. El script no cambia cuentas existentes y no imprime la contraseña. Si el alojamiento carece de CLI, registrar la cuenta y promover únicamente su usuario exacto desde phpMyAdmin; no ejecutar un UPDATE sin WHERE. Guardar la contraseña en un gestor privado. No publicar un administrador compartido en README o una página pública: podría modificar todas las cuentas e imágenes. Compartir el acceso administrativo solo de forma privada y temporal con el evaluador y revocarlo después.

## Pruebas locales previas

PHP 8.2.12 y MariaDB 10.4 de XAMPP, instancia separada. Flujos HTTP comprobados en Apache con mod_rewrite y mod_headers:

- Inicio y CSS: 200; perfil anónimo: redirección a login.
- Registro de tres cuentas ficticias y login de cada una.
- Login sin CSRF rechazado; usuario sin acceso a administración.
- Alta, edición y eliminación de cita propia; modificación de cita ajena rechazada; CSRF inválido no elimina citas.
- Administrador sube PNG por multipart; imagen recuperable en otra petición.
- Revocación del rol aplicada inmediatamente a una sesión ya abierta.
- Logout exige POST y CSRF; tras salir, citas vuelve a exigir login.
- SQL, certificado, test de conexión, .git, config, includes, api, docs, scripts y .htaccess de uploads devuelven 403 en Apache.
- Edición y eliminación de noticias con eliminación de su imagen; actualización de perfil y contraseña, verificando el nuevo hash y rechazo de la contraseña anterior. Total: 26 comprobaciones HTTP/BD satisfactorias. Se corrigió una constante __DIR mal escrita que rompía el borrado de imágenes y la limpieza de subidas cuando el formulario tiene errores.
- PHP sin errores de sintaxis y diff sin errores de espacios.

[integration.php](../tests/integration.php) conserva el recorrido. Requiere CLI, SALUD_TEST_DATABASE=salud_demo_test, SALUD_ROOT con la ruta del proyecto y una base vacía en puerto 13317; la web de pruebas debe apuntar a esa misma base. Solo admite una URL local (SALUD_TEST_URL, defecto http://127.0.0.1:8766/). Crea exclusivamente datos ficticios. Volver a crear la base aislada para repetir; nunca usar una base real. No subir estas herramientas al document root público.

**Pendientes de verificación pública:** persistencia tras reinicios/despliegues, subida de imágenes próxima al límite, edición/eliminación de noticias e imágenes en ambos formularios, borrado de citas, CRUD de usuarios, cambios de contraseña/perfil, revocación de roles, comportamiento móvil y renovación SSL. DNS, HTTPS, cookies, versión/extensiones de PHP y los flujos básicos indicados al inicio ya se comprobaron en el alojamiento. Las pruebas locales no sustituyen las comprobaciones públicas que siguen pendientes.

## Mantenimiento y restauración

Antes de cada actualización: exportar SQL y descargar imágenes y archivos actuales. Conservar respaldos fuera de la web con acceso privado. Probar las actualizaciones en una base de demo separada; no subir SQL o pruebas. Si falla, restaurar primero el código anterior compatible, y restaurar datos solo con autorización y una copia previa del estado actual. Comprobar inicio, login, permisos y una imagen tras cada actualización. Revisar errores en logs privados, espacio de disco, actividad del sitio y fecha del certificado. Rotar credenciales si se filtran y revisar el historial del volcado original en privado antes de difundirlo.

## Acceso para el mantenimiento

La demo ya tiene URL pública verificada. Para actualizarla o recuperar copias seguirá haciendo falta acceso autorizado al panel, FTPS y phpMyAdmin de la cuenta. Conservar los accesos en un gestor privado y mantener las copias del sitio y de la base fuera de la web. La configuración real no forma parte del repositorio ni del paquete de publicación.
