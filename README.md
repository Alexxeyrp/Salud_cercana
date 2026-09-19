# Salud Cercana · Proyecto final de PHP

Aplicación web desarrollada como proyecto final del módulo de PHP del Máster en Desarrollo Full Stack. Simula un portal de atención sanitaria donde los visitantes pueden consultar noticias, los usuarios registrados pueden gestionar su perfil y sus citas, y los administradores pueden gestionar los contenidos y las cuentas.

El proyecto integra interfaz, lógica de servidor y base de datos relacional para poner en práctica un flujo completo de desarrollo web: registro, autenticación, autorización por roles y operaciones de creación, consulta, edición y eliminación (CRUD).

## Funcionalidades

| Perfil             | Qué puede hacer                                                                                                                                                                                    |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Visitante          | Consultar la página de inicio y las noticias, registrarse e iniciar sesión.                                                                                                                        |
| Usuario registrado | Actualizar sus datos personales, cambiar su contraseña y solicitar, consultar, editar o eliminar sus propias citas. La edición y eliminación de citas se limita a las de hoy o fechas posteriores. |
| Administrador      | Gestionar usuarios y roles, crear y administrar noticias con imágenes y gestionar las citas de los usuarios. También dispone de las funciones de usuario registrado.                               |

La interfaz se adapta a dispositivos móviles e incluye navegación desplegable, formularios de validación y mensajes de resultado. Las noticias muestran imagen, contenido, fecha y autor.

## Tecnologías utilizadas

| Tecnología      | Uso en el proyecto                                                                                        |
| --------------- | --------------------------------------------------------------------------------------------------------- |
| PHP 8           | Lógica de servidor, sesiones, validación de formularios y gestión de archivos.                            |
| MySQL / MariaDB | Persistencia de usuarios, credenciales, noticias y citas. El volcado incluido procede de MariaDB 10.4.32. |
| PDO             | Conexión a la base de datos, consultas preparadas y transacciones.                                        |
| HTML5 y CSS3    | Estructura semántica y estilos propios con Grid, Flexbox y media queries.                                 |
| JavaScript      | Interacción del menú móvil y confirmaciones de eliminación.                                               |
| Apache y XAMPP  | Entorno de ejecución local.                                                                               |
| phpMyAdmin      | Creación de la base de datos e importación del archivo SQL.                                               |

La aplicación está escrita sin frameworks y no necesita instalar dependencias con Composer o npm ni ejecutar un proceso de compilación.

## Aspectos técnicos destacados

- Separación de la conexión, las funciones de autenticación y los componentes comunes de la interfaz.
- Control de acceso mediante sesiones y roles `user` y `admin`, con regeneración del identificador de sesión al iniciar sesión.
- Contraseñas almacenadas mediante `password_hash()` y comprobadas con `password_verify()`.
- Consultas preparadas con PDO y escape de contenido HTML al mostrar datos.
- Tokens CSRF en los formularios de perfil, citas y administración, además de la creación de noticias.
- Validación de imágenes JPG, PNG y WEBP, con límite de 5 MB y nombres de archivo aleatorios.
- Transacciones para operaciones relacionadas de usuarios y claves foráneas para mantener la integridad de los datos.
- Restricciones de negocio: un usuario no puede modificar citas ajenas y no se pueden eliminar usuarios con citas o noticias asociadas.

## Cómo ejecutar el proyecto en local

### 1. Preparar el entorno

Necesitas XAMPP con Apache, MySQL/MariaDB y PHP 8.0 o superior. El volcado SQL incluido se generó en un entorno con PHP 8.2.12.

Comprueba que PHP tenga habilitadas las extensiones `pdo_mysql`, `mbstring` y `fileinfo`.

Descarga el repositorio desde **Code → Download ZIP** en GitHub y descomprímelo dentro de `htdocs`. En una instalación habitual de XAMPP en Windows, la estructura debe quedar así:

