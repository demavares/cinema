-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: datame
-- Tiempo de generación: 03-08-2026 a las 18:35:34
-- Versión del servidor: 10.11.18-MariaDB-ubu2204
-- Versión de PHP: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `cinema_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `code` varchar(2) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `countries`
--

INSERT INTO `countries` (`id`, `code`, `name`) VALUES
(1, 'AR', 'Argentina'),
(2, 'BO', 'Bolivia'),
(3, 'BR', 'Brasil'),
(4, 'CL', 'Chile'),
(5, 'CO', 'Colombia'),
(6, 'CR', 'Costa Rica'),
(7, 'CU', 'Cuba'),
(8, 'EC', 'Ecuador'),
(9, 'ES', 'España'),
(10, 'GT', 'Guatemala'),
(11, 'HN', 'Honduras'),
(12, 'MX', 'México'),
(13, 'NI', 'Nicaragua'),
(14, 'PA', 'Panamá'),
(15, 'PE', 'Perú'),
(16, 'PR', 'Puerto Rico'),
(17, 'PY', 'Paraguay'),
(18, 'SV', 'El Salvador'),
(19, 'UY', 'Uruguay'),
(20, 'VE', 'Venezuela'),
(21, 'US', 'Estados Unidos de América'),
(22, 'CA', 'Canadá'),
(23, 'DE', 'Alemania'),
(24, 'FR', 'Francia'),
(25, 'IT', 'Italia'),
(26, 'GB', 'Reino Unido'),
(27, 'JP', 'Japón');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `food_categories`
--

CREATE TABLE `food_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `food_categories`
--

