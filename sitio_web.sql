-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 03-09-2026 a las 23:02:01
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sitio_web`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `idCita` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `fecha_cita` date NOT NULL,
  `motivo_cita` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `noticias`
--

CREATE TABLE `noticias` (
  `idNoticia` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `imagen` varchar(255) NOT NULL,
  `texto` longtext NOT NULL,
  `fecha` date NOT NULL,
  `idUser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `noticias`
--

INSERT INTO `noticias` (`idNoticia`, `titulo`, `imagen`, `texto`, `fecha`, `idUser`) VALUES
(3, 'Los adolescentes chinos que consumen fármacos para adormecer la mente y \"escapar de la realidad\"', 'uploads/noticias/ae6d207af2a625bc2d4b92031097f075.webp', 'A los 14 años, Xiaomei*, una estudiante de alto rendimiento de una provincia costera de China, ingirió una sobredosis de un medicamento para la tos.\r\n\r\nQuería impresionar a un chico mayor que le había dicho que aquello la ayudaría a relajarse.\r\n\r\nLas pastillas solo se vendían con receta médica, pero ella logró comprarlas por internet.\r\n\r\nAl principio, notó que le provocaban un ligero mareo.\r\n\r\nSin embargo, pronto se enganchó a esa sensación de leve entumecimiento que para ella suponía un breve respiro frente a la presión de los exámenes que se avecinaban.', '2026-09-03', 3),
(4, 'Por qué han disminuido tan drásticamente las muertes por sobredosis de fentanilo en EE.UU.', 'uploads/noticias/65c873e225dca9d0b32bc591c5c19855.webp', 'Durante 15 años, las muertes por fentanilo en Estados Unidos se mantuvieron en ascenso. Y luego, hacia mediados de 2023, se desplomaron repentinamente.\r\n\r\nPara finales de 2024, la cifra había caído más de un tercio.\r\n\r\nEl fenómeno no ocurrió solo en EE.UU; las muertes también se desplomaron en Canadá, lo que es una noticia fantástica.\r\n\r\nPero no deja de resultar inquietante que los investigadores no tengan certezas sobre la causa que llevó a este descenso tan repentino y tan marcado. No hubo ningún gran avance médico ni una iniciativa nacional para crear más centros de rehabilitación.\r\n\r\nEso llevó al equipo de Keith Humphries, profesor de la Universidad de Stanford, en California, a investigar las posibles causas y publicar un estudio exponiendo sus hipótesis sobre lo que realmente frenó esas sobredosis mortales y si es probable que la situación se mantenga así.', '2026-09-03', 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users_data`
--

CREATE TABLE `users_data` (
  `idUser` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `sexo` enum('masculino','femenino','otro') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users_data`
--

INSERT INTO `users_data` (`idUser`, `nombre`, `apellidos`, `email`, `telefono`, `fecha_nacimiento`, `direccion`, `sexo`) VALUES
(3, 'alexis', 'ramirez', 'alexisramireztrd@gmail.com', '632102853', '1990-01-06', 'CALLE ANA MARIA MATUTE, 18', 'masculino');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users_login`
--

CREATE TABLE `users_login` (
  `idLogin` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','user') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users_login`
--

INSERT INTO `users_login` (`idLogin`, `idUser`, `usuario`, `password`, `rol`) VALUES
(3, 3, 'alexadmin', '$2y$10$ofAWnJW.1DOpnszmQgY5.eL2/GR0qI6z86yzUZ4X..wgp1iP9VtM2', 'admin');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`idCita`),
  ADD KEY `idUser` (`idUser`);

--
-- Indices de la tabla `noticias`
--
ALTER TABLE `noticias`
  ADD PRIMARY KEY (`idNoticia`),
  ADD UNIQUE KEY `titulo` (`titulo`),
  ADD KEY `idUser` (`idUser`);

--
-- Indices de la tabla `users_data`
--
ALTER TABLE `users_data`
  ADD PRIMARY KEY (`idUser`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `users_login`
--
ALTER TABLE `users_login`
  ADD PRIMARY KEY (`idLogin`),
  ADD UNIQUE KEY `idUser` (`idUser`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `idCita` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `noticias`
--
ALTER TABLE `noticias`
  MODIFY `idNoticia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `users_data`
--
ALTER TABLE `users_data`
  MODIFY `idUser` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `users_login`
--
ALTER TABLE `users_login`
  MODIFY `idLogin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`idUser`) REFERENCES `users_data` (`idUser`);

--
-- Filtros para la tabla `noticias`
--
ALTER TABLE `noticias`
  ADD CONSTRAINT `noticias_ibfk_1` FOREIGN KEY (`idUser`) REFERENCES `users_data` (`idUser`);

--
-- Filtros para la tabla `users_login`
--
ALTER TABLE `users_login`
  ADD CONSTRAINT `users_login_ibfk_1` FOREIGN KEY (`idUser`) REFERENCES `users_data` (`idUser`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