```text
C:\xampp\htdocs\trabajofinalPHP\index.php
```

También puedes clonar el repositorio dentro de `htdocs`, usando `trabajofinalPHP` como nombre de la carpeta de destino.

### 2. Iniciar los servicios e importar la base de datos

1. Abre el panel de control de XAMPP e inicia **Apache** y **MySQL**.
2. Accede a [phpMyAdmin local](http://localhost/phpmyadmin).
3. Crea una base de datos llamada `sitio_web` con cotejamiento `utf8mb4_general_ci`.
4. Selecciona esa base de datos, abre **Importar** y carga [sitio_web.sql](sitio_web.sql).

El archivo SQL crea las tablas e incluye una cuenta y noticias iniciales. Debes importarlo en una base de datos vacía; el archivo no contiene la instrucción `CREATE DATABASE`.

### 3. Revisar la conexión

Abre [config/conexion.php](config/conexion.php). La cadena de conexión actual es:

```php
"mysql:host=localhost;port=3307;dbname=sitio_web;charset=utf8mb4"
```

**El proyecto utiliza actualmente el puerto 3307.** Comprueba el puerto de MySQL en tu instalación de XAMPP. Si utiliza el 3306, cambia únicamente `port=3307` por `port=3306` en esa cadena.

Las credenciales por defecto son el usuario `root` y una contraseña vacía. Si tu instalación utiliza otras, configura `DB_USER` y `DB_PASSWORD` en el entorno del proceso PHP o adapta los valores por defecto de `$user` y `$password` en ese archivo para tu entorno local.

Aunque el archivo declara variables para `DB_HOST` y `DB_NAME`, la cadena de conexión actual tiene el servidor y el nombre de base de datos escritos directamente. Si necesitas cambiarlos, edita también esa cadena. El proyecto no carga archivos `.env` automáticamente.

### 4. Abrir la aplicación

Con ambos servicios activos, visita:

**[http://localhost/trabajofinalPHP/](http://localhost/trabajofinalPHP/)**

Si has utilizado otro nombre de carpeta o puerto de Apache, adapta la URL. Los archivos PHP deben ejecutarse desde el servidor; no basta con abrir `index.php` directamente ni con usar Live Server.

Para publicar noticias, PHP debe poder escribir en `uploads/noticias/`. Para admitir imágenes de hasta 5 MB, comprueba que `upload_max_filesize` sea al menos `5M` y que `post_max_size` sea superior, por ejemplo `8M`, en `php.ini`. Reinicia Apache si modificas esa configuración.

## Recorrido de demostración para reclutadores

Una vez instalado, este recorrido permite explorar las funciones principales:

1. **Inicio y noticias:** revisa la presentación del portal y las publicaciones incluidas. Reduce el ancho de la ventana para comprobar el diseño móvil.
2. **Registro:** crea una cuenta con datos ficticios y un nombre de usuario como `demo_reclutador`. El teléfono debe tener nueve dígitos y la contraseña de registro al menos seis caracteres.
3. **Perfil:** inicia sesión, modifica los datos personales y prueba el cambio de contraseña; la nueva contraseña debe tener al menos ocho caracteres.
4. **Citaciones:** solicita una cita para hoy o una fecha posterior, edita su motivo y comprueba su eliminación.
5. **Administración:** utiliza los pasos siguientes para habilitar el rol de administrador en tu cuenta local y explorar la gestión de usuarios, noticias y citas.

### Probar el rol de administrador

No necesitas conocer la contraseña de la cuenta incluida en el volcado SQL. Después de registrar tu propia cuenta de demostración, selecciona `sitio_web` en phpMyAdmin y ejecuta en la pestaña **SQL**:

```sql
UPDATE users_login
SET rol = 'admin'
WHERE usuario = 'demo_reclutador';
```

Sustituye `demo_reclutador` si elegiste otro nombre. Esta operación es para tu instalación local de demostración. **Cierra la sesión y vuelve a iniciarla** para que se actualice el rol almacenado en la sesión.

Ahora podrás acceder a:

- [Administración de usuarios](http://localhost/trabajofinalPHP/usuarios-administracion.php): altas, edición de datos, asignación de roles y eliminación cuando no existan citas o noticias asociadas.
- [Administración de noticias](http://localhost/trabajofinalPHP/noticias-administracion.php): publicación, edición y eliminación de noticias con imágenes.
- [Administración de citas](http://localhost/trabajofinalPHP/citaciones-administracion.php): selección de un usuario y gestión de sus citas.

## Estructura del proyecto

```text
trabajofinalPHP/
├── config/
│   └── conexion.php                # Conexión PDO a la base de datos
├── css/
│   └── style.css                   # Estilos y diseño adaptable
├── includes/
│   ├── auth.php                    # Acceso, validación y funciones comunes
│   ├── header.php                  # Cabecera HTML
│   ├── navbar.php                  # Navegación según sesión y rol
│   └── footer.php                  # Pie de página
├── js/
│   └── script.js                   # Menú móvil
├── uploads/noticias/               # Imágenes de las publicaciones
├── index.php                       # Página de inicio
├── registro.php                    # Registro de usuarios
├── login.php                       # Inicio de sesión
├── logout.php                      # Cierre de sesión
├── perfil.php                      # Datos personales y contraseña
├── noticias.php                    # Listado público de noticias
├── crear_noticia.php                # Formulario de publicación para administradores
├── citaciones.php                  # Gestión de citas propias
├── usuarios-administracion.php      # Administración de usuarios
├── noticias-administracion.php      # Administración de noticias
├── citaciones-administracion.php    # Administración de citas
└── sitio_web.sql                   # Estructura y datos iniciales
```

## Modelo de datos

| Tabla         | Contenido                                                            |
| ------------- | -------------------------------------------------------------------- |
| `users_data`  | Datos personales de cada usuario.                                    |
| `users_login` | Nombre de usuario, hash de contraseña y rol; una cuenta por usuario. |
| `citas`       | Usuario solicitante, fecha y motivo de la cita.                      |
| `noticias`    | Título, imagen, texto, fecha y usuario autor.                        |

Las tablas se relacionan mediante `idUser`. El correo, el nombre de usuario y el título de noticia tienen restricciones de unicidad.

## Solución de problemas

| Problema                                        | Qué revisar                                                                                                                               |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| «No se ha podido conectar con la base de datos» | Que MySQL esté iniciado, que exista `sitio_web` y que el puerto y las credenciales de `config/conexion.php` coincidan con tu instalación. |
| Error de tabla inexistente                      | Que hayas importado `sitio_web.sql` dentro de la base de datos seleccionada.                                                              |
| Error 404 al abrir la aplicación                | El nombre de la carpeta dentro de `htdocs` y el puerto de Apache.                                                                         |
| Error relacionado con `mb_strlen` o `finfo`     | Activa `mbstring` o `fileinfo` en el `php.ini` utilizado por Apache y reinicia el servicio.                                               |
| No aparece el acceso de administrador           | Comprueba el nombre de usuario del `UPDATE`, cierra sesión y vuelve a entrar.                                                             |
| No se puede subir una imagen                    | Formato, tamaño, límites de `php.ini` y permisos de escritura en `uploads/noticias/`.                                                     |

## Alcance

Proyecto académico para demostrar competencias de desarrollo Full Stack con PHP. Las citas se gestionan por fecha y motivo; no incluyen selección de hora ni disponibilidad de profesionales. La imagen de fondo de la portada se carga desde Unsplash y requiere conexión a Internet.

GitHub permite consultar el código y esta documentación. Para ejecutar la aplicación es necesario un servidor con PHP y MySQL/MariaDB; GitHub Pages no ejecuta este backend.