INSERT INTO `food_categories` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Combos', 'Combos', 1, '2026-07-29 02:09:29'),
(2, 'Dulces', 'Dulces', 1, '2026-07-29 02:10:55'),
(3, 'Bebidas', 'Bebidas', 1, '2026-07-29 02:10:55'),
(5, 'Palomita de maíz', 'Palomita de maíz', 1, '2026-07-29 02:54:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `food_items`
--

CREATE TABLE `food_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `food_items`
--

INSERT INTO `food_items` (`id`, `name`, `description`, `price`, `image_url`, `category_id`, `is_active`, `created_at`) VALUES
(1, 'Combo Papas', 'papas', 1200.00, 'img/food_1785290273_6a695e2132a59.jpg', 1, 1, '2026-07-29 01:57:53'),
(2, 'Combo Hamburguera', 'Hamburguesa', 1500.00, 'img/food_1785292754_6a6967d284990.jpg', 1, 1, '2026-07-29 02:39:14'),
(3, 'Coca Cola', '', 15.00, 'img/food_1785293633_6a696b4184245.jpg', 3, 1, '2026-07-29 02:53:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `food_orders`
--

CREATE TABLE `food_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `showtime_id` int(11) NOT NULL,
  `food_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `order_date` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','cancelled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `food_orders`
--

INSERT INTO `food_orders` (`id`, `user_id`, `ticket_id`, `showtime_id`, `food_item_id`, `quantity`, `unit_price`, `total_price`, `order_date`, `status`) VALUES
(1, 1, NULL, 54, 1, 1, 1200.00, 1200.00, '2026-08-03 09:12:12', 'completed'),
(2, 1, NULL, 54, 1, 1, 1200.00, 1200.00, '2026-08-03 10:04:25', 'completed'),
(3, 1, NULL, 54, 2, 1, 1500.00, 1500.00, '2026-08-03 10:04:25', 'completed');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movies`
--

CREATE TABLE `movies` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `poster_url` varchar(255) DEFAULT NULL,
  `banner_url` varchar(255) DEFAULT NULL,
  `cast_members` text DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `director` varchar(100) DEFAULT NULL,
  `classification` varchar(50) DEFAULT NULL,
  `trailer_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `movies`
--

INSERT INTO `movies` (`id`, `title`, `description`, `poster_url`, `banner_url`, `cast_members`, `country_id`, `duration`, `genre`, `year`, `director`, `classification`, `trailer_url`, `is_active`, `created_at`) VALUES
(18, 'Aliens', 'Alien es un organismo perfecto, una máquina de matar cuya superioridad física sólo puede competir con su agresividad. La oficial Ripley y la tripulación de la nave «Nostromo» se habían enfrentado, en el pasado, a esa monstruosa criatura. Y sólo Ripley sobrevivió a la masacre. Después de vagar por el espacio durante varios años, Ripley fue rescatada. Durante ese tiempo, el planeta de Alien ha sido colonizado. Pero, de repente, se pierde la comunicación con la colonia y, para investigar los motivos, se envía una expedición de marines espaciales, capitaneados por Ripley. Allí les esperan miles de espeluznantes criaturas. Alien se ha reproducido y esta vez la lucha es por la supervivencia de la Humanidad.', 'https://image.tmdb.org/t/p/w500/3QU9EP8BFLnTh6w9ycDSCvhqbRU.jpg', 'https://image.tmdb.org/t/p/original/4kix6fAblJIH6eMs0Ku2loyZJXK.jpg', 'Sigourney Weaver, Carrie Henn, Michael Biehn, Paul Reiser, Lance Henriksen, Bill Paxton', 21, 150, 'Acción, Suspense, Ciencia ficción', '1986', 'James Cameron', 'C (Mayores de 18)', 'https://youtu.be/gOnNP4ONTtI?si=VQ2YbkdpPoA_mGPG', 1, '2026-07-20 01:06:32'),
(21, 'The Mandalorian and Grogu', 'El Imperio ha caído y los señores de la guerra imperiales siguen dispersos por toda la galaxia. Mientras la incipiente Nueva República trabaja para proteger todo por lo que luchó la Rebelión, ha reclutado la ayuda del legendario cazarrecompensas mandaloriano Din Djarin y su joven aprendiz Grogu.', 'https://image.tmdb.org/t/p/w500/sWitU9IjgFwf6y1OrI0zUaL3GNa.jpg', 'https://image.tmdb.org/t/p/original/ysLlsAxwgNSxBWHCgTKJrmjxpRQ.jpg', 'Pedro Pascal, Jeremy Allen White, Sigourney Weaver, Brendan Wayne, Lateef Crowder, Steve Blum', 21, 132, 'Acción, Aventura, Ciencia ficción', '2026', 'Jon Favreau', 'B (Mayores de 12)', 'https://youtu.be/LjLamj-b0I8?si=lALDYK1Vkjt42iZ9', 1, '2026-07-20 01:15:22'),
(22, 'Spider-Man: Brand New Day', 'Han pasado cuatro años desde los acontecimientos de No Way Home, y Peter Parker ahora es un adulto que vive completamente solo, ha desaparecido voluntariamente de las vidas y recuerdos de quienes ama. Combatiendo el crimen en una Nueva York que ya no conoce su nombre, se ha dedicado por completo a proteger su ciudad, pero a medida que aumentan las exigencias sobre él, la presión desencadena una evolución física que amenaza su existencia, al mismo tiempo que un extraño nuevo patrón de crímenes da lugar a una de las amenazas más poderosas a las que se ha enfrentado.', 'https://image.tmdb.org/t/p/w500/tluwRNA7k0XfTtDkdLYKX1KOSCJ.jpg', 'https://image.tmdb.org/t/p/original/kbvNLChuMl2nyAzPZvqkD8hZGZn.jpg', 'Tom Holland, Zendaya, Sadie Sink, Jacob Batalon, Jon Bernthal, Tramell Tillman', 21, 144, 'Ciencia ficción, Acción, Aventura', '2026', 'Destin Daniel Cretton', 'B (Mayores de 12)', 'https://youtu.be/lBU9_kIJI9U?si=p76bf5PItFGwTUI_', 1, '2026-07-21 03:58:04'),
(29, 'Papita, maní, tostón', 'Andrés (Jean Pierre Agostini) es fanático de Los Leones del Caracas, uno de los principales equipos de béisbol de Venezuela. Julissa (Juliette Pardau) es fanática de Los Navegantes del Magallanes, el equipo rival. Un día Andrés recibe boletos para ver el juego en la Zona VIP de Magallanes. Conoce a Julissa y a su padre, que no solo es admirador sino también uno de los gerentes del equipo. Andrés y Julissa se enamorarán y tendrán que fingir ser fanáticos del equipo del otro. Pero pronto surgirán problemas.', 'https://image.tmdb.org/t/p/w500/hF3I0Zd54EOgt9PuI1yXDmsx4gb.jpg', 'https://image.tmdb.org/t/p/original/k2W93PeASnRtucQnRVxOKeFatf.jpg', 'Jean Pierre Agostini De Risi, Juliette Pardau, Vicente Peña, Vantroy Sánchez, Juan Andres Belgrave, José Roberto Díaz', 20, 112, 'Comedia, Romance', '2013', 'Luis Carlos Hueck', 'B (Mayores de 12)', 'https://youtu.be/EDnIEWyVIlE?si=Omr8eTQJu9wPUfe3', 1, '2026-07-22 09:56:45'),
(30, 'La odisea', 'Tras la caída de Troya, el legendario rey de Ítaca, Odiseo a.k.a. Ulises, emprende un largo y peligroso viaje para regresar junto a su esposa Penélope y su hijo Telémaco. En el camino deberá enfrentarse a criaturas mitológicas, dioses caprichosos y pruebas que pondrán a prueba su ingenio, su resistencia y su humanidad. Mientras tanto, en Ítaca, el futuro de su reino pende de un hilo ante la creciente amenaza de quienes creen que jamás volverá.', 'https://image.tmdb.org/t/p/w500/bheY17L6wtB0SdJn6EYyh1X5iry.jpg', 'https://image.tmdb.org/t/p/original/twiVn9oFXOVR0uoYgawyEBlnFu8.jpg', 'Matt Damon, Tom Holland, Anne Hathaway, Robert Pattinson, Himesh Patel', 26, 173, 'Aventura, Acción, Fantasía', '2026', 'Christopher Nolan', 'C (Mayores de 18)', 'https://youtu.be/LjLamj-b0I8?si=lALDYK1Vkjt42iZ9', 1, '2026-07-23 05:15:48'),
(34, 'Mi vecino Totoro', 'Dos chicas jóvenes, Mei y Satsuki, se mudan a una nueva casa cerca del hospital en el que se encuentra su madre. En el patio junto a la casa, existe un gran árbol que es el hogar de tres Totoros, dioses de la selva. Poco después, reciben noticias desde el hospital de que su madre no puede venir a casa como había prometido, por lo que Mei (la más joven) se escapa para ir a visitarla. Satsuki tiene que recurrir a un Totoro para ayudar a encontrarla.', 'https://image.tmdb.org/t/p/w500/uu6RaEAfkIQaolf20axWaRU4h3w.jpg', 'https://image.tmdb.org/t/p/original/zkThiZAaAie8Lw7RAc5yPTOewBV.jpg', 'Noriko Hidaka, 坂本千夏, 高木均, 糸井重里, Sumi Shimamoto, Tanie Kitabayashi', 27, 86, 'Fantasía, Animación, Familia', '1988', 'Hayao Miyazaki', 'A (Todo público)', 'https://youtu.be/gOnNP4ONTtI?si=VQ2YbkdpPoA_mGPG', 1, '2026-07-25 09:37:33'),
(35, 'Babymetal', 'Babymetal es un grupo idol japonés de kawaii metal formada en Tokio en 2010.', 'https://www.prints4u.net/wp-content/uploads/2024/06/BabyMetal-019-1.jpg', 'https://shop.metalforth.com/cdn/shop/files/METALFORTH-ALTCOVERKITSUNE-DIGITALCOVER.png?v=1754604266', 'Suzuka Nakamoto (Su-metal) Moa Kikuchi (Moametal) Momoko Okazaki (Momometal)', 27, 120, 'Música', '2026', 'No disponible', 'A (Todo público)', 'https://youtu.be/EDnIEWyVIlE?si=Omr8eTQJu9wPUfe3', 1, '2026-07-27 03:25:09'),
(46, 'Avengers: Doomsday', 'Quinta entrega de la saga \'Vengadores\' incluida en el Universo Cinematográfico Marvel (UCM). Sinopsis desconocido.', 'https://image.tmdb.org/t/p/w500/rQKabpeIewLLNStFr3anEXI0xqu.jpg', 'https://image.tmdb.org/t/p/original/6KDDoTq8Vq3HuQHULzuvPiCJbMI.jpg', 'Robert Downey Jr., Chris Evans, Chris Hemsworth, Pedro Pascal, Paul Rudd, Anthony Mackie', 21, 165, 'Ciencia ficción, Acción, Aventura', '2026', 'Joe Russo y Anthony Russo', 'A (Todo público)', 'https://youtu.be/EDnIEWyVIlE?si=Omr8eTQJu9wPUfe3', 1, '2026-07-28 07:15:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `showtime_id` int(11) NOT NULL,
  `seats` text NOT NULL,
  `accessible_seats` text DEFAULT NULL,
  `total_tickets` int(11) NOT NULL,
  `total_food` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `purchase_date` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','expired') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_data` text DEFAULT NULL,
  `session_token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `purchases`
--

INSERT INTO `purchases` (`id`, `user_id`, `showtime_id`, `seats`, `accessible_seats`, `total_tickets`, `total_food`, `total_amount`, `subtotal`, `tax_amount`, `tax_rate`, `purchase_date`, `status`, `payment_method`, `payment_data`, `session_token`, `expires_at`) VALUES
(124, 1, 52, 'H20', NULL, 4, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 06:56:06', 'expired', NULL, NULL, '3316072681c515d18ec1cfa0da553e1f8d2dbb96a74e0aee7e0f456a0132d521', '2026-08-03 03:13:28'),
(125, 1, 52, 'H20', NULL, 1, 0.00, 1740.00, 1500.00, 240.00, 16.00, '2026-08-03 07:03:43', 'completed', 'movil', '{\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260803-F9A466\",\"date\":\"2026-08-03 03:03:43\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'ad183e73721ae0eadc21335a6d49fcfa3fea2a4bd7210db222c7274e62ce74b1', '2026-08-03 03:13:43'),
(126, 1, 52, 'E14,E13,E12', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 08:25:13', 'expired', NULL, NULL, '8c1640dd23184f4112fa70efbfdb5b0e4b446117c5a666997e99c6f160e79f5b', '2026-08-03 04:37:55'),
(128, 1, 54, 'A10,A11', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 09:02:19', 'expired', NULL, NULL, '217da77b07f198bc5cced5e7822e583be51ad6c7175e1d0aa187a96a3c31855d', '2026-08-03 05:21:56'),
(129, 1, 54, 'A10,A11♿', NULL, 2, 1200.00, 6420.00, 4500.00, 720.00, 16.00, '2026-08-03 09:12:12', 'completed', 'movil', '{\"method\":\"movil\",\"bank\":\"Banco de Venezuela\",\"phone\":\"0412-1234567\",\"reference\":\"CMP-20260803-C01562\",\"simulated\":true,\"date\":\"2026-08-03 05:12:12\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'aa6aa47e2d43d87ce1863ae8e3aa233d', '2026-08-03 05:22:12'),
(130, 1, 54, 'A12,A11', NULL, 2, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 09:30:13', 'expired', NULL, NULL, '8882a7f1eb970e5d1b9d47ec10694da2d1f72b915fecb706d9ddd382e969e71c', '2026-08-03 05:40:13'),
(131, 1, 54, 'A12,A11,A10', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 10:03:52', 'expired', NULL, NULL, '978715d9daea43288f966709680371f959d1d34abc11c06d7bf247714eae8418', '2026-08-03 06:14:17'),
(132, 1, 54, 'A12,A11♿,A10', NULL, 3, 2700.00, 13140.00, 9000.00, 1440.00, 16.00, '2026-08-03 10:04:25', 'completed', 'movil', '{\"method\":\"movil\",\"bank\":\"Banco de Venezuela\",\"phone\":\"0412-1234567\",\"reference\":\"CMP-20260803-964F59\",\"simulated\":true,\"date\":\"2026-08-03 06:04:25\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '8a1cb5032d7c9a6c2ea1a3a5ec501e4e', '2026-08-03 06:14:25'),
(133, 5, 52, 'D14', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 18:33:22', 'pending', NULL, NULL, 'e3d97e1ca3e1213aa83868e262a2c9430b27bc26e5171018803bcdee32634a29', '2026-08-03 14:43:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchase_tickets`
--

CREATE TABLE `purchase_tickets` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `showtime_id` int(11) NOT NULL,
  `ticket_type_id` int(11) NOT NULL,
  `seat_code` varchar(10) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `purchase_tickets`
--

INSERT INTO `purchase_tickets` (`id`, `purchase_id`, `showtime_id`, `ticket_type_id`, `seat_code`, `price`, `created_at`) VALUES
(1, 125, 52, 1, 'H20', 1500.00, '2026-08-03 07:03:43'),
(2, 129, 54, 1, 'A10', 3000.00, '2026-08-03 09:12:12'),
(3, 129, 54, 3, 'A11', 1500.00, '2026-08-03 09:12:12'),
(4, 132, 54, 1, 'A12', 3000.00, '2026-08-03 10:04:25'),
(5, 132, 54, 1, 'A11', 3000.00, '2026-08-03 10:04:25'),
(6, 132, 54, 1, 'A10', 3000.00, '2026-08-03 10:04:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 50,
  `description` text DEFAULT NULL,
  `seat_layout` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`seat_layout`)),
  `aisle_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`aisle_config`)),
  `seat_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`seat_config`)),
  `seat_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rooms`
