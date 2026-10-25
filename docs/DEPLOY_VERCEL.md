# Preparar Vercel Hobby + Aiven MySQL

Esta rama prepara una demo personal. No crea servicios, no importa ni modifica
la base Aiven existente, no publica credenciales y no cambia XAMPP. Los valores
reales se introducen de forma privada en el panel del proveedor.

## Arquitectura y decisiones

- Una función `api/index.php`, PHP 8.4 con `vercel-php@0.8.0` y Node 22.x.
  PHP es un runtime comunitario, no un runtime oficial con soporte de Vercel.
- `routes` envía todas las URL al despachador. Sólo se sirven las páginas de la
  lista, CSS, JS y las imágenes históricas con nombre aleatorio permitido.
  No se usa un fallback de filesystem que pueda entregar PHP como texto.
- Sesiones compartidas en `app_sessions`, bloqueo MySQL entre peticiones,
  caducidad deslizante de 30 minutos, cookie de navegador `Secure`, `HttpOnly`,
  `SameSite=Lax` y prefijo `__Host-`. Login/logout regeneran el identificador.
  Cambiar o revocar un rol se comprueba de nuevo en cada petición autenticada.
- Imágenes nuevas JPG/PNG/WEBP de hasta 2 MiB en `app_news_images` (BLOB).
  Se validan los bytes y se guardan en la misma transacción que la noticia.
  `imagen.php?id=…` entrega imágenes públicas con ETag y caché inmutable, sin
  abrir sesión. Las imágenes ya versionadas conservan sus rutas.
- No hay subidas ni sesiones en el disco efímero. `/tmp` sólo aloja la CA pública
  temporal cuando procede. No se elimina ninguna imagen histórica al editar o
  borrar una noticia: revisar huérfanos manualmente cuando haga falta espacio.
- TLS MySQL con CA y verificación del nombre/certificado mediante PDO; además
  se comprueba que MySQL negoció cifrado. No hay fallback sin TLS en Vercel.
- Páginas personalizadas llevan `Cache-Control: private, no-store`; CSRF en
  formularios y errores sin detalles de conexión. GET logout pide confirmación;
  el cierre ocurre mediante POST con token.

Guardar BLOB en MySQL evita un tercer servicio para esta demo. Consume el mismo
disco, transferencia y conexiones de Aiven que el resto de la aplicación, y cada
lectura de imagen que no cubra caché usa PHP/MySQL. Para crecer, mover imágenes a
almacenamiento de objetos/CDN y migrar sus URL de forma controlada.

## 1. Revisar datos y cuentas antes de publicar

Lee `SECURITY_AUDIT.md`. El SQL histórico contiene semillas con datos personales
y hashes. Esta rama conserva estructura y elimina todas las semillas del archivo
actual; no modifica el historial Git. Cambia contraseñas de esas cuentas si siguen
activas o si se reutilizaron. No pegues secretos en issues, commits, capturas ni
este documento. El administrador existente se conserva; no hay alta pública admin.

## 2. Preparar Aiven sin reemplazar datos

1. Confirma que el servicio pertenece al plan **Free**, está activo, tiene espacio
   libre y es realmente MySQL. Descarga su CA desde la consola.
2. Haz una copia de seguridad antes de cambios de esquema.
3. En la base existente, aplica únicamente `database/001_sessions.sql` y
   `database/002_news_images.sql`. Son `CREATE TABLE IF NOT EXISTS`, sin DROP,
   TRUNCATE, UPDATE de datos ni creación automática desde peticiones web.
   Si ya existía una tabla con esos nombres, comprueba su estructura primero.
4. **No importes `sitio_web.sql` sobre la base actual.** Es sólo una referencia
   de esquema para una base nueva y vacía, sin semillas ni cuenta admin.
5. Usa un usuario de aplicación con permisos mínimos necesarios:
   SELECT/INSERT/UPDATE/DELETE en tablas de aplicación y sesiones/imágenes.
   No necesita CREATE/DROP/ALTER después de aplicar migraciones. GET_LOCK y
   RELEASE_LOCK deben estar disponibles. Evita el usuario administrador Aiven
   para atender las peticiones. No cambies contraseñas en esta preparación.
