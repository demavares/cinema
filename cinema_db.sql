-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: datame
-- Tiempo de generación: 06-08-2026 a las 17:15:30
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
(1, 5, NULL, 54, 229, 3, 2, 15.00, 30.00, '2026-08-05 08:39:00', 'completed'),
(2, 1, NULL, 55, 231, 1, 1, 1200.00, 1200.00, '2026-08-05 08:42:32', 'completed'),
(3, 1, NULL, 55, 233, 3, 3, 15.00, 45.00, '2026-08-05 08:43:40', 'completed'),
(4, 1, NULL, 55, 235, 1, 1, 1200.00, 1200.00, '2026-08-05 08:51:45', 'completed'),
(5, 1, NULL, 55, 235, 2, 1, 1500.00, 1500.00, '2026-08-05 08:51:45', 'completed'),
(6, 1, NULL, 55, 235, 3, 1, 15.00, 15.00, '2026-08-05 08:51:45', 'completed');

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
(133, 5, 52, 'D14', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 18:33:22', 'expired', NULL, NULL, 'e3d97e1ca3e1213aa83868e262a2c9430b27bc26e5171018803bcdee32634a29', '2026-08-03 14:43:22'),
(134, 5, 52, 'A17,A18,A19', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 20:02:23', 'expired', NULL, NULL, 'bf7a27be632ab1a5bf0ef36cb14b61bcd5fdc324d36c5e40b34ea8340ed184ad', '2026-08-03 16:12:23'),
(136, 5, 52, 'A19,A18,A20', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 20:34:19', 'expired', NULL, NULL, 'e849011fb11c229a86ee7b0cd7344920568a82160fac26e53d8e7c8775fb9d49', '2026-08-03 16:44:19'),
(137, 5, 54, 'A9,A10,A11', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-03 20:34:43', 'expired', NULL, NULL, '8973301e24aec01e7d3ffe0f3a49c1b358403f09229dae9555cbbc60967d42ea', '2026-08-03 16:52:39'),
(138, 1, 52, 'A21,A20,A19', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 04:00:27', 'expired', NULL, NULL, 'e66af1256767c09e8664a4d311f1fb2dc5c517b7ee632a0e1b7bffae34424ab2', '2026-08-04 00:10:27'),
(139, 1, 52, 'D20,E20,F20', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 04:58:36', 'expired', NULL, NULL, '77c09b24e3992a23820c8df16f3b1c5bcc7d4aaea77d2afc7b0dccab563c555c', '2026-08-04 01:11:29'),
(153, 1, 54, 'A11', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 08:32:25', 'expired', NULL, NULL, '362da90affcf4e154d23fffdb53594529cd0b7991095b1d49bcbe0493cfadc11', '2026-08-04 04:42:25'),
(154, 1, 54, 'A12', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 08:49:40', 'expired', NULL, NULL, '2495cb4196de7420a3ad8c552e1a2e53bfd4632a98b6006b8eb7e419573676e9', '2026-08-04 04:59:47'),
(156, 1, 52, 'I21,H21', NULL, 2, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 09:04:54', 'expired', NULL, NULL, 'd0cdffb0359e68b53099bb5658bf96250392ce15f9d16b4692486261118575c4', '2026-08-04 05:14:54'),
(157, 5, 52, 'I21,H21,G21', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 14:23:50', 'expired', NULL, NULL, '83075a0f14f0f54764d08f5931c4e192776ae8a98b0f3657eb12f59f0345c43c', '2026-08-04 10:34:09'),
(162, 5, 52, 'I21', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 15:08:04', 'expired', NULL, NULL, '5082d648713180dc38aea0033c759c8104fdfc802a9b5dd582943d97a2fa59e2', '2026-08-04 11:18:04'),
(163, 5, 52, 'I21,H21', NULL, 2, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 15:23:30', 'expired', NULL, NULL, '40755500c59e5b2bd8c2bba0c752e349e0c6d7e195d47b1ac91456b43fb7fa89', '2026-08-04 11:33:30'),
(165, 5, 52, 'I21,H21', NULL, 2, 0.00, 6960.00, 6000.00, 960.00, 16.00, '2026-08-04 15:34:10', 'completed', 'movil', '{\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260804-28BB7A\",\"date\":\"2026-08-04 11:34:10\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '7ae682747a45f4981648db6e6d2ebdf7bc49d1717750503bc200bc7310d0ae80', '2026-08-04 11:44:10'),
(167, 5, 52, 'G21', NULL, 1, 0.00, 3480.00, 3000.00, 480.00, 16.00, '2026-08-04 15:36:12', 'completed', 'movil', '{\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260804-CD373E\",\"date\":\"2026-08-04 11:36:12\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'a910644e69b289cd654557ca06b456b29dac36ea8cf176a922a34a4d4f2e19fa', '2026-08-04 11:46:12'),
(168, 5, 52, 'I17', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 15:42:51', 'expired', NULL, NULL, 'd1a3e7f0125c7c615509681a03f7785b0071c3361bf4faa6b3aae239eb04bd36', '2026-08-04 11:52:51'),
(169, 5, 52, 'I17', NULL, 1, 1230.00, 4710.00, 3000.00, 480.00, 16.00, '2026-08-04 15:43:03', 'completed', 'movil', '{\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260804-731BE2\",\"date\":\"2026-08-04 11:43:03\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '7ccaaedce4019d7b3d33aa1821d798b2c7f236e5bacbe5dfed32e84adbc87a67', '2026-08-04 11:53:03'),
(171, 5, 52, 'I17', NULL, 1, 0.00, 3480.00, 3000.00, 480.00, 16.00, '2026-08-04 16:15:29', 'completed', 'movil', '{\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260804-1DF3EA\",\"date\":\"2026-08-04 12:15:29\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '7ab0a39f14f77cb673117074f960187b88fc011833fecab4ee5e4ab130f6367b', '2026-08-04 12:25:29'),
(179, 5, 54, 'B1', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-04 16:42:43', 'expired', NULL, NULL, 'a7d27995cf7be74eebf3b1d457fd7cc5368db281643ba9d8f0b44888dfddd60b', '2026-08-04 12:52:43'),
(181, 5, 54, 'A12,A11', NULL, 2, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 04:09:34', 'expired', NULL, NULL, '2ce9d244eac8d4283ac3753bc03adb1c6865bb25b802c529b6699f462dfed909', '2026-08-05 00:19:34'),
(186, 5, 54, 'A1', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 05:11:27', 'expired', NULL, NULL, '2ffb90e5db809dd85f6cc8222b9385f77639c170377ede134cdc4354a81e514b', '2026-08-05 01:21:27'),
(187, 5, 54, 'A1', NULL, 1, 0.00, 3480.00, 3000.00, 480.00, 16.00, '2026-08-05 05:11:39', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-60B52EE4\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-B5304C\",\"date\":\"2026-08-05 01:11:39\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '582c6c87096a36f8c8b87ef86ac3f31b830fdfb77bf20e7150bfe27d12bf2f93', '2026-08-05 01:21:39'),
(189, 1, 54, 'A1', NULL, 1, 0.00, 3480.00, 3000.00, 480.00, 16.00, '2026-08-05 06:33:41', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-9458503B\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-5851AB\",\"date\":\"2026-08-05 02:33:41\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '01d2560c2d1129d8775ad99a1e1338653a85a95fd2e391b88072db12f9b8c7fa', '2026-08-05 02:43:41'),
(191, 5, 54, 'A2,A3', NULL, 2, 30.00, 6990.00, 6000.00, 960.00, 16.00, '2026-08-05 06:38:48', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-A78D0807\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-8D1A32\",\"date\":\"2026-08-05 02:38:48\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '6bb6bf7b1ae2399c9cfb3d3e54c97d831b506324f5e27b28df1ba90eec248574', '2026-08-05 02:48:48'),
(192, 5, 52, 'A17,A18,A19', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 06:45:38', 'expired', NULL, NULL, '4fc4b300ac5bf8dfca4cffcee58c0be04e257804c5be562d5de75b45701a4d15', '2026-08-05 02:55:38'),
(193, 5, 52, 'A17,A18,A19', NULL, 3, 1200.00, 11640.00, 9000.00, 1440.00, 16.00, '2026-08-05 06:45:52', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-C20F288E\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-0F36F9\",\"date\":\"2026-08-05 02:45:52\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'aa79ef6acf99ecb3be4252e27a172b58aae45694359f9ed73551ae31a9d7dc65', '2026-08-05 02:55:52'),
(195, 5, 54, 'A11♿,A12', NULL, 2, 4215.00, 11175.00, 6000.00, 960.00, 16.00, '2026-08-05 07:08:56', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-188F112A\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-8F29A1\",\"date\":\"2026-08-05 03:08:56\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '89be81a2baae4df7d835bec0dfeb82dfd0472607e26505fc73378fb85c40580e', '2026-08-05 03:18:56'),
(197, 5, 54, 'A4,A5', NULL, 2, 2715.00, 9675.00, 6000.00, 960.00, 16.00, '2026-08-05 07:12:17', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-2519701D\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-198090\",\"date\":\"2026-08-05 03:12:17\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '1d5518de4f8e75e34940845cada5bc2e327365aafd4045c1605bbca8181734f2', '2026-08-05 03:22:17'),
(198, 5, 54, 'A6,A7', NULL, 2, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 07:17:35', 'expired', NULL, NULL, '7cce4f61067e22126f97fb4f45e7178cc70c37b72788fae131eaccaeeab92383', '2026-08-05 03:27:35'),
(199, 5, 54, 'A6,A7', NULL, 2, 2715.00, 9675.00, 6000.00, 960.00, 16.00, '2026-08-05 07:17:48', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-39C54068\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-C55C51\",\"date\":\"2026-08-05 03:17:48\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '75e276ff041fd014e71f781ff5f3b78d7af94fb3a6a66a587a96223800b91882', '2026-08-05 03:27:48'),
(201, 5, 52, 'A17,A18,A19', NULL, 3, 2715.00, 13155.00, 9000.00, 1440.00, 16.00, '2026-08-05 07:22:01', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-499722D6\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-973B9D\",\"date\":\"2026-08-05 03:22:01\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '4894b21adf69a5e6b0a9ec7b78c00d6d038fe0cb3f4681fe1080f8fdd5947979', '2026-08-05 03:32:01'),
(203, 5, 52, 'B17,B18,B19', NULL, 3, 5430.00, 15870.00, 9000.00, 1440.00, 16.00, '2026-08-05 07:27:39', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-5EBBF082\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-BBF5F1\",\"date\":\"2026-08-05 03:27:39\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'c5c81c99492462bd44a8a1535c310e03ad9d43e89aded3985da7d2b23aaf4581', '2026-08-05 03:37:39'),
(204, 5, 52, 'C17,C19,C18', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 07:28:44', 'expired', NULL, NULL, '2a8be74dbdd823e622880f542579ae4d37a4d17cbdea98809978ad65b01793f2', '2026-08-05 03:38:44'),
(205, 5, 52, 'C17,C19,C18', NULL, 3, 2715.00, 13155.00, 9000.00, 1440.00, 16.00, '2026-08-05 07:29:11', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-64710533\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-711BD3\",\"date\":\"2026-08-05 03:29:11\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '433bb8b07c2a1e62692dd443241aadc9b455e202ad562030a798aa978ed29b54', '2026-08-05 03:39:11'),
(207, 5, 52, 'C17,C18,C19', NULL, 3, 5430.00, 15870.00, 9000.00, 1440.00, 16.00, '2026-08-05 07:39:28', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-8B086408\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-086956\",\"date\":\"2026-08-05 03:39:28\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '1ef2f2557af54e5b0723c84fd63841bd2c8c1abfe05de2b3dd4a7e1b9d435a9a', '2026-08-05 03:49:28'),
(209, 5, 52, 'A20', NULL, 1, 30.00, 3510.00, 3000.00, 480.00, 16.00, '2026-08-05 07:44:08', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-9C853293\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-854178\",\"date\":\"2026-08-05 03:44:08\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '2dbe8473945204c5ac7ae28bc8ef8b1fa5b5bf69ce1f11e1c7be87a51feb9344', '2026-08-05 03:54:08'),
(211, 5, 52, 'B20,B21,A21', NULL, 3, 2715.00, 13155.00, 9000.00, 1440.00, 16.00, '2026-08-05 07:45:13', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-A0977655\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-9786E6\",\"date\":\"2026-08-05 03:45:13\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'dad6fad0aac9075b2c157bcd5d99ebe4f95a52cd3950323f54f0fbde6e769a53', '2026-08-05 03:55:13'),
(213, 5, 52, 'C20', NULL, 1, 2715.00, 6195.00, 3000.00, 480.00, 16.00, '2026-08-05 07:47:40', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-A9C9B955\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-C9DF3D\",\"date\":\"2026-08-05 03:47:40\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '2ce5b06e43dd4b933be0f6e61bf3e035e1c13983513e5d542b774b47a77b7c50', '2026-08-05 03:57:40'),
(215, 5, 52, 'C21,D21', NULL, 2, 5430.00, 12390.00, 6000.00, 960.00, 16.00, '2026-08-05 07:49:12', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-AF8678D7\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-86930F\",\"date\":\"2026-08-05 03:49:12\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'f3f1f02d34cb3c88f8334f7fa04d5d4802d2f583ae17154e851b0637c8a9354c', '2026-08-05 03:59:12'),
(216, 5, 52, 'D20,D19,D18', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 07:52:52', 'expired', NULL, NULL, '3a9ed57a2027f5c6fe559509df125f2b74e18383cf72c84a34c7a7d326d6b30d', '2026-08-05 04:02:52'),
(217, 5, 52, 'D20,D19,D18', NULL, 3, 2715.00, 13155.00, 9000.00, 1440.00, 16.00, '2026-08-05 07:53:03', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-BDF1B30E\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-F1CD6D\",\"date\":\"2026-08-05 03:53:03\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'a9874bc4debe944bd93d3c7ef34e0894feeb7b18bfbe4554db8f1cafddbb2d80', '2026-08-05 04:03:03'),
(218, 5, 52, 'D20,D19,D18', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 08:03:56', 'expired', NULL, NULL, '9c3356aa84afef5cc75425bbf298df4d62a652eb3c7bf60d4cc0053b68362907', '2026-08-05 04:13:56'),
(219, 5, 52, 'D20,D19,D18', NULL, 3, 2715.00, 13155.00, 9000.00, 1440.00, 16.00, '2026-08-05 08:05:17', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-EBD5175A\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-D519E3\",\"date\":\"2026-08-05 04:05:17\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '1c99822d86a730212e2e08fe1c4816aecc8b5a1e55b07d286f9a56d0077af6b1', '2026-08-05 04:15:17'),
(220, 5, 54, 'A10', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 08:08:02', 'expired', NULL, NULL, 'a0fc820e07780084023cb5985720b4ab880c4262739065beb6b5bcda0a207a16', '2026-08-05 04:18:02'),
(221, 5, 54, 'A10', NULL, 1, 4245.00, 7725.00, 3000.00, 480.00, 16.00, '2026-08-05 08:08:13', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-F6D6671C\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-D66869\",\"date\":\"2026-08-05 04:08:13\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'd0b2b18437e6bfaa38e2e42d6119c8ede380b79631700bec8d6a3d71ce7fdd1b', '2026-08-05 04:18:13'),
(222, 5, 52, 'D20,D19,D18', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 08:18:12', 'expired', NULL, NULL, '16a52794e81afb4c79f5ff4f0f08cb01bf2eda2c634d6a7846fbcc6e81d01f1a', '2026-08-05 04:28:12'),
(223, 5, 52, 'D20,D19,D18', NULL, 3, 2715.00, 13155.00, 9000.00, 1440.00, 16.00, '2026-08-05 08:19:23', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-20B9AF74\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-B9B4CD\",\"date\":\"2026-08-05 04:19:23\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '97f73f4888c4a20f524e6746cab8efc841bf6d5a66b9d0618de0856f1d711535', '2026-08-05 04:29:23'),
(225, 5, 52, 'D20,D19,D18', NULL, 3, 2730.00, 15277.20, 13170.00, 2107.20, 16.00, '2026-08-05 08:31:54', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-4FA0BDD6\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-A0C083\",\"date\":\"2026-08-05 04:31:54\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '85ef56df0cf3c4b55490ae9d6e389facb9f331454d262808972309701767b171', '2026-08-05 04:41:54'),
(226, 5, 52, 'E21,E20,E19', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 08:33:22', 'expired', NULL, NULL, '22c649333fa1cb91412ddf2e5d400d9f25185f6aeca710ed4a36531a3038f75f', '2026-08-05 04:43:22'),
(227, 5, 52, 'E21,E20,E19', NULL, 3, 2715.00, 15259.80, 13155.00, 2104.80, 16.00, '2026-08-05 08:33:33', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-55D75632\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-D76635\",\"date\":\"2026-08-05 04:33:33\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'a3b4c5bb755cdfb799403476321dc413e7e74641cffcf80f0da4544b50b6e2ff', '2026-08-05 04:43:33'),
(228, 5, 54, 'A10', NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 08:38:51', 'expired', NULL, NULL, '0954b9417706a0f7c705756fb51cd284f4a0cc83ecc7e7e8bfca6b18b6100ed4', '2026-08-05 04:48:51'),
(229, 5, 54, 'A10', NULL, 1, 30.00, 4071.60, 3510.00, 561.60, 16.00, '2026-08-05 08:39:00', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-6A438708\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-43884A\",\"date\":\"2026-08-05 04:39:00\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '70931a114e0fe182d9dacc047405108164e16e2ce70e55eaa798ddbfd609a562', '2026-08-05 04:49:00'),
(231, 1, 55, 'A1,A2,A3', NULL, 3, 1200.00, 9465.60, 8160.00, 1305.60, 16.00, '2026-08-05 08:42:32', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-778AF7B2\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-8AFD11\",\"date\":\"2026-08-05 04:42:32\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', 'b7163d01777462a3614f74de2a8bfd2f9f544894c4d6d65d33ee01a10d2066ab', '2026-08-05 04:52:32'),
(233, 1, 55, 'A4,A5', NULL, 2, 45.00, 5434.60, 4685.00, 749.60, 16.00, '2026-08-05 08:43:40', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-7BC60AEB\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-C6102E\",\"date\":\"2026-08-05 04:43:40\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '1b345138b382a972cb8b9c79c59715c301e5006ba27be5ab37a0c0ce12ebe3e9', '2026-08-05 04:53:40'),
(234, 1, 55, 'A6,A7,A8', NULL, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-08-05 08:51:28', 'expired', NULL, NULL, 'f253ff8f8b680059bd79317fe7ddc5772908a90df245ee9c5dc08ba6a227f271', '2026-08-05 05:01:28'),
(235, 1, 55, 'A6,A7,A8', NULL, 3, 2715.00, 11223.00, 9675.00, 1548.00, 16.00, '2026-08-05 08:51:44', 'completed', 'movil', '{\"transaction_id\":\"TXN-20260805-9A0F3520\",\"method\":\"movil\",\"simulated\":true,\"reference\":\"CMP-20260805-0F37A8\",\"date\":\"2026-08-05 04:51:44\",\"ip\":\"172.22.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/151.0.0.0 Safari\\/537.36\"}', '6dc2f2b5c6806cf356e50ed17793614eea07f8160818086ccb7cd26c05b2913f', '2026-08-05 05:01:44');

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
(1, 229, 54, 1, 'A10', 3000.00, '2026-08-05 08:39:00'),
(2, 231, 55, 1, 'A1', 2000.00, '2026-08-05 08:42:32'),
(3, 231, 55, 1, 'A2', 2000.00, '2026-08-05 08:42:32'),
(4, 231, 55, 1, 'A3', 2000.00, '2026-08-05 08:42:32'),
(5, 233, 55, 1, 'A4', 2000.00, '2026-08-05 08:43:40'),
(6, 233, 55, 1, 'A5', 2000.00, '2026-08-05 08:43:40'),
(7, 235, 55, 1, 'A6', 2000.00, '2026-08-05 08:51:44'),
(8, 235, 55, 1, 'A7', 2000.00, '2026-08-05 08:51:44'),
(9, 235, 55, 1, 'A8', 2000.00, '2026-08-05 08:51:44');

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
(52, 46, 12, '2026-08-10', '14:20:00', 3000.00, 3000.00, 500.00, 1500.00, 1, 1, 1, 'lunes_mitad', 'subtitulos', 0, '2026-08-03 05:13:01'),
(54, 46, 14, '2026-08-10', '22:50:00', 3000.00, 3000.00, 0.00, 1500.00, 0, 1, 0, '', 'español', 0, '2026-08-03 08:53:59'),
(55, 18, 14, '2026-08-14', '14:35:00', 2000.00, 2000.00, 500.00, 1500.00, 1, 1, 0, '', 'español', 1, '2026-08-05 08:41:56');

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
(1, 'IVA', 16.00, 1, '2026-08-05 15:10:12');

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

--
-- Volcado de datos para la tabla `tickets`
--

INSERT INTO `tickets` (`id`, `user_id`, `showtime_id`, `seat_code`, `purchase_date`, `price_paid`) VALUES
(320, 5, 52, 'I21', '2026-08-04 15:34:10', 3000.00),
(321, 5, 52, 'H21', '2026-08-04 15:34:10', 3000.00),
(322, 5, 52, 'G21', '2026-08-04 15:36:12', 3000.00),
(324, 5, 52, 'I17', '2026-08-04 16:15:29', 3000.00),
(348, 1, 54, 'A1', '2026-08-05 06:33:41', 3000.00),
(349, 5, 54, 'A2', '2026-08-05 06:38:48', 3000.00),
(350, 5, 54, 'A3', '2026-08-05 06:38:48', 3000.00),
(354, 5, 54, 'A11', '2026-08-05 07:08:56', 3000.00),
(355, 5, 54, 'A12', '2026-08-05 07:08:56', 3000.00),
(356, 5, 54, 'A4', '2026-08-05 07:12:17', 3000.00),
(357, 5, 54, 'A5', '2026-08-05 07:12:17', 3000.00),
(360, 5, 52, 'A17', '2026-08-05 07:22:01', 3000.00),
(361, 5, 52, 'A18', '2026-08-05 07:22:01', 3000.00),
(362, 5, 52, 'A19', '2026-08-05 07:22:01', 3000.00),
(363, 5, 52, 'B17', '2026-08-05 07:27:39', 3000.00),
(364, 5, 52, 'B18', '2026-08-05 07:27:39', 3000.00),
(365, 5, 52, 'B19', '2026-08-05 07:27:39', 3000.00),
(369, 5, 52, 'C17', '2026-08-05 07:39:28', 3000.00),
(370, 5, 52, 'C18', '2026-08-05 07:39:28', 3000.00),
(371, 5, 52, 'C19', '2026-08-05 07:39:28', 3000.00),
(372, 5, 52, 'A20', '2026-08-05 07:44:08', 3000.00),
(373, 5, 52, 'B20', '2026-08-05 07:45:13', 3000.00),
(374, 5, 52, 'B21', '2026-08-05 07:45:13', 3000.00),
(375, 5, 52, 'A21', '2026-08-05 07:45:13', 3000.00),
(376, 5, 52, 'C20', '2026-08-05 07:47:40', 3000.00),
(377, 5, 52, 'C21', '2026-08-05 07:49:12', 3000.00),
(378, 5, 52, 'D21', '2026-08-05 07:49:12', 3000.00),
(389, 5, 52, 'D20', '2026-08-05 08:31:54', 3000.00),
(390, 5, 52, 'D19', '2026-08-05 08:31:54', 3000.00),
(391, 5, 52, 'D18', '2026-08-05 08:31:54', 3000.00);

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
(1, 'Administrador', 'admin@cinema.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$NOMst0oD6bh5Lrm8op6h8O5VIEaqXj70FjgMF7IeU9lAL0b4dwNPq', 'admin', 0, '2026-07-12 21:36:26', '2026-08-06 13:04:57'),
(5, 'Fulano Sutano', 'darwinmavares@gmail.com', 'V', '14511134', '414', '3601706', '1979-03-31', '$2y$10$7gM9QRKX7I4/HcKI0Q7TCuj0rpxRUPSk03f.MK9XhBCSEQtt2ru8G', 'user', 0, '2026-07-25 05:58:22', '2026-08-05 02:34:29');

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
  ADD KEY `food_item_id` (`food_item_id`),
  ADD KEY `idx_purchase_id` (`purchase_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `food_items`
--
ALTER TABLE `food_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `food_orders`
--
ALTER TABLE `food_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT de la tabla `purchase_tickets`
--
ALTER TABLE `purchase_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `showtimes`
--
ALTER TABLE `showtimes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=404;

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
