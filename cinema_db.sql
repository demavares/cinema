-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: datame
-- Tiempo de generación: 05-09-2026 a las 10:29:08
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
(3, 'Coca Cola', 'Coca Cola', 15.00, '', 3, 1, '2026-07-29 02:53:53'),
(4, 'Cotufas Grande', '', 1500.00, 'img/food_1788106672_6a9457b019b4f.jpg', 5, 1, '2026-08-08 16:43:44');

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
(21, 1, NULL, 72, 586, 1, 1, 1200.00, 1200.00, '2026-08-30 07:18:36', 'completed');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_rate_limits`
--

CREATE TABLE `login_rate_limits` (
  `rate_limit_key` varchar(191) NOT NULL,
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `first_attempt_at` int(10) UNSIGNED DEFAULT NULL,
  `last_attempt_at` int(10) UNSIGNED NOT NULL,
  `blocked_until` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `login_rate_limits`
--

INSERT INTO `login_rate_limits` (`rate_limit_key`, `attempts`, `first_attempt_at`, `last_attempt_at`, `blocked_until`, `created_at`, `updated_at`) VALUES
('account:68051dd5cc3e50d1badd08c415c1ad694f326923082a0824be28e7c59ce23da7', 1, 1787558243, 1787558243, NULL, '2026-08-24 07:57:23', '2026-08-24 07:57:23'),
('account:c0030ece54609ec378730c2f994df76edeb4d57fec0464332126338ec133c443', 1, 1788597163, 1788597163, NULL, '2026-08-22 06:38:01', '2026-09-05 08:32:43'),
('action:movie_detail_view:ip:172.22.0.1', 1, 1788603774, 1788603774, NULL, '2026-08-23 15:55:30', '2026-09-05 10:22:54'),
('ip:172.22.0.1', 1, 1788597163, 1788597163, NULL, '2026-08-22 06:38:01', '2026-09-05 08:32:43');

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
(35, 'Babymetal', 'Babymetal es un grupo idol japonés de kawaii metal formada en Tokio en 2010.', 'https://cdn.shopify.com/s/files/1/0689/6061/6685/files/20251120_imp_fest_2026_vo4_referral_social_babymetal_4x5_c853eafa-872e-4d0c-9d1c-ea063dc17bad.jpg', 'https://gritaradio.com/wp-content/uploads/2026/04/BM26-Anuncio_General_16x9-scaled.jpg', 'Suzuka Nakamoto (Su-metal), Moa Kikuchi (Moametal) y Momoko Okazaki (Momometal).', 27, 120, 'Música, Concierto', '2026', 'No disponible', 'A (Todo público)', 'https://youtu.be/EDnIEWyVIlE?si=Omr8eTQJu9wPUfe3', 1, '2026-07-27 03:25:09'),
(55, 'La casa del fin de los tiempos', 'La casa del fin de los tiempos es el primer thriller de suspenso y terror venezolano. Narra la historia de Dulce, Ruddy Rodríguez, una madre de familia que tiene encuentros con Apariciones dentro de su vieja casa, lugar donde debe descifrar un misterio que podría desencadenar una profecía: la muerte de su familia.', 'https://image.tmdb.org/t/p/w500/weNI3TFmC2JGYwEX4YKvYIKbGme.jpg', 'https://image.tmdb.org/t/p/original/ZIwr7usYLrVodguqdosMiXzJc5.jpg', 'Ruddy Rodriguez, Gonzalo Cubero, Guillermo García, Adriana Calzadilla, Rosmel Bustamante, Hector Mercado', 20, 100, 'Terror, Drama, Fantasía', '2013', 'Alejandro Hidalgo', 'C (Mayores de 18)', 'https://youtu.be/EDnIEWyVIlE?si=Omr8eTQJu9wPUfe3', 1, '2026-08-11 13:53:18'),
(70, 'Alien', 'De regreso a la Tierra, la nave de carga Nostromo interrumpe su viaje y despierta a sus siete tripulantes. El ordenador central, MADRE, ha detectado la misteriosa transmisión de una forma de vida desconocida, procedente de un planeta cercano aparentemente deshabitado. La nave se dirige entonces al extraño planeta para investigar el origen de la comunicación.', 'https://image.tmdb.org/t/p/w500/pZ9cAe5FxexJjpCaeiETbXzz3Fs.jpg', 'https://image.tmdb.org/t/p/original/AmR3JG1VQVxU8TfAvljUhfSFUOx.jpg', 'Tom Skerritt, Sigourney Weaver, Veronica Cartwright, Harry Dean Stanton, John Hurt, Ian Holm', 21, 117, 'Terror, Ciencia ficción', '1979', 'Ridley Scott', 'C (Mayores de 18)', 'https://www.youtube.com/watch?v=oSeQQlaCZgU', 1, '2026-08-26 17:04:25'),
(72, 'Star Wars: Episode IV - A New Hope', 'La princesa Leia, líder del movimiento rebelde que desea reinstaurar la República en la galaxia en los tiempos ominosos del Imperio, es capturada por las malévolas Fuerzas Imperiales, capitaneadas por el implacable Darth Vader, el sirviente más fiel del emperador. El intrépido Luke Skywalker, ayudado por Han Solo, capitán de la nave espacial \"El Halcón Milenario\", y los androides, R2D2 y C3PO, serán los encargados de luchar contra el enemigo y rescatar a la princesa para volver a instaurar la justicia en el seno de la Galaxia.', 'https://image.tmdb.org/t/p/original/wmIVGytmj4TmSQ45YvQaHi95Xv9.jpg', 'https://image.tmdb.org/t/p/original/yUiXA68FfQeA8cRBhd0Ao0jIRZt.jpg', 'Mark Hamill, Harrison Ford, Carrie Fisher, Peter Cushing, Alec Guinness, Anthony Daniels', 21, 121, 'Aventura, Acción, Ciencia ficción', '1977', 'George Lucas', 'A (Todo público)', 'https://youtu.be/EDnIEWyVIlE?si=Omr8eTQJu9wPUfe3', 1, '2026-08-30 08:56:43');

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
(586, 1, 72, 'G7', 1, 1200.00, 4872.00, 4200.00, 672.00, 16.00, '2026-08-30 07:18:36', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260830-94C26BB2\",\"method\":\"movil\",\"reference\":\"CMP-20260830-C26BD9\",\"date\":\"2026-08-30 03:18:36\",\"ip\":\"172.22.0.1\"}', '942bf47fd7cc80725331b20dac5ed6a450234d2a16731574c168618ead9c2e5f', '2026-08-30 03:18:36'),
(597, 1, 77, 'F12', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-31 03:14:21', 'expired', NULL, NULL, '61c35da5a13c4861acdc3b6d7afdfb59b9a566ddd91f855004aaaaf49f020026', '2026-08-30 23:24:21'),
(599, 1, 77, 'B14', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-09-05 08:33:37', 'expired', NULL, NULL, '538fa017bcf040bd1158191edea932e91dd05ad0e55bf9f3076a8ed2f5f71b9d', '2026-09-05 04:39:02'),
(600, 1, 77, 'E19', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-09-05 09:01:52', 'expired', NULL, NULL, 'b6dd29e6b5f86360775265cec535bcea06c7ac87bc290305129a41d81641e3ac', '2026-09-05 05:02:20');

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
(12, 'Sala 1', 173, 'sala', '{\"rows\":[\"A\",\"B\",\"C\",\"D\",\"E\",\"F\",\"G\",\"H\",\"I\",\"J\"],\"seatsPerRow\":21,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"C\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"D\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"E\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"F\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"G\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"H\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"I\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"J\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]},\"totalSeats\":210,\"blockedSeats\":[\"A4\",\"A5\",\"A14\",\"A15\",\"A16\",\"B4\",\"B5\",\"B15\",\"B16\",\"C4\",\"C5\",\"C15\",\"C16\",\"D4\",\"D5\",\"D15\",\"D16\",\"E4\",\"E5\",\"E15\",\"E16\",\"F4\",\"F5\",\"F15\",\"F16\",\"G4\",\"G5\",\"G15\",\"G16\",\"H4\",\"H5\",\"H15\",\"H16\",\"I4\",\"I5\",\"I15\",\"I16\"],\"wheelchairSeats\":[]}', '{\"blockedSeats\":[\"A4\",\"A5\",\"A14\",\"A15\",\"A16\",\"B4\",\"B5\",\"B15\",\"B16\",\"C4\",\"C5\",\"C15\",\"C16\",\"D4\",\"D5\",\"D15\",\"D16\",\"E4\",\"E5\",\"E15\",\"E16\",\"F4\",\"F5\",\"F15\",\"F16\",\"G4\",\"G5\",\"G15\",\"G16\",\"H4\",\"H5\",\"H15\",\"H16\",\"I4\",\"I5\",\"I15\",\"I16\"],\"wheelchairSeats\":[]}', 1, '2026-07-13 06:15:33'),
(13, 'Sala 2', 146, '', '{\"rows\":[\"A\",\"B\",\"C\",\"D\",\"E\",\"F\",\"G\",\"H\"],\"seatsPerRow\":21,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"C\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"D\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"E\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"F\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"G\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"H\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]},\"totalSeats\":168,\"blockedSeats\":[\"A9\",\"A16\",\"B9\",\"B16\",\"C9\",\"C16\",\"D9\",\"D16\",\"E9\",\"E16\",\"F9\",\"F10\",\"F11\",\"F12\",\"F13\",\"F14\",\"F15\",\"F16\",\"G9\",\"G16\",\"H9\",\"H16\"],\"wheelchairSeats\":[\"A10\",\"A11\",\"A12\",\"A13\",\"A14\",\"A15\"]}', '{\"blockedSeats\":[\"A9\",\"A16\",\"B9\",\"B16\",\"C9\",\"C16\",\"D9\",\"D16\",\"E9\",\"E16\",\"F9\",\"F10\",\"F11\",\"F12\",\"F13\",\"F14\",\"F15\",\"F16\",\"G9\",\"G16\",\"H9\",\"H16\"],\"wheelchairSeats\":[\"A10\",\"A11\",\"A12\",\"A13\",\"A14\",\"A15\"]}', 1, '2026-07-13 15:11:33'),
(14, 'Sala 3', 21, '', '{\"rows\":[\"A\",\"B\"],\"seatsPerRow\":21,\"seatMap\":{\"A\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21],\"B\":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]},\"totalSeats\":42,\"blockedSeats\":[\"A13\",\"A14\",\"A15\",\"A16\",\"A17\",\"A18\",\"A19\",\"A20\",\"A21\",\"B10\",\"B11\",\"B12\",\"B13\",\"B14\",\"B15\",\"B16\",\"B17\",\"B18\",\"B19\",\"B20\",\"B21\"],\"wheelchairSeats\":[\"A11\",\"B8\",\"B9\",\"A12\"]}', '{\"blockedSeats\":[\"A13\",\"A14\",\"A15\",\"A16\",\"A17\",\"A18\",\"A19\",\"A20\",\"A21\",\"B10\",\"B11\",\"B12\",\"B13\",\"B14\",\"B15\",\"B16\",\"B17\",\"B18\",\"B19\",\"B20\",\"B21\"],\"wheelchairSeats\":[\"A11\",\"B8\",\"B9\",\"A12\"]}', 1, '2026-07-13 23:38:47'),
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
(72, 70, 12, '2026-08-30', '03:30:00', 3000.00, 3000.00, 0.00, 1500.00, 0, 1, 0, '', 'español', '2D', 0, '2026-08-30 07:24:46'),
(77, 72, 12, '2026-09-08', '14:35:00', 3000.00, 3000.00, 0.00, 0.00, 0, 0, 0, '', 'subtitulos', '4DX', 1, '2026-08-31 03:13:06'),
(79, 72, 14, '2026-09-28', '14:30:00', 3000.00, 3000.00, 500.00, 1500.00, 1, 1, 0, '', 'español', '2D', 1, '2026-09-05 09:51:15');

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
(2, 'site_logo', 'uploads/logo.png', '2026-08-23 15:29:52'),
(3, 'currency_symbol', 'Bs', '2026-07-25 04:15:43'),
(4, 'currency_position', 'left', '2026-07-24 20:26:59'),
(5, 'thousands_separator', '.', '2026-07-24 20:26:59'),
(6, 'decimal_separator', ',', '2026-07-24 20:26:59'),
(7, 'decimal_places', '2', '2026-07-24 21:21:46'),
(8, 'address', 'Plaza Bolívar, Cd Ojeda 4019, Zulia', '2026-08-30 17:49:03'),
(9, 'phone', '04143601706', '2026-07-25 09:06:55'),
(10, 'email', 'contacto@cinemapro.com', '2026-07-25 09:06:55'),
(11, 'instagram', 'https://www.instagram.com/demavares/', '2026-07-24 21:21:46'),
(12, 'facebook', 'https://facebook.com', '2026-08-12 13:15:17'),
(13, 'twitter', 'https://x.com', '2026-07-24 20:26:59'),
(14, 'telegram', '', '2026-08-23 17:02:08'),
(15, 'whatsapp', 'https://wa.me/584143601706', '2026-07-24 21:25:57'),
(16, 'footer_copyright', '© {year} Cinema. Todos los derechos reservados. RIF: J-00123456-7', '2026-08-21 16:50:00'),
(17, 'footer_logo', 'uploads/footer_logo.png', '2026-07-27 01:53:32'),
(18, 'site_favicon', 'uploads/favicon.png', '2026-08-11 05:39:24'),
(19, 'timezone', 'America/Caracas', '2026-09-05 08:32:26'),
(20, 'last_cleanup_expired_purchases', '2026-09-05 04:32:26', '2026-09-05 08:32:26');

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
(1, 'IVA', 16.00, 1, '2026-08-23 17:28:33');

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
(67, 1, 72, 586, 1, 'G7', 3000.00, 'confirmed', '2026-08-30 07:18:22', '2026-08-30 07:18:36');

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

--
-- Volcado de datos para la tabla `ticket_logs`
--

INSERT INTO `ticket_logs` (`id`, `showtime_id`, `ticket_count`, `released_at`) VALUES
(4, 72, 1, '2026-08-30 09:38:26'),
(6, 72, 1, '2026-08-30 14:28:58');

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
  `avatar` varchar(255) DEFAULT NULL,
  `delete_requested_at` datetime DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `is_blocked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `cedula_type`, `cedula_number`, `phone_prefix`, `phone_number`, `birth_date`, `avatar`, `delete_requested_at`, `password`, `role`, `is_blocked`, `created_at`, `last_login`) VALUES
(1, 'Administrador', 'admin@cinema.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$NOMst0oD6bh5Lrm8op6h8O5VIEaqXj70FjgMF7IeU9lAL0b4dwNPq', 'admin', 0, '2026-07-12 21:36:26', '2026-09-05 04:32:54'),
(8, 'Darwin Mavares', 'darwinmavares@gmail.com', 'V', '14511134', '412', '3601706', '1979-03-31', NULL, NULL, '$2y$10$OBPu7dEtLSDfXPtMLYx6c.p8u6kI2QWnM9tPW9F9no4Yr7KS.dM2C', 'user', 0, '2026-08-09 00:24:33', '2026-08-22 01:41:34');

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
-- Indices de la tabla `login_rate_limits`
--
ALTER TABLE `login_rate_limits`
  ADD PRIMARY KEY (`rate_limit_key`),
  ADD KEY `idx_last_attempt_at` (`last_attempt_at`),
  ADD KEY `idx_blocked_until` (`blocked_until`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT de la tabla `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=601;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT de la tabla `site_config`
--
ALTER TABLE `site_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `tax_config`
--
ALTER TABLE `tax_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT de la tabla `ticket_logs`
--
ALTER TABLE `ticket_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