--

INSERT INTO `rooms` (`id`, `name`, `capacity`, `description`, `seat_layout`, `aisle_config`, `seat_config`, `seat_image`, `is_active`, `created_at`) VALUES
(12, 'Sala 1', 161, 'sala', '{\"rows\":[\"A\",\"B\",\"C\",\"D\",\"E\",\"F\",\"G\",\"H\",\"I\",\"J\"],\"seatsPerRow\":21,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"C\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"D\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"E\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"F\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"G\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"H\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"I\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"J\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]},\"totalSeats\":210,\"blockedSeats\":[\"A4\",\"A5\",\"A14\",\"A15\",\"A16\",\"B4\",\"B5\",\"B15\",\"B16\",\"C4\",\"C5\",\"C15\",\"C16\",\"D4\",\"D5\",\"D15\",\"D16\",\"E4\",\"E5\",\"E15\",\"E16\",\"F4\",\"F5\",\"F15\",\"F16\",\"G4\",\"G5\",\"G15\",\"G16\",\"H4\",\"H5\",\"H15\",\"H16\",\"I4\",\"I5\",\"I15\",\"I16\",\"J10\",\"J11\",\"J12\",\"J13\",\"J14\",\"J15\",\"J16\",\"J17\",\"J18\",\"J19\",\"J20\",\"J21\"]}', '{\"blockedSeats\":[\"A4\",\"A5\",\"A14\",\"A15\",\"A16\",\"B4\",\"B5\",\"B15\",\"B16\",\"C4\",\"C5\",\"C15\",\"C16\",\"D4\",\"D5\",\"D15\",\"D16\",\"E4\",\"E5\",\"E15\",\"E16\",\"F4\",\"F5\",\"F15\",\"F16\",\"G4\",\"G5\",\"G15\",\"G16\",\"H4\",\"H5\",\"H15\",\"H16\",\"I4\",\"I5\",\"I15\",\"I16\",\"J10\",\"J11\",\"J12\",\"J13\",\"J14\",\"J15\",\"J16\",\"J17\",\"J18\",\"J19\",\"J20\",\"J21\"]}', NULL, NULL, 1, '2026-07-13 06:15:33'),
(13, 'Sala 2', 146, '', '{\"rows\":[\"A\",\"B\",\"C\",\"D\",\"E\",\"F\",\"G\",\"H\"],\"seatsPerRow\":21,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"C\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"D\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"E\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"F\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"G\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"H\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]},\"totalSeats\":168,\"blockedSeats\":[\"A9\",\"A16\",\"B9\",\"B16\",\"C9\",\"C16\",\"D9\",\"D16\",\"E9\",\"E16\",\"F9\",\"F10\",\"F11\",\"F12\",\"F13\",\"F14\",\"F15\",\"F16\",\"G9\",\"G16\",\"H9\",\"H16\"],\"wheelchairSeats\":[\"A10\",\"A11\",\"A12\",\"A13\",\"A14\",\"A15\"]}', '{\"blockedSeats\":[\"A9\",\"A16\",\"B9\",\"B16\",\"C9\",\"C16\",\"D9\",\"D16\",\"E9\",\"E16\",\"F9\",\"F10\",\"F11\",\"F12\",\"F13\",\"F14\",\"F15\",\"F16\",\"G9\",\"G16\",\"H9\",\"H16\"],\"wheelchairSeats\":[\"A10\",\"A11\",\"A12\",\"A13\",\"A14\",\"A15\"]}', NULL, NULL, 1, '2026-07-13 15:11:33'),
(14, 'Sala 3', 21, '', '{\"rows\":[\"A\",\"B\"],\"seatsPerRow\":21,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]},\"totalSeats\":42,\"blockedSeats\":[\"A13\",\"A14\",\"A15\",\"A16\",\"A17\",\"A18\",\"A19\",\"A20\",\"A21\",\"B10\",\"B11\",\"B12\",\"B13\",\"B14\",\"B15\",\"B16\",\"B17\",\"B18\",\"B19\",\"B20\",\"B21\"],\"wheelchairSeats\":[\"A11\",\"B8\",\"B9\"]}', '{\"blockedSeats\":[\"A13\",\"A14\",\"A15\",\"A16\",\"A17\",\"A18\",\"A19\",\"A20\",\"A21\",\"B10\",\"B11\",\"B12\",\"B13\",\"B14\",\"B15\",\"B16\",\"B17\",\"B18\",\"B19\",\"B20\",\"B21\"],\"wheelchairSeats\":[\"A11\",\"B8\",\"B9\"]}', NULL, NULL, 1, '2026-07-13 23:38:47'),
(16, 'Sala 4', 127, '', '{\"rows\":[\"A\",\"B\",\"C\",\"D\",\"E\",\"F\",\"G\"],\"seatsPerRow\":20,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"C\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"D\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"E\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"F\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"G\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20]},\"totalSeats\":140,\"blockedSeats\":[\"C9\",\"C10\",\"B10\",\"B9\",\"A9\",\"A10\",\"D10\",\"D9\",\"E9\",\"E10\",\"F9\",\"F10\",\"F11\"]}', '{\"blockedSeats\":[\"C9\",\"C10\",\"B10\",\"B9\",\"A9\",\"A10\",\"D10\",\"D9\",\"E9\",\"E10\",\"F9\",\"F10\",\"F11\"]}', NULL, NULL, 1, '2026-07-26 04:21:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `showtimes`
--

CREATE TABLE `showtimes` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `price_adult` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_child` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_senior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `enable_child_price` tinyint(1) NOT NULL DEFAULT 1,
  `enable_senior_price` tinyint(1) NOT NULL DEFAULT 1,
  `half_price_monday` tinyint(1) DEFAULT 0,
  `promotions` varchar(255) DEFAULT NULL,
  `language` varchar(20) DEFAULT 'español',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `showtimes`
--

INSERT INTO `showtimes` (`id`, `movie_id`, `room_id`, `show_date`, `show_time`, `price`, `price_adult`, `price_child`, `price_senior`, `enable_child_price`, `enable_senior_price`, `half_price_monday`, `promotions`, `language`, `is_active`, `created_at`) VALUES
(52, 46, 12, '2026-08-10', '14:20:00', 3000.00, 3000.00, 500.00, 1500.00, 1, 1, 1, 'lunes_mitad', 'subtitulos', 1, '2026-08-03 05:13:01'),
(54, 46, 14, '2026-08-10', '22:50:00', 3000.00, 3000.00, 0.00, 1500.00, 0, 1, 0, '', 'español', 1, '2026-08-03 08:53:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `site_config`
--

CREATE TABLE `site_config` (
  `id` int(11) NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `site_config`
--

INSERT INTO `site_config` (`id`, `key_name`, `value`, `updated_at`) VALUES
(1, 'site_name', 'Cinema', '2026-07-25 05:06:09'),
(2, 'site_logo', 'uploads/logo.png', '2026-07-27 00:02:28'),
(3, 'currency_symbol', 'Bs', '2026-07-25 04:15:43'),
(4, 'currency_position', 'left', '2026-07-24 20:26:59'),
(5, 'thousands_separator', '.', '2026-07-24 20:26:59'),
(6, 'decimal_separator', ',', '2026-07-24 20:26:59'),
(7, 'decimal_places', '2', '2026-07-24 21:21:46'),
(8, 'address', 'Av. Intercomunal, CC Costa Mall, Nivel Feria, Ciudad Ojeda, Zulia, Venezuela', '2026-07-25 09:06:55'),
(9, 'phone', '04143601706', '2026-07-25 09:06:55'),
(10, 'email', 'contacto@cinemapro.com', '2026-07-25 09:06:55'),
(11, 'instagram', 'https://www.instagram.com/demavares/', '2026-07-24 21:21:46'),
(12, 'facebook', '', '2026-07-25 05:08:49'),
(13, 'twitter', 'https://x.com', '2026-07-24 20:26:59'),
(14, 'telegram', 'https://t.me', '2026-07-24 20:26:59'),
(15, 'whatsapp', 'https://wa.me/584143601706', '2026-07-24 21:25:57'),
(16, 'footer_copyright', 'Cinema Pro. Todos los derechos reservados.', '2026-07-24 20:26:59'),
(17, 'footer_logo', 'uploads/footer_logo.png', '2026-07-27 01:53:32'),
(18, 'site_favicon', '', '2026-07-27 06:08:43');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tax_config`
--

CREATE TABLE `tax_config` (
  `id` int(11) NOT NULL,
  `tax_name` varchar(50) NOT NULL DEFAULT 'IVA',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 16.00,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tax_config`
--

INSERT INTO `tax_config` (`id`, `tax_name`, `tax_rate`, `is_active`, `updated_at`) VALUES
(1, 'IVA', 16.00, 1, '2026-08-03 04:53:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `showtime_id` int(11) NOT NULL,
  `seat_code` varchar(10) NOT NULL,
  `purchase_date` timestamp NULL DEFAULT current_timestamp(),
  `price_paid` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_logs`
--

CREATE TABLE `ticket_logs` (
  `id` int(11) NOT NULL,
  `showtime_id` int(11) NOT NULL,
  `ticket_count` int(11) NOT NULL,
  `released_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_types`
--

CREATE TABLE `ticket_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ticket_types`
--

INSERT INTO `ticket_types` (`id`, `name`, `code`, `description`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'Adulto', 'adult', 'Precio estándar', 1, 1, '2026-08-03 04:53:32'),
(2, 'Niño', 'child', 'Menores de 12 años', 1, 2, '2026-08-03 04:53:32'),
(3, 'Tercera Edad', 'senior', 'Mayores de 60 años', 1, 3, '2026-08-03 04:53:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `cedula_type` enum('V','E','P') DEFAULT NULL,
  `cedula_number` varchar(20) DEFAULT NULL,
  `phone_prefix` varchar(10) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `is_blocked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `cedula_type`, `cedula_number`, `phone_prefix`, `phone_number`, `birth_date`, `password`, `role`, `is_blocked`, `created_at`, `last_login`) VALUES
(1, 'Administrador', 'admin@cinema.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$NOMst0oD6bh5Lrm8op6h8O5VIEaqXj70FjgMF7IeU9lAL0b4dwNPq', 'admin', 0, '2026-07-12 21:36:26', '2026-08-03 02:45:36'),
(5, 'Fulano Sutano', 'darwinmavares@gmail.com', 'V', '14511134', '414', '3601706', '1979-03-31', '$2y$10$7gM9QRKX7I4/HcKI0Q7TCuj0rpxRUPSk03f.MK9XhBCSEQtt2ru8G', 'user', 0, '2026-07-25 05:58:22', '2026-08-03 14:33:06');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indices de la tabla `food_categories`
--
ALTER TABLE `food_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `food_items`
--
ALTER TABLE `food_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indices de la tabla `food_orders`
--
ALTER TABLE `food_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `showtime_id` (`showtime_id`),
  ADD KEY `food_item_id` (`food_item_id`);

--
-- Indices de la tabla `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_movies_countries` (`country_id`);

--
-- Indices de la tabla `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `showtime_id` (`showtime_id`);

--
-- Indices de la tabla `purchase_tickets`
--
ALTER TABLE `purchase_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `showtime_id` (`showtime_id`),
  ADD KEY `ticket_type_id` (`ticket_type_id`);

--
-- Indices de la tabla `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indices de la tabla `showtimes`
--
ALTER TABLE `showtimes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_showtime` (`room_id`,`show_date`,`show_time`),
  ADD KEY `movie_id` (`movie_id`);

--
-- Indices de la tabla `site_config`
--
ALTER TABLE `site_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- Indices de la tabla `tax_config`
--
ALTER TABLE `tax_config`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_seat_showtime` (`showtime_id`,`seat_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `ticket_logs`
--
ALTER TABLE `ticket_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `showtime_id` (`showtime_id`);

--
-- Indices de la tabla `ticket_types`
--
ALTER TABLE `ticket_types`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cedula` (`cedula_type`,`cedula_number`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `food_categories`
--
ALTER TABLE `food_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `food_items`
--
ALTER TABLE `food_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `food_orders`
--
ALTER TABLE `food_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT de la tabla `purchase_tickets`
--
ALTER TABLE `purchase_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `showtimes`
--
ALTER TABLE `showtimes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT de la tabla `site_config`
--
ALTER TABLE `site_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `tax_config`
--
ALTER TABLE `tax_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=320;

--
-- AUTO_INCREMENT de la tabla `ticket_logs`
--
ALTER TABLE `ticket_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `ticket_types`
--
ALTER TABLE `ticket_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `food_items`
--
ALTER TABLE `food_items`
  ADD CONSTRAINT `food_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `food_categories` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `food_orders`
--
ALTER TABLE `food_orders`
  ADD CONSTRAINT `food_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_orders_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `food_orders_ibfk_3` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_orders_ibfk_4` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `movies`
--
ALTER TABLE `movies`
  ADD CONSTRAINT `fk_movies_countries` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `purchase_tickets`
--
ALTER TABLE `purchase_tickets`
  ADD CONSTRAINT `purchase_tickets_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_tickets_ibfk_2` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_tickets_ibfk_3` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `showtimes`
--
ALTER TABLE `showtimes`
  ADD CONSTRAINT `showtimes_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `showtimes_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ticket_logs`
--
ALTER TABLE `ticket_logs`
  ADD CONSTRAINT `ticket_logs_ibfk_1` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
