# Auditoría de preparación para Vercel y Aiven

Fecha: 25 de octubre de 2026. Alcance: los dos commits públicos existentes al iniciar la revisión, el árbol del repositorio y una comparación de lectura con la instalación XAMPP. El trabajo se prepara en una rama separada. Este documento no contiene credenciales, datos personales, contraseñas ni sus valores derivados.

## Hallazgos

- El dump público `sitio_web.sql` contenía semillas de noticias, datos personales y una cuenta con contraseña derivada y rol. Estas semillas estaban presentes en los dos commits revisados. La propuesta retira las tres sentencias de carga de datos y conserva las definiciones de tablas, índices, autoincrementos y claves foráneas.
- No se detectaron credenciales remotas incrustadas en los archivos de conexión revisados, ni en el archivo local de prueba. Esto describe el resultado de la revisión; no garantiza que no exista ningún secreto en otros archivos, servicios, copias o contenido que quede fuera del alcance.
- El archivo local `test_conexion.php` genera salida de diagnóstico y detalles de conexión. No se incorpora a la rama y queda excluido mediante las reglas de despliegue. Los archivos de entorno, certificados, claves, SQL, documentación y pruebas también se excluyen del paquete publicado.
- El certificado local es una CA de confianza, no una clave privada. Su contenido y los parámetros de conexión no se copian al repositorio: la configuración de despliegue procede de variables del servidor.
- La comparación de los archivos seguidos por Git, normalizando finales de línea, encontró diferencias de contenido en `config/conexion.php` y `crear_noticia.php`. La instalación XAMPP se utilizó solo para lectura y permanece intacta.

## Tratamiento del dump y de la historia pública

`sitio_web.sql` es ahora una referencia de estructura sin filas ni cuentas precargadas. **No se debe importar en el servicio Aiven existente**: contiene instrucciones de creación y alteración de tablas. La aplicación no ejecuta este archivo al arrancar.

No se ha conectado a la base de datos para aplicar este cambio, eliminado información del servicio, ni reescrito la historia de Git. Un commit nuevo que retire las semillas no elimina los datos ya publicados en commits anteriores. Si las cuentas sembradas siguen existiendo o sus contraseñas se reutilizan, sus responsables deben cambiarlas. No se crea una cuenta administradora predeterminada ni una contraseña compartida para producción.

Las tablas adicionales para el despliegue se entregan como una migración separada para revisión. Su aplicación requiere una copia de seguridad y la selección explícita de la base de datos correspondiente; no debe confundirse con una restauración del dump de referencia.

## Decisiones para el entorno sin servidor

### Imágenes

Las nuevas imágenes de noticias se almacenan como BLOB en MySQL, dentro de la misma transacción que la noticia. Se valida el contenido real como JPEG, PNG o WEBP y se limita cada archivo a **2 MiB**. El navegador no determina por sí solo el tipo aceptado. El disco temporal de Vercel no se usa como almacenamiento permanente.

Esta solución evita necesitar una cuenta de almacenamiento adicional, pero comparte la capacidad de Aiven con noticias, usuarios y sesiones. También consume consultas y transferencia al servir imágenes; es apropiada para una demo pequeña. Las imágenes originales incluidas en el repositorio conservan su referencia. La conservación de imágenes sustituidas evita borrar archivos compartidos, aunque requiere vigilar el crecimiento del almacenamiento y preparar una limpieza revisable si llega a ser necesaria.

### Sesiones

Las sesiones se guardan en MySQL, con caducidad y exclusión mutua para peticiones de la misma sesión. Se valida que el identificador ya exista al recibirlo y se limita el trabajo de limpieza de sesiones caducadas. En despliegue se utilizan cookies seguras, inaccesibles a JavaScript y con `SameSite=Lax`. El inicio de sesión renueva el identificador.

La persistencia depende de que la base de datos esté disponible. El almacenamiento temporal de una función no permite mantener el acceso entre instancias o despliegues. La conexión utilizada por las sesiones no es una conexión PDO persistente.

### TLS y configuración

La conexión remota exige TLS con CA y verificación del certificado del servidor; también comprueba que el servidor informe un cifrado activo. Las variables obligatorias se validan antes de abrir la conexión. En despliegue, una configuración ausente o inválida produce un error genérico y no activa valores locales de XAMPP.

La CA puede proporcionarse mediante una variable codificada y materializarse temporalmente con permisos restringidos. Los errores públicos no incluyen los detalles originales del controlador de base de datos. Las credenciales deben configurarse en Vercel para el entorno seleccionado y nunca incluirse en código, capturas, documentación o registros de diagnóstico.

## Condiciones del plan gratuito y validación pendiente

La propuesta se ha diseñado para una demo personal y no comercial. **Es una inferencia de planificación, no una confirmación del uso real del sitio.** Vercel Hobby limita el uso comercial; si el sitio promociona servicios remunerados, procesa pagos, contiene publicidad o se ha desarrollado por encargo pagado, debe revisarse la elegibilidad antes de publicarlo. [Condiciones de Vercel](https://vercel.com/docs/limits/fair-use-guidelines).

Aiven Free dispone de capacidad limitada, una sola instancia y hasta 76 conexiones; no ofrece alta disponibilidad ni SLA y puede apagarse por inactividad. El espacio de las imágenes reduce la capacidad disponible para el resto de los datos. [Aiven MySQL Free](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier).

PHP se ejecuta mediante un runtime comunitario, por lo que la validación local no sustituye una prueba en Vercel. La carga completa de un formulario debe permanecer por debajo del límite de petición de la plataforma. Las cuotas gratuitas pueden limitar o suspender disponibilidad y no equivalen a una garantía de servicio. [Runtimes](https://vercel.com/docs/functions/runtimes), [límites de funciones](https://vercel.com/docs/functions/limitations).

La revisión del dump confirmó que no quedan sentencias de inserción, borrado de tablas ni truncado, y que las quince sentencias de estructura originales se conservan sin cambios. Las pruebas locales y el despliegue Preview deben verificar además registro, acceso, permisos, persistencia de sesión, imágenes y conexión TLS real. Este documento no afirma que esas verificaciones remotas ya hayan ocurrido.