6. Usa el hostname y puerto exactos de Aiven, no localhost, una IP ni 3306 por
   suposición. Conserva sus restricciones de red. Hobby no proporciona una IP
   de salida dedicada para una allowlist fija; no abras la red automáticamente.
   Si la política actual impide conectar, evalúa esa decisión antes de publicar.

## 3. Variables privadas en Vercel

| Variable | Valor que debes introducir |
|---|---|
| `APP_ENV` | `production` |
| `DB_HOST` | Hostname completo del servicio Aiven |
| `DB_PORT` | Puerto indicado por Aiven |
| `DB_NAME` | Base que ya contiene tus tablas |
| `DB_USER` | Usuario de aplicación |
| `DB_PASSWORD` | Su contraseña, sin publicarla |
| `DB_SSL_MODE` | `verify-full` |
| `DB_SSL_CA_BASE64` | Certificado CA completo codificado como base64, una línea |
| `SESSION_TTL` | `1800` (segundos; rango admitido 60–86400) |

El código lee variables del proceso. `.env.example` es una referencia, **no** un
cargador de archivos `.env`. En XAMPP configura las variables del servidor, o usa
un proceso local con entorno privado fuera de la carpeta pública. Allí se puede
usar `DB_SSL_CA` con ruta absoluta al certificado en vez de base64. Una CA no es
la clave privada del servidor: nunca proporciones una clave privada.

No uses prefijos `NEXT_PUBLIC_` ni `VITE_`. Añade los valores a **Preview** y,
cuando se complete la validación, a **Production**. Preferiblemente usa una base
de pruebas con datos sintéticos para Preview; una Preview conectada a producción
puede modificar datos reales con los formularios existentes. No adjuntes secretos
a previews de contribuciones no confiables. Actualizar variables requiere un
nuevo despliegue para que tengan efecto.

## 4. Importar y generar una Preview

1. Importa el repositorio en Vercel con plan Hobby, preset **Other**, raíz del
   repositorio y Node **22.x**. Deja Build/Install Command vacíos (ya configurados)
   y Output Directory sin override. No selecciones una carpeta PHP como salida
   estática. El código fija runtime y duración máxima de 60 segundos.
2. Despliega primero la rama preparada como **Preview**. Mantén protección de
   Preview si está disponible. Para no desplegar el `main` antiguo al importar,
   selecciona la rama preparada para el primer despliegue; no publiques `main`
   hasta integrar esta propuesta y configurar las variables.
3. Elige una región próxima al MySQL de Aiven en los ajustes de funciones. No se
   fija una región inventada: primero comprueba la región real del servicio.
4. Verifica en el build una única función PHP y las extensiones requeridas:
   `pdo_mysql`, `openssl`, `fileinfo`, `mbstring`, `session`. `.vercelignore` y
   `excludeFiles` impiden incluir SQL, pruebas, CA/keys, `.env` y documentación.
5. Sigue la matriz siguiente. Sólo después integra la rama y promueve a
   producción. No hay una acción de publicación automática en estos pasos.

## 5. Validación y comprobaciones antes de promoción

Las pruebas automatizadas del repositorio usan datos sintéticos en una instancia
MySQL aislada y nunca deben apuntarse a Aiven real sin un entorno de pruebas.
Las instrucciones están en `tests/README.md`.

Desde un entorno privado con las variables configuradas:

```text
php scripts/preflight.php
php scripts/preflight.php --database
```

La primera valida configuración y CA sin conectar a red; la segunda abre una
conexión y hace SELECT de esquema/TLS, sin modificar datos ni crear tablas.
No demuestra permisos de escritura: eso se valida con usuarios de prueba.

