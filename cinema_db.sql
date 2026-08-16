-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: datame
-- Tiempo de generación: 16-08-2026 a las 07:16:15
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
(5, 'Palomita de maíz', 'Palomita de maíz', 1, '2026-07-29 02:54:48'),
(6, 'Snack', 'Snack', 1, '2026-08-06 17:09:03');

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
(1, 'Combo Papas', 'Combo', 1200.00, 'img/food_1785290273_6a695e2132a59.jpg', 1, 1, '2026-07-29 01:57:53'),
(2, 'Combo Hamburguera', 'Hamburguesa', 1500.00, 'img/food_1785292754_6a6967d284990.jpg', 1, 1, '2026-07-29 02:39:14'),
(3, 'Coca Cola', 'Coca Cola', 15.00, 'img/food_1785293633_6a696b4184245.jpg', 3, 1, '2026-07-29 02:53:53'),
(4, 'Cotufas Grande', '', 1500.00, 'img/food_1786207424_6a775cc0b44da.jpg', 5, 1, '2026-08-08 16:43:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `food_orders`
--

CREATE TABLE `food_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `showtime_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
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

INSERT INTO `food_orders` (`id`, `user_id`, `ticket_id`, `showtime_id`, `purchase_id`, `food_item_id`, `quantity`, `unit_price`, `total_price`, `order_date`, `status`) VALUES
(5, 8, NULL, 64, 502, 1, 1, 1200.00, 1200.00, '2026-08-15 01:46:57', 'completed'),
(6, 8, NULL, 64, 502, 4, 1, 1500.00, 1500.00, '2026-08-15 01:46:57', 'completed'),
(7, 8, NULL, 64, 504, 3, 1, 15.00, 15.00, '2026-08-15 03:37:14', 'completed'),
(8, 8, NULL, 64, 504, 4, 1, 1500.00, 1500.00, '2026-08-15 03:37:14', 'completed'),
(9, 8, NULL, 64, 510, 2, 1, 1500.00, 1500.00, '2026-08-15 04:03:45', 'completed'),
(10, 8, NULL, 64, 519, 3, 1, 15.00, 15.00, '2026-08-16 05:14:00', 'completed'),
(11, 8, NULL, 64, 519, 4, 1, 1500.00, 1500.00, '2026-08-16 05:14:00', 'completed'),
(12, 1, NULL, 64, 521, 3, 3, 15.00, 45.00, '2026-08-16 06:25:34', 'completed'),
(13, 1, NULL, 64, 521, 4, 3, 1500.00, 4500.00, '2026-08-16 06:25:34', 'completed');

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
(22, 'Spider-Man: Brand New Day', 'Han pasado cuatro años desde los acontecimientos de No Way Home, y Peter Parker ahora es un adulto que vive completamente solo, ha desaparecido voluntariamente de las vidas y recuerdos de quienes ama. Combatiendo el crimen en una Nueva York que ya no conoce su nombre, se ha dedicado por completo a proteger su ciudad, pero a medida que aumentan las exigencias sobre él, la presión desencadena una evolución física que amenaza su existencia, al mismo tiempo que un extraño nuevo patrón de crímenes da lugar a una de las amenazas más poderosas a las que se ha enfrentado.', 'https://image.tmdb.org/t/p/w500/9g0sEFhmvmK4nGhXj8DHuv2noYI.jpg', 'https://image.tmdb.org/t/p/original/qeQJx07rK2xm8SD2sJxFKhE7gs0.jpg', 'Tom Holland, Zendaya, Mark Ruffalo, Jon Bernthal, Jacob Batalon, Sadie Sink', 21, 144, 'Ciencia ficción, Acción, Aventura', '2026', 'Destin Daniel Cretton', 'B (Mayores de 12)', 'https://youtu.be/lBU9_kIJI9U?si=p76bf5PItFGwTUI_', 1, '2026-07-21 03:58:04'),
(35, 'Babymetal', 'Babymetal es un grupo idol japonés de kawaii metal formada en Tokio en 2010.', 'https://i.pinimg.com/originals/86/a3/a8/86a3a83128ec8089e613922bcdbfa99d.jpg', 'https://gritaradio.com/wp-content/uploads/2026/04/BM26-Anuncio_General_16x9-scaled.jpg', 'Suzuka Nakamoto (Su-metal), Moa Kikuchi (Moametal) y Momoko Okazaki (Momometal).', 27, 120, 'Música, Concierto', '2026', 'No disponible', 'A (Todo público)', 'https://youtu.be/EDnIEWyVIlE?si=Omr8eTQJu9wPUfe3', 1, '2026-07-27 03:25:09'),
(47, 'He-Man y los Masters del Universo', 'En las regiones más lejanas del espacio, el reino de Eternia está amenazado por el villano Skeletor y sus traviesos ejércitos de oscuridad. Para salvar el reino de su padre y proteger las vidas de sus seres queridos, el joven príncipe Adam tiene que recuperar una espada mítica y convertirse en el legendario guerrero conocido como He-Man.', 'https://image.tmdb.org/t/p/w500/A6kqScyPEsn6akDS5HyPf9jE4Od.jpg', 'https://image.tmdb.org/t/p/original/yQIdU11DYQQp0neGtGtGxbGfRer.jpg', 'Nicholas Galitzine, Camila Mendes, Idris Elba, Jared Leto, Alison Brie, Jóhannes Haukur Jóhannesson', 21, 140, 'Acción, Fantasía, Ciencia ficción', '2026', 'Travis Knight', 'A (Todo público)', 'https://youtu.be/LjLamj-b0I8?si=lALDYK1Vkjt42iZ9', 1, '2026-08-07 04:42:39'),
(54, 'Minions & Monsters', 'Narra la historia de cómo los minions conquistaron la industria de Hollywood, se convirtieron en estrellas de cine, lo perdieron todo, desataron monstruos en el mundo y luego se unieron para intentar salvar al planeta del caos que acababan de crear.', 'https://image.tmdb.org/t/p/w500/7DUzo8Ys7BfmZpqnzIwG4qA0egl.jpg', 'https://image.tmdb.org/t/p/original/kkcwhgSFd81QDlXo8ytrpHPQjhy.jpg', 'Pierre Coffin, Trey Parker, Christoph Waltz, Allison Janney, Jesse Eisenberg, Jeff Bridges', 21, 90, 'Aventura, Animación, Comedia, Familia, Fantasía', '2026', 'Pierre Coffin', 'A (Todo público)', 'https://youtu.be/lBU9_kIJI9U?si=p76bf5PItFGwTUI_', 1, '2026-08-11 13:26:41'),
(55, 'La casa del fin de los tiempos', 'La casa del fin de los tiempos es el primer thriller de suspenso y terror venezolano. Narra la historia de Dulce, Ruddy Rodríguez, una madre de familia que tiene encuentros con Apariciones dentro de su vieja casa, lugar donde debe descifrar un misterio que podría desencadenar una profecía: la muerte de su familia.', 'https://image.tmdb.org/t/p/w500/weNI3TFmC2JGYwEX4YKvYIKbGme.jpg', 'https://image.tmdb.org/t/p/original/ZIwr7usYLrVodguqdosMiXzJc5.jpg', 'Ruddy Rodriguez, Gonzalo Cubero, Guillermo García, Adriana Calzadilla, Rosmel Bustamante, Hector Mercado', 20, 100, 'Terror, Drama, Fantasía', '2013', 'Alejandro Hidalgo', 'C (Mayores de 18)', 'https://youtu.be/EDnIEWyVIlE?si=Omr8eTQJu9wPUfe3', 1, '2026-08-11 13:53:18'),
(63, 'The Exorcist', 'Regan es una niña de doce años víctima de fenómenos paranormales como la levitación o la manifestación de una fuerza sobrehumana. Su madre, aterrorizada, tras someter a su hija a múltiples análisis médicos que no ofrecen ningún resultado, acude a un sacerdote con estudios de psiquiatría. Éste está convencido de que el mal no es físico sino espiritual, es decir, que la niña es víctima de una posesión diabólica. Por eso, con la ayuda de otro sacerdote decide practicar un exorcismo.', 'https://image.tmdb.org/t/p/w500/hwJvgHQ4tf9eRQec1ouAgg33dPV.jpg', 'https://image.tmdb.org/t/p/original/xcjJ5khg2yzOa282mza39Lbrm7j.jpg', 'Ellen Burstyn, Linda Blair, Jason Miller, Max von Sydow, Lee J. Cobb, William O\'Malley', 21, 132, 'Terror', '1973', 'William Friedkin', 'C (Mayores de 18)', 'https://youtu.be/LjLamj-b0I8?si=lALDYK1Vkjt42iZ9', 1, '2026-08-12 09:14:15'),
(64, 'Rogue One', 'El Imperio Galáctico ha terminado de construir el arma más poderosa de todas, la Estrella de la muerte, pero un grupo de rebeldes decide realizar una misión de muy alto riesgo: robar los planos de dicha estación antes de que entre en operaciones, mientras se enfrentan también al poderoso Lord Sith conocido como Darth Vader, discípulo del despiadado Emperador Palpatine.', 'https://image.tmdb.org/t/p/w500/mAqgFQxaBaLkcQBRQf9YnAz9sNQ.jpg', 'https://image.tmdb.org/t/p/original/6t8ES1d12OzWyCGxBeDYLHoaDrT.jpg', 'Felicity Jones, Diego Luna, Alan Tudyk, Donnie Yen, Jiang Wen, Ben Mendelsohn', 21, 133, 'Acción, Aventura, Ciencia ficción', '2016', 'Gareth Edwards', 'B (Mayores de 12)', 'https://youtu.be/jQ5lPt9edzQ?si=5DhEB7WjQQRdmT1o', 1, '2026-08-12 09:19:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `showtime_id` int(11) NOT NULL,
  `seats` text NOT NULL,
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

INSERT INTO `purchases` (`id`, `user_id`, `showtime_id`, `seats`, `total_tickets`, `total_food`, `total_amount`, `subtotal`, `tax_amount`, `tax_rate`, `purchase_date`, `status`, `payment_method`, `payment_data`, `session_token`, `expires_at`) VALUES
(517, 8, 64, 'A1', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-16 05:10:34', 'expired', NULL, NULL, 'adbaa58d53fe6b5c32aec1d982796af95d246a207aa55b051b01965de7c8a96a', '2026-08-16 01:11:11'),
(518, 8, 64, 'A1', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-16 05:13:49', 'expired', NULL, NULL, '0073890cc2595769e974f6f30e309a8f4cdf09ff4835f31e4095c29b90309e36', '2026-08-16 01:14:00'),
(519, 8, 64, 'A1', 1, 1515.00, 5237.40, 4515.00, 722.40, 16.00, '2026-08-16 05:14:00', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260816-71829C8D\",\"method\":\"movil\",\"reference\":\"CMP-20260816-829CAF\",\"date\":\"2026-08-16 01:14:00\",\"ip\":\"172.22.0.1\"}', '96c290bae6a32a5e3d7076f811bb202ec59937e0aaada3180b245aa98e00af1c', '2026-08-16 01:24:00'),
(520, 1, 64, 'A12,A11,A10', 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-16 06:25:17', 'expired', NULL, NULL, '11357922d097dc8cfcaef84495637e11596e6839e9206f8136f1e59cdb93bc12', '2026-08-16 02:25:34'),
(521, 1, 64, 'A12,A11,A10', 3, 4545.00, 11072.20, 9545.00, 1527.20, 16.00, '2026-08-16 06:25:34', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260816-7DE0C07F\",\"method\":\"movil\",\"reference\":\"CMP-20260816-E0C0A8\",\"date\":\"2026-08-16 02:25:34\",\"ip\":\"172.22.0.1\"}', 'a3b73e7cbac27d39ab9a0e9c9c73f7d43f42285a29e8dcab09047a2b4b88ea6f', '2026-08-16 02:35:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rooms`
--

INSERT INTO `rooms` (`id`, `name`, `capacity`, `description`, `seat_layout`, `aisle_config`, `is_active`, `created_at`) VALUES
(12, 'Sala 1', 161, 'sala', '{\"rows\":[\"A\",\"B\",\"C\",\"D\",\"E\",\"F\",\"G\",\"H\",\"I\",\"J\"],\"seatsPerRow\":21,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"C\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"D\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"E\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"F\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"G\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"H\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"I\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"J\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]},\"totalSeats\":210,\"blockedSeats\":[\"A4\",\"A5\",\"A14\",\"A15\",\"A16\",\"B4\",\"B5\",\"B15\",\"B16\",\"C4\",\"C5\",\"C15\",\"C16\",\"D4\",\"D5\",\"D15\",\"D16\",\"E4\",\"E5\",\"E15\",\"E16\",\"F4\",\"F5\",\"F15\",\"F16\",\"G4\",\"G5\",\"G15\",\"G16\",\"H4\",\"H5\",\"H15\",\"H16\",\"I4\",\"I5\",\"I15\",\"I16\",\"J10\",\"J11\",\"J12\",\"J13\",\"J14\",\"J15\",\"J16\",\"J17\",\"J18\",\"J19\",\"J20\",\"J21\"]}', '{\"blockedSeats\":[\"A4\",\"A5\",\"A14\",\"A15\",\"A16\",\"B4\",\"B5\",\"B15\",\"B16\",\"C4\",\"C5\",\"C15\",\"C16\",\"D4\",\"D5\",\"D15\",\"D16\",\"E4\",\"E5\",\"E15\",\"E16\",\"F4\",\"F5\",\"F15\",\"F16\",\"G4\",\"G5\",\"G15\",\"G16\",\"H4\",\"H5\",\"H15\",\"H16\",\"I4\",\"I5\",\"I15\",\"I16\",\"J10\",\"J11\",\"J12\",\"J13\",\"J14\",\"J15\",\"J16\",\"J17\",\"J18\",\"J19\",\"J20\",\"J21\"]}', 1, '2026-07-13 06:15:33'),
(13, 'Sala 2', 146, '', '{\"rows\":[\"A\",\"B\",\"C\",\"D\",\"E\",\"F\",\"G\",\"H\"],\"seatsPerRow\":21,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"C\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"D\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"E\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"F\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"G\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"H\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]},\"totalSeats\":168,\"blockedSeats\":[\"A9\",\"A16\",\"B9\",\"B16\",\"C9\",\"C16\",\"D9\",\"D16\",\"E9\",\"E16\",\"F9\",\"F10\",\"F11\",\"F12\",\"F13\",\"F14\",\"F15\",\"F16\",\"G9\",\"G16\",\"H9\",\"H16\"],\"wheelchairSeats\":[\"A10\",\"A11\",\"A12\",\"A13\",\"A14\",\"A15\"]}', '{\"blockedSeats\":[\"A9\",\"A16\",\"B9\",\"B16\",\"C9\",\"C16\",\"D9\",\"D16\",\"E9\",\"E16\",\"F9\",\"F10\",\"F11\",\"F12\",\"F13\",\"F14\",\"F15\",\"F16\",\"G9\",\"G16\",\"H9\",\"H16\"],\"wheelchairSeats\":[\"A10\",\"A11\",\"A12\",\"A13\",\"A14\",\"A15\"]}', 1, '2026-07-13 15:11:33'),
(14, 'Sala 3', 21, '', '{\"rows\":[\"A\",\"B\"],\"seatsPerRow\":21,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]},\"totalSeats\":42,\"blockedSeats\":[\"A13\",\"A14\",\"A15\",\"A16\",\"A17\",\"A18\",\"A19\",\"A20\",\"A21\",\"B10\",\"B11\",\"B12\",\"B13\",\"B14\",\"B15\",\"B16\",\"B17\",\"B18\",\"B19\",\"B20\",\"B21\"],\"wheelchairSeats\":[\"A11\",\"B8\",\"B9\"]}', '{\"blockedSeats\":[\"A13\",\"A14\",\"A15\",\"A16\",\"A17\",\"A18\",\"A19\",\"A20\",\"A21\",\"B10\",\"B11\",\"B12\",\"B13\",\"B14\",\"B15\",\"B16\",\"B17\",\"B18\",\"B19\",\"B20\",\"B21\"],\"wheelchairSeats\":[\"A11\",\"B8\",\"B9\"]}', 1, '2026-07-13 23:38:47'),
(16, 'Sala 4', 127, '', '{\"rows\":[\"A\",\"B\",\"C\",\"D\",\"E\",\"F\",\"G\"],\"seatsPerRow\":20,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"C\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"D\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"E\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"F\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20],\"G\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20]},\"totalSeats\":140,\"blockedSeats\":[\"C9\",\"C10\",\"B10\",\"B9\",\"A9\",\"A10\",\"D10\",\"D9\",\"E9\",\"E10\",\"F9\",\"F10\",\"F11\"]}', '{\"blockedSeats\":[\"C9\",\"C10\",\"B10\",\"B9\",\"A9\",\"A10\",\"D10\",\"D9\",\"E9\",\"E10\",\"F9\",\"F10\",\"F11\"]}', 1, '2026-07-26 04:21:53');

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
  `format` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `showtimes`
--

INSERT INTO `showtimes` (`id`, `movie_id`, `room_id`, `show_date`, `show_time`, `price`, `price_adult`, `price_child`, `price_senior`, `enable_child_price`, `enable_senior_price`, `half_price_monday`, `promotions`, `language`, `format`, `is_active`, `created_at`) VALUES
(64, 18, 14, '2026-08-24', '14:30:00', 3000.00, 3000.00, 500.00, 1500.00, 1, 1, 1, 'lunes_mitad,preventa', 'español', '3D', 1, '2026-08-11 04:12:47'),
(66, 18, 14, '2026-08-13', '22:00:00', 3000.00, 3000.00, 0.00, 1500.00, 0, 1, 0, '', 'español', 'ScreenX', 0, '2026-08-13 23:56:05');

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
(12, 'facebook', 'https://facebook.com', '2026-08-12 13:15:17'),
(13, 'twitter', 'https://x.com', '2026-07-24 20:26:59'),
(14, 'telegram', 'https://t.me', '2026-07-24 20:26:59'),
(15, 'whatsapp', 'https://wa.me/584143601706', '2026-07-24 21:25:57'),
(16, 'footer_copyright', 'Cinema Pro. Todos los derechos reservados.', '2026-07-24 20:26:59'),
(17, 'footer_logo', 'uploads/footer_logo.png', '2026-07-27 01:53:32'),
(18, 'site_favicon', 'uploads/favicon.png', '2026-08-11 05:39:24'),
(20, 'last_cleanup_expired_purchases', '2026-08-14 05:14:26', '2026-08-14 09:14:26');

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
(1, 'IVA', 16.00, 1, '2026-08-12 13:15:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `showtime_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL COMMENT 'NULL mientras es reserva temporal (hold)',
  `ticket_type_id` int(11) DEFAULT NULL COMMENT 'NULL mientras es reserva temporal (hold)',
  `seat_code` varchar(10) NOT NULL,
  `price_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('hold','confirmed') NOT NULL DEFAULT 'hold' COMMENT 'hold=reserva temporal | confirmed=pagado',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `confirmed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tickets`
--

INSERT INTO `tickets` (`id`, `user_id`, `showtime_id`, `purchase_id`, `ticket_type_id`, `seat_code`, `price_paid`, `status`, `created_at`, `confirmed_at`) VALUES
(2, 8, 64, 519, 1, 'A1', 3000.00, 'confirmed', '2026-08-16 05:13:49', '2026-08-16 05:14:00'),
(3, 1, 64, 521, 1, 'A12', 3000.00, 'confirmed', '2026-08-16 06:25:17', '2026-08-16 06:25:34'),
(4, 1, 64, 521, 2, 'A11', 500.00, 'confirmed', '2026-08-16 06:25:17', '2026-08-16 06:25:34'),
(5, 1, 64, 521, 3, 'A10', 1500.00, 'confirmed', '2026-08-16 06:25:17', '2026-08-16 06:25:34');

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
(1, 'Administrador', 'admin@cinema.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$NOMst0oD6bh5Lrm8op6h8O5VIEaqXj70FjgMF7IeU9lAL0b4dwNPq', 'admin', 0, '2026-07-12 21:36:26', '2026-08-16 02:21:13'),
(8, 'Darwin Mavarez', 'darwinmavares@gmail.com', 'V', '14511134', '414', '3601706', '1979-03-31', '$2y$10$OBPu7dEtLSDfXPtMLYx6c.p8u6kI2QWnM9tPW9F9no4Yr7KS.dM2C', 'user', 0, '2026-08-09 00:24:33', '2026-08-16 00:59:29');

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
  ADD KEY `showtime_id` (`showtime_id`),
  ADD KEY `food_item_id` (`food_item_id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `food_orders_ibfk_2` (`ticket_id`);

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
  ADD KEY `showtime_id` (`showtime_id`),
  ADD KEY `idx_status_fecha` (`status`,`purchase_date`),
  ADD KEY `idx_user_showtime` (`user_id`,`showtime_id`),
  ADD KEY `idx_status_expires` (`status`,`expires_at`);

--
-- Indices de la tabla `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip_action` (`ip_address`,`action`),
  ADD KEY `idx_window_start` (`window_start`);

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
  ADD UNIQUE KEY `unique_showtime_seat` (`showtime_id`,`seat_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_ticket_type` (`ticket_type_id`),
  ADD KEY `idx_status` (`status`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `food_items`
--
ALTER TABLE `food_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `food_orders`
--
ALTER TABLE `food_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT de la tabla `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=522;

--
-- AUTO_INCREMENT de la tabla `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `showtimes`
--
ALTER TABLE `showtimes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT de la tabla `site_config`
--
ALTER TABLE `site_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `tax_config`
--
ALTER TABLE `tax_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `ticket_logs`
--
ALTER TABLE `ticket_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ticket_types`
--
ALTER TABLE `ticket_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
-- Filtros para la tabla `showtimes`
--
ALTER TABLE `showtimes`
  ADD CONSTRAINT `showtimes_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `showtimes_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_fk_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_fk_type` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE SET NULL,
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
