-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: datame
-- Tiempo de generación: 24-08-2026 a las 14:24:27
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
(13, 1, NULL, 64, 521, 4, 3, 1500.00, 4500.00, '2026-08-16 06:25:34', 'completed'),
(14, 1, NULL, 64, 524, 2, 1, 1500.00, 1500.00, '2026-08-16 07:40:13', 'completed'),
(15, 1, NULL, 64, 530, 3, 1, 15.00, 15.00, '2026-08-19 05:57:56', 'completed'),
(16, 1, NULL, 64, 530, 4, 1, 1500.00, 1500.00, '2026-08-19 05:57:56', 'completed'),
(17, 1, NULL, 64, 534, 1, 1, 1200.00, 1200.00, '2026-08-20 10:54:05', 'completed'),
(18, 1, NULL, 64, 539, 1, 1, 1200.00, 1200.00, '2026-08-20 13:49:34', 'completed'),
(19, 8, NULL, 64, 562, 3, 1, 15.00, 15.00, '2026-08-21 22:39:21', 'completed'),
(20, 8, NULL, 64, 562, 4, 1, 1500.00, 1500.00, '2026-08-21 22:39:21', 'completed');

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
('account:c0030ece54609ec378730c2f994df76edeb4d57fec0464332126338ec133c443', 1, 1787380681, 1787380681, NULL, '2026-08-22 06:38:01', '2026-08-22 06:38:01'),
('action:movie_detail_view:ip:172.22.0.1', 1, 1787579625, 1787579625, NULL, '2026-08-23 15:55:30', '2026-08-24 13:53:45'),
('ip:172.22.0.1', 1, 1787558243, 1787558243, NULL, '2026-08-22 06:38:01', '2026-08-24 07:57:23');

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
(18, 'Aliens', 'Alien es un organismo perfecto, una máquina de matar cuya superioridad física sólo puede competir con su agresividad. La oficial Ripley y la tripulación de la nave «Nostromo» se habían enfrentado, en el pasado, a esa monstruosa criatura. Y sólo Ripley sobrevivió a la masacre. Después de vagar por el espacio durante varios años, Ripley fue rescatada. Durante ese tiempo, el planeta de Alien ha sido colonizado. Pero, de repente, se pierde la comunicación con la colonia y, para investigar los motivos, se envía una expedición de marines espaciales, capitaneados por Ripley. Allí les esperan miles de espeluznantes criaturas. Alien se ha reproducido y esta vez la lucha es por la supervivencia de la Humanidad.', 'https://image.tmdb.org/t/p/w500/3QU9EP8BFLnTh6w9ycDSCvhqbRU.jpg', 'https://image.tmdb.org/t/p/original/4kix6fAblJIH6eMs0Ku2loyZJXK.jpg', 'Sigourney Weaver, Carrie Henn, Michael Biehn, Paul Reiser, Lance Henriksen, Bill Paxton', 21, 150, 'Acción, Suspense, Ciencia ficción', '1986', 'James Cameron', 'C (Mayores de 18)', 'https://www.youtube.com/watch?v=oSeQQlaCZgU', 1, '2026-07-20 01:06:32'),
(22, 'Spider-Man: Brand New Day', 'Han pasado cuatro años desde los acontecimientos de No Way Home, y Peter Parker ahora es un adulto que vive completamente solo, ha desaparecido voluntariamente de las vidas y recuerdos de quienes ama. Combatiendo el crimen en una Nueva York que ya no conoce su nombre, se ha dedicado por completo a proteger su ciudad, pero a medida que aumentan las exigencias sobre él, la presión desencadena una evolución física que amenaza su existencia, al mismo tiempo que un extraño nuevo patrón de crímenes da lugar a una de las amenazas más poderosas a las que se ha enfrentado.', 'https://image.tmdb.org/t/p/w500/tluwRNA7k0XfTtDkdLYKX1KOSCJ.jpg', 'https://image.tmdb.org/t/p/original/qeQJx07rK2xm8SD2sJxFKhE7gs0.jpg', 'Tom Holland, Zendaya, Mark Ruffalo, Jon Bernthal, Jacob Batalon, Sadie Sink', 21, 144, 'Ciencia ficción, Acción, Aventura', '2026', 'Destin Daniel Cretton', 'B (Mayores de 12)', 'https://youtu.be/lBU9_kIJI9U?si=p76bf5PItFGwTUI_', 1, '2026-07-21 03:58:04'),
(35, 'Babymetal', 'Babymetal es un grupo idol japonés de kawaii metal formada en Tokio en 2010.', 'https://cdn.shopify.com/s/files/1/0689/6061/6685/files/20251120_imp_fest_2026_vo4_referral_social_babymetal_4x5_c853eafa-872e-4d0c-9d1c-ea063dc17bad.jpg', 'https://gritaradio.com/wp-content/uploads/2026/04/BM26-Anuncio_General_16x9-scaled.jpg', 'Suzuka Nakamoto (Su-metal), Moa Kikuchi (Moametal) y Momoko Okazaki (Momometal).', 27, 120, 'Música, Concierto', '2026', 'No disponible', 'A (Todo público)', 'https://youtu.be/EDnIEWyVIlE?si=Omr8eTQJu9wPUfe3', 1, '2026-07-27 03:25:09'),
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
(521, 1, 64, 'A12,A11,A10', 3, 4545.00, 11072.20, 9545.00, 1527.20, 16.00, '2026-08-16 06:25:34', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260816-7DE0C07F\",\"method\":\"movil\",\"reference\":\"CMP-20260816-E0C0A8\",\"date\":\"2026-08-16 02:25:34\",\"ip\":\"172.22.0.1\"}', 'a3b73e7cbac27d39ab9a0e9c9c73f7d43f42285a29e8dcab09047a2b4b88ea6f', '2026-08-16 02:35:34'),
(522, 1, 64, 'A2', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-16 07:39:42', 'expired', NULL, NULL, 'c651a41afdb19d87fabe14953bceeb3497bcaf9918b39ae348f9da6322d228a9', '2026-08-16 03:39:56'),
(523, 1, 64, 'A2', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-16 07:40:02', 'expired', NULL, NULL, '5cb9568047d04fa3183c2f6f5c64718e88ac3bb47f3bbe25a3f3d8da9d14ec17', '2026-08-16 03:40:13'),
(524, 1, 64, 'A2', 1, 1500.00, 5220.00, 4500.00, 720.00, 16.00, '2026-08-16 07:40:13', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260816-95D80B97\",\"method\":\"movil\",\"reference\":\"CMP-20260816-D80BC0\",\"date\":\"2026-08-16 03:40:13\",\"ip\":\"172.22.0.1\"}', 'ec7135ba5eff6649640f3ebfd03003a7d225f79456e78b6d10ba1db5741405c4', '2026-08-16 03:40:13'),
(525, 1, 64, 'A9', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-17 20:51:22', 'expired', NULL, NULL, '91e5c0c691b7224e0b796ccdabe5051ff5ebbc4c7e558316e21dfec1421d2599', '2026-08-17 16:53:14'),
(526, 1, 64, 'A9', 1, 0.00, 1740.00, 1500.00, 240.00, 16.00, '2026-08-17 20:53:14', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260817-4BA8D330\",\"method\":\"movil\",\"reference\":\"CMP-20260817-A8D36B\",\"date\":\"2026-08-17 16:53:14\",\"ip\":\"172.22.0.1\"}', '503c4a5d6e9cb7afff2389df2b3576ce40cee877a1ff31da4163ea92d9f60e23', '2026-08-17 16:53:14'),
(527, 1, 64, 'A3', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-18 08:41:13', 'expired', NULL, NULL, '3d9bb818b0c435fc9c14df582fe1b777699fbba74b867c31b777f5977a72754e', '2026-08-18 04:49:08'),
(528, 1, 64, 'A3', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-18 09:53:18', 'expired', NULL, NULL, '8062f55517813cbbfc35cf59bec4f9328398ebe8560ffb190dd080176ed9e909', '2026-08-18 05:53:37'),
(529, 1, 64, 'B9', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-19 05:57:40', 'expired', NULL, NULL, '6fd0c0055e1e08e9d98046ce20a7707795cd8f2b26877239e2d51ed99fa4587a', '2026-08-19 01:57:56'),
(530, 1, 64, 'B9', 1, 1515.00, 5237.40, 4515.00, 722.40, 16.00, '2026-08-19 05:57:56', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260819-5E4CED2C\",\"method\":\"movil\",\"reference\":\"CMP-20260819-4CED53\",\"date\":\"2026-08-19 01:57:56\",\"ip\":\"172.22.0.1\"}', '24e5fce0e8c9708292a3e631ecad1c8b3e924d6dfbc7acf0dd63b443f96ae34f', '2026-08-19 01:57:56'),
(531, 1, 64, 'A8', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-19 18:57:18', 'expired', NULL, NULL, '63bec087fd2e99f4d9e9d493732b563e103c7d145e02d7c15bc318e36abeb250', '2026-08-19 14:57:23'),
(532, 1, 64, 'A8', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-20 10:38:26', 'expired', NULL, NULL, 'af757eb26df5a23d4e3a7a1292a87317203fac32856269f4097a7942ce498175', '2026-08-20 06:41:26'),
(533, 1, 64, 'A3', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-20 10:45:25', 'expired', NULL, NULL, 'eef1da9266e4735afd379dc272eccba67890b54a4707528b14e0936449a1d356', '2026-08-20 06:54:05'),
(534, 1, 64, 'A3', 1, 1200.00, 4872.00, 4200.00, 672.00, 16.00, '2026-08-20 10:54:05', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260820-CCD3F791\",\"method\":\"movil\",\"reference\":\"CMP-20260820-D3F8D8\",\"date\":\"2026-08-20 06:54:05\",\"ip\":\"172.22.0.1\"}', '984ca45d432d203aeb9ba3982a8e3533a1be62e62f7d1741c73b01b3c318cd0e', '2026-08-20 06:54:05'),
(535, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-20 11:00:12', 'expired', NULL, NULL, '24bd1264e68d7babfeddc16c9af13b3456958fab16a97458844848be3b116151', '2026-08-20 07:10:12'),
(536, 8, 64, 'B1', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-20 11:11:10', 'expired', NULL, NULL, 'b788b407ae006ff32e0998d35c32589d6011fbccf2dd03d25973d5b10ca9d14b', '2026-08-20 07:21:10'),
(537, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-20 12:11:44', 'expired', NULL, NULL, 'a5182ecd80f435b4541584ded4368d9155ab72ef62296b2fcdc4026ba1aeda13', '2026-08-20 08:11:53'),
(538, 1, 64, 'B1', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-20 13:42:16', 'expired', NULL, NULL, 'c5a4c87c3c41097a5a24c42543195f5dbdfb3f82f636bc9e4ba871f960e0834b', '2026-08-20 09:49:34'),
(539, 1, 64, 'B1', 1, 1200.00, 4872.00, 4200.00, 672.00, 16.00, '2026-08-20 13:49:34', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260820-5EE7B699\",\"method\":\"movil\",\"reference\":\"CMP-20260820-E7B6B5\",\"date\":\"2026-08-20 09:49:34\",\"ip\":\"172.22.0.1\"}', '38fd0fc5e465ac080536392aed7578b7870f03937f52d6459d0ac74fff42b36d', '2026-08-20 09:49:34'),
(540, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 00:20:18', 'expired', NULL, NULL, 'ca48d2dc62a245aee02482b12979e8e2939ec2d482cdbf9f768769a78fffa99d', '2026-08-20 20:30:18'),
(541, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 01:23:03', 'expired', NULL, NULL, '34cdd101e7e13e6781b54333a8f6a7c0cf07e9aae8b7c376e96dfa192f8a5bfb', '2026-08-20 21:24:45'),
(542, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 01:25:08', 'expired', NULL, NULL, '90f060c214929166dd66f588f4031be1786d5bd5baaeffd962e47e8897f8a353', '2026-08-20 21:25:11'),
(543, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 01:25:34', 'expired', NULL, NULL, 'a53eebac87e902932ee2f9ed079078b13512b17d508662e04775d9fa282f90d7', '2026-08-20 21:25:37'),
(544, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 01:26:10', 'expired', NULL, NULL, 'a4624292de8dc2330e36e318d070af8356c9e8e616bbb924cb2e451ad4cef971', '2026-08-20 21:26:13'),
(545, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 01:28:05', 'expired', NULL, NULL, 'a454bc9f376784835a4efb182083d025835b3e64e0b1249bad6d92203751bb5b', '2026-08-20 21:29:01'),
(546, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 02:37:17', 'expired', NULL, NULL, '0bb315c2065d4c40a96bc027679deeac59540d6c60a2dc05d2b3f4f0fb013e7f', '2026-08-20 22:37:40'),
(547, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 03:08:05', 'expired', NULL, NULL, 'eaeb4d10e77dad55b24406bfca3e0e8a9690ce506a4fe1ea6cf8eab62b7a9d2e', '2026-08-20 23:08:13'),
(548, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 04:04:23', 'expired', NULL, NULL, '10b676cf286471c956786a9d804b23c12db594af371eb5523ff4580e2af1e837', '2026-08-21 00:12:04'),
(549, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 04:12:11', 'expired', NULL, NULL, '448652db797eed5bd1ca02858c29020a8e11d5e25878c9b1f404fafb44762ace', '2026-08-21 00:22:11'),
(550, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 11:54:48', 'expired', NULL, NULL, 'e8ed5e65735af3155a03d1326356f3e729c0877448ab71c2290d7d340cb58f69', '2026-08-21 08:04:48'),
(551, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 12:53:12', 'expired', NULL, NULL, 'bed636446a8ce74e519db5d0c0e2cb03a3375dccd94e5c8ac97b188f4ab1e7b4', '2026-08-21 09:03:12'),
(552, 8, 64, 'B2', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 13:10:30', 'expired', NULL, NULL, 'e0b1aa3672f50d7b9ef5bbdf6d71926a72faf57e8881cb890b9fdc41715c890a', '2026-08-21 09:20:30'),
(553, 1, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 18:16:52', 'expired', NULL, NULL, 'df40d2b02411fb559baabb6b2585c1b76c3c0609b1012af33491751685b7ca79', '2026-08-21 14:26:52'),
(554, 8, 64, 'B2', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 19:48:46', 'expired', NULL, NULL, '98c44525d3ccd5d393c4652c231fbb1c79e197e52180637cb9335b4edf90b30e', '2026-08-21 15:58:46'),
(555, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 20:00:40', 'expired', NULL, NULL, 'dc4ad7d2b4cacbcf072099ce8e54df224ab24abe1fadd0d759ba47eba7a50cc6', '2026-08-21 16:20:54'),
(556, 8, 64, 'B2', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 20:21:09', 'expired', NULL, NULL, 'a68b2dc83e71166f8e3ac0cb0607d20ea68a1b5d5dbc9b8a17baf5ecb57ffec4', '2026-08-21 16:21:53'),
(557, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 20:24:52', 'expired', NULL, NULL, '9edeaf39500710f842eb28901965350060908d487bf02466c822ddba4b9dc200', '2026-08-21 16:25:43'),
(558, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 20:25:57', 'expired', NULL, NULL, 'fb29468e13359f651d527394a8f5c401f81b8b16b285b374cae3e5b80a781e8d', '2026-08-21 16:35:10'),
(559, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 22:37:58', 'expired', NULL, NULL, 'cb55e3cfd79afd9ca0d1526b0e4c4eb2b0a5aa0a4d2319ace63536f7941edd14', '2026-08-21 18:38:13'),
(560, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 22:38:24', 'expired', NULL, NULL, '21b03c33686f051d1947fb4efc80cdf80b5ffce44b126e1af399f67fcccf17f0', '2026-08-21 18:38:52'),
(561, 8, 64, 'A4', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 22:39:10', 'expired', NULL, NULL, '421aab5e6fdbd57ba6ce2c6c17f34c616c4d4f01fbc700fc57b257bde228bad5', '2026-08-21 18:39:21'),
(562, 8, 64, 'A4', 1, 1515.00, 5237.40, 4515.00, 722.40, 16.00, '2026-08-21 22:39:21', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260821-3994DDDB\",\"method\":\"movil\",\"reference\":\"CMP-20260821-94DDFF\",\"date\":\"2026-08-21 18:39:21\",\"ip\":\"172.22.0.1\"}', '6a98607ad74c8c1c82076165551e995a6296cce74139cca353d5ca19c67eb8e6', '2026-08-21 18:39:21'),
(563, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 22:42:30', 'expired', NULL, NULL, '5971c109a58950da290d1e5ab615e5294243aafdd39846723c68299d5543cbdd', '2026-08-21 18:42:38'),
(564, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 22:52:35', 'expired', NULL, NULL, '0958dbfebc18aa764144083c1af7c15c544759a5a0db145513f568267b7d8d9a', '2026-08-21 18:52:38'),
(565, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:26:07', 'expired', NULL, NULL, '5cbea3f846c29b5cae35852b3b89bac587e2656beaeaff38f1e687088bcaa51b', '2026-08-21 19:26:12'),
(566, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:26:44', 'expired', NULL, NULL, 'cd99681e951ac11973091d601d32c4ddba7861620263eccf5993b31712d17317', '2026-08-21 19:27:10'),
(567, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:27:57', 'expired', NULL, NULL, '1e2f022427fb8cb176c2bc4a4852adb214b9e024cfdc41e8b9a1c543fdcefc2b', '2026-08-21 19:28:09'),
(568, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:28:25', 'expired', NULL, NULL, 'd636cf35d44ad1f0746a4393a0eceb8060f9fd7761a02dbccebb008b13d62faa', '2026-08-21 19:28:51'),
(569, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:29:07', 'expired', NULL, NULL, 'edcecbc6e6ee5a3943f05d215ab44cdfb04690cd9296d02ba089b4d409f063b9', '2026-08-21 19:29:22'),
(570, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:29:36', 'expired', NULL, NULL, '111eab5a4b99547897542788e5c5ca216ad2862890d76d4af3bbd97cfc50d101', '2026-08-21 19:29:42'),
(571, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:30:43', 'expired', NULL, NULL, 'acbe602c18cdfe09582b2426cfa7da66e4fa9c8309d1282fedf95e33cd692a4d', '2026-08-21 19:30:47'),
(572, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:30:51', 'expired', NULL, NULL, 'a6d3afb0b92aec427ee4fe35336a3f2e98ac0d2d8e12c63e959b928262122513', '2026-08-21 19:31:05'),
(573, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:39:15', 'expired', NULL, NULL, '1a08b64d53800ccaa7c4bab1da7d56a79571c113df008dac1bae11abf984edc5', '2026-08-21 19:39:25'),
(574, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:40:06', 'expired', NULL, NULL, '4020598b4b85dda524d5a59452f01941dcf7b4f47c66037b849b2817e9f9107e', '2026-08-21 19:41:39'),
(575, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-21 23:41:58', 'expired', NULL, NULL, '841f3c575bc389db1c470bf4e18d19b556d5a38057db4fb91aa0f723a7dc7e6b', '2026-08-21 19:42:09'),
(576, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-22 00:54:59', 'expired', NULL, NULL, 'c7ea8d63db9687d1f6cdc8f6f72e2a13eab89ef118bb98e7885bebe701caca5d', '2026-08-21 20:55:24'),
(577, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-22 00:55:57', 'expired', NULL, NULL, '39c39b89afcebd168b2d1ebd4cd71b0f3d6d4320b791d32e25e96ffc660325e9', '2026-08-21 21:05:57'),
(578, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-22 04:24:23', 'expired', NULL, NULL, '8d28fe3166043fc756eff381130fb041b12fec3cac2ea28139e81c38ecadeb9e', '2026-08-22 00:34:44'),
(579, 8, 64, 'A5', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-22 05:43:50', 'expired', NULL, NULL, '351d66748008a95205baa205f98d2e9c338ba6b621904a56adc0f756f3d8d1ae', '2026-08-22 01:44:19'),
(580, 1, 68, 'F7,F8', 2, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-23 16:19:50', 'expired', NULL, NULL, '27bec210cac3870f839266687f9287df234d19430d62249743d10a658d156bb4', '2026-08-23 12:20:04'),
(581, 1, 68, 'J9', 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-24 12:58:02', 'expired', NULL, NULL, '4a2915ba9ad705248e1bcdfb7743946e1bfa5e8a4c893a6d3c45377494c39af3', '2026-08-24 08:58:11'),
(582, 1, 68, 'J9', 1, 0.00, 3480.00, 3000.00, 480.00, 16.00, '2026-08-24 12:58:11', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260824-FE3F1202\",\"method\":\"movil\",\"reference\":\"CMP-20260824-3F153C\",\"date\":\"2026-08-24 08:58:11\",\"ip\":\"172.22.0.1\"}', '88579ead391742a6aaf5d3d4c0b33e5332a1a5c5deffe15e0cfadc45954837c7', '2026-08-24 08:58:11');

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
  `seat_map_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `showtimes`
--

INSERT INTO `showtimes` (`id`, `movie_id`, `room_id`, `show_date`, `show_time`, `price`, `price_adult`, `price_child`, `price_senior`, `enable_child_price`, `enable_senior_price`, `half_price_monday`, `promotions`, `language`, `format`, `seat_map_image`, `is_active`, `created_at`) VALUES
(64, 18, 14, '2026-08-24', '14:30:00', 3000.00, 3000.00, 500.00, 1500.00, 1, 1, 1, 'lunes_mitad,preventa', 'español', '2D', NULL, 1, '2026-08-11 04:12:47'),
(68, 18, 12, '2026-08-25', '15:24:00', 3000.00, 3000.00, 0.00, 1500.00, 0, 1, 0, '', 'subtitulos', '2D', 'uploads/seatmap_1787547381_6a8bcef5b745b.jpg', 1, '2026-08-23 16:00:22');

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
(8, 'address', 'Plaza Bolivar, Cd Ojeda 4019, Zulia', '2026-08-23 17:28:33'),
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
(20, 'last_cleanup_expired_purchases', '2026-08-20 01:00:18', '2026-08-20 05:00:18');

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
(2, 8, 64, 519, 1, 'A1', 3000.00, 'confirmed', '2026-08-16 05:13:49', '2026-08-16 05:14:00'),
(3, 1, 64, 521, 1, 'A12', 3000.00, 'confirmed', '2026-08-16 06:25:17', '2026-08-16 06:25:34'),
(4, 1, 64, 521, 2, 'A11', 500.00, 'confirmed', '2026-08-16 06:25:17', '2026-08-16 06:25:34'),
(5, 1, 64, 521, 3, 'A10', 1500.00, 'confirmed', '2026-08-16 06:25:17', '2026-08-16 06:25:34'),
(7, 1, 64, 524, 1, 'A2', 3000.00, 'confirmed', '2026-08-16 07:40:02', '2026-08-16 07:40:13'),
(8, 1, 64, 526, 1, 'A9', 1500.00, 'confirmed', '2026-08-17 20:51:22', '2026-08-17 20:53:14'),
(11, 1, 64, 530, 1, 'B9', 3000.00, 'confirmed', '2026-08-19 05:57:40', '2026-08-19 05:57:56'),
(14, 1, 64, 534, 1, 'A3', 3000.00, 'confirmed', '2026-08-20 10:45:25', '2026-08-20 10:54:05'),
(18, 1, 64, 539, 1, 'B1', 3000.00, 'confirmed', '2026-08-20 13:42:16', '2026-08-20 13:49:34'),
(42, 8, 64, 562, 1, 'A4', 3000.00, 'confirmed', '2026-08-21 22:39:10', '2026-08-21 22:39:21'),
(62, 1, 68, 582, 1, 'J9', 3000.00, 'confirmed', '2026-08-24 12:58:02', '2026-08-24 12:58:11');

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
(1, 'Administrador', 'admin@cinema.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$NOMst0oD6bh5Lrm8op6h8O5VIEaqXj70FjgMF7IeU9lAL0b4dwNPq', 'admin', 0, '2026-07-12 21:36:26', '2026-08-24 08:47:28'),
(8, 'Darwin Mavares', 'darwinmavares@gmail.com', 'V', '14511134', '414', '3601706', '1979-03-31', '$2y$10$OBPu7dEtLSDfXPtMLYx6c.p8u6kI2QWnM9tPW9F9no4Yr7KS.dM2C', 'user', 0, '2026-08-09 00:24:33', '2026-08-22 01:41:34');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT de la tabla `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=583;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT de la tabla `ticket_logs`
--
ALTER TABLE `ticket_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