| Comprobar en Preview | Resultado esperado |
|---|---|
| `/`, `index.php`, noticias, CSS/JS e imágenes históricas | Responden y conservan enlaces |
| `config/conexion.php`, `.env`, `ca.pem`, `sitio_web.sql`, `test_conexion.php`, `includes/auth.php`, `/api/index.php` | 404, sin código ni diagnóstico |
| Registro y login con CSRF correcto/incorrecto | Éxito con token, rechazo sin él |
| Cookie de sesión y nueva visita tras redeploy | Secure/HttpOnly/Lax, sesión persiste en MySQL |
| Usuario normal abre páginas admin | Redirección sin acceso ni cambios |
| Admin crea/edita noticias e imagen | Datos e imagen persisten tras otra instancia/redeploy |
| Imagen >2 MiB, MIME falso, petición >3 MiB | Rechazo controlado, sin archivos ni filas huérfanas nuevas |
| Admin guarda título duplicado | Se revierte su nueva imagen, originales intactos |
| Logout POST, volver al perfil y cookie anterior | Identificador anterior inválido; pide login |
| CA equivocada, host incorrecto, DB inaccesible | Falla cerrada; sin secretos en respuesta/logs |
| Sesiones simultáneas con la misma cookie | Sin pérdida de cambios; expiración tras inactividad |

## Límites y costes del plan gratuito

Revisado el 25 de octubre de 2026. Comprueba de nuevo el panel y las condiciones
antes de publicar; las cuotas pueden cambiar.

- **Vercel Hobby:** uso personal **no comercial**. No es apropiado para una
  clínica que vende servicios, recibe pagos, muestra publicidad o encarga trabajo
  pagado. Si ese es el destino real, el plan gratuito no cumple las condiciones.
  [Condiciones oficiales](https://vercel.com/docs/limits/fair-use-guidelines).
- Vercel limita solicitud/respuesta de funciones a **4,5 MB**. La app reduce las
  imágenes a 2 MiB y el formulario a 3 MiB; exceder el límite del proveedor puede
  devolver su error antes de ejecutar PHP. Una función sirve también los assets:
  estas peticiones cuentan dentro de las cuotas de uso. Revisa transferencia,
  invocaciones, CPU, builds y cuotas vigentes en el panel; no es uso ilimitado.
  [Límites de funciones](https://vercel.com/docs/functions/limitations),
  [plan Hobby](https://vercel.com/docs/plans/hobby).
- **Aiven MySQL Free:** 1 CPU, 1 GB RAM, **1 GB de disco**, 76 conexiones y una
  instancia por tipo y organización. Sesiones, imágenes, tablas e índices
  comparten ese espacio. Sin alta disponibilidad, SLA ni soporte. Puede apagarse
  por inactividad. Confirma las condiciones de backups/retención en tu servicio
  y mantén una exportación privada propia. Los BLOB retenidos consumen disco.
  [Plan MySQL Free](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier).
- Una conexión MySQL no persistente por petición dinámica; las sesiones usan esa
  misma conexión. Picos de concurrencia pueden agotar las 76 conexiones antes de
  agotar otras cuotas. No se promete rendimiento ni disponibilidad de producción.
- PHP depende del mantenedor del runtime y de la compatibilidad de Vercel.
  Cold starts, actualizaciones, límites y suspensión pueden interrumpir la demo.
  [Runtime fijado](https://github.com/vercel-community/php),
  [documentación Vercel](https://vercel.com/docs/functions/runtimes).

Para esta demo utiliza datos ficticios. No se ha evaluado como plataforma clínica
ni preparado un entorno de cumplimiento para datos sanitarios reales. Antes de
abrir registro público, configura protección de abuso y rate limiting en la
plataforma según el uso previsto; esta preparación no incorpora CAPTCHA ni un
servicio de correo para verificar cuentas o recuperar contraseñas.

## Recuperación

Conserva el despliegue anterior y promuévelo de nuevo si la Preview falla.
Las dos tablas nuevas no cambian las existentes y se pueden dejar instaladas;
no se propone eliminarlas. No vuelvas a la versión pública antigua como salida
estática, porque no tiene las protecciones de este despachador. La base de datos
y XAMPP no deben restaurarse ni sobrescribirse para probar esta rama.
