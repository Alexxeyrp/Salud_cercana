# Validación de la preparación

Realizada el 25 de octubre de 2026, sobre una copia separada del repositorio.
No se conectó a Aiven ni se modificó la instalación XAMPP del usuario.

## Resultados locales

Entorno: PHP 8.2.12 CLI, MySQL 8.0.44 en una instancia nueva y aislada,
escuchando únicamente en loopback y en un puerto distinto de XAMPP. Todos los
usuarios, contraseñas, certificados y datos de prueba son sintéticos y permanecen
fuera del paquete de despliegue.

| Comprobación | Resultado |
|---|---|
| Sintaxis de todos los PHP y `git diff --check` | Correcto |
| `tests/database_config.php` | 11 comprobaciones, configuración falla cerrada |
| `tests/session_mysql.php` | 19 comprobaciones, con y sin TLS local verificado |
| TLS local con CA correcta por ruta y base64 | Aceptado y cifrado negociado |
| TLS local con CA equivocada o nombre del servidor incorrecto | Rechazado |
| `tests/images.php` | 28 comprobaciones aisladas |
| `tests/images_http.php` | 12 comprobaciones HTTP aisladas |
| `tests/router_shutdown.php` | 43 comprobaciones HTTP de fallos de sesión, redirecciones, HEAD y rutas |
| `tests/run-e2e.ps1` | 126 comprobaciones HTTP/PDO contra dos procesos y MySQL real aislado |
| `scripts/preflight.php --database` contra la instancia aislada | Conexión, esquema, InnoDB y tamaño de paquetes correctos |
| SQL de referencia | Sin semillas, DROP ni TRUNCATE; estructura original conservada |
| Revisión independiente | Corregido guardado de sesión al salir mediante redirección; sin otros fallos concretos pendientes detectados |

Las pruebas de extremo a extremo incluyen registro, acceso, denegación de admin
a usuarios normales, CSRF, revocación de roles, rotación/invalidez de sesiones,
persistencia entre procesos, ambos formularios de noticias, multipart, imágenes
BLOB, límite de tamaño, rollback ante título duplicado, GET/HEAD/ETag y rutas
internas o codificadas. La configuración de producción nunca se carga en los
tests de fixtures sin red.

## Comprobaciones externas necesarias

El workflow `Validate PHP and MySQL` está preparado para repetir lint, pruebas
aisladas y flujos completos con **PHP 8.4** y **MySQL 8** en GitHub Actions, sin
secretos de producción. Consulta su resultado en el PR; no se debe inferir éxito
sólo porque exista el archivo de workflow.

La validación local no certifica un despliegue real de Vercel ni la identidad TLS
del servicio Aiven concreto. Antes de promover a producción, aplicar las dos
migraciones revisadas, configurar las variables privadas y completar la matriz
de Preview de `DEPLOY_VERCEL.md`, incluyendo persistencia tras redeploy,
restricciones de red, cuota, permisos y comprobación del runtime comunitario.
