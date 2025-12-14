-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 13, 2025 at 01:23 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tour_guide_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `guide_id` int(11) NOT NULL,
  `rate_type` enum('day','hour') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `booking_date` date NOT NULL,
  `status` enum('pending','approved','declined','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `guide_id`, `rate_type`, `start_date`, `end_date`, `start_time`, `end_time`, `booking_date`, `status`, `created_at`) VALUES
(9, 10, 1, 'day', '2025-12-13', '2025-12-13', NULL, NULL, '2025-12-13', 'declined', '2025-12-12 11:38:44'),
(11, 10, 1, 'day', '2025-12-13', '2025-12-13', NULL, NULL, '2025-12-13', 'approved', '2025-12-12 13:12:09'),
(12, 10, 1, 'hour', '2025-12-14', '2025-12-14', '05:00:00', '12:00:00', '2025-12-14', 'approved', '2025-12-13 09:15:51'),
(13, 10, 1, 'day', '2025-12-15', '2025-12-15', NULL, NULL, '2025-12-15', 'declined', '2025-12-13 09:22:30'),
(14, 10, 1, 'day', '2025-12-15', '2025-12-15', NULL, NULL, '2025-12-15', 'declined', '2025-12-13 09:36:48');

-- --------------------------------------------------------

--
-- Table structure for table `guides`
--

CREATE TABLE `guides` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `languages` varchar(255) DEFAULT NULL,
  `accommodation` text DEFAULT NULL,
  `rate_day` decimal(10,2) DEFAULT 0.00,
  `rate_hour` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guides`
--

INSERT INTO `guides` (`id`, `user_id`, `contact`, `location`, `languages`, `accommodation`, `rate_day`, `rate_hour`) VALUES
(1, 10, '09123456789', 'Pagadian', 'English, Filipino, Bisaya', 'I specialize in coordinating comfortable and authentic accommodations, ranging from luxury hotels to charming local inns, all vetted for quality and safety.', 1000.00, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `guide_availability`
--

CREATE TABLE `guide_availability` (
  `id` int(11) NOT NULL,
  `guide_id` int(11) NOT NULL,
  `available_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guide_availability`
--

INSERT INTO `guide_availability` (`id`, `guide_id`, `available_date`) VALUES
(55, 1, '2025-12-16'),
(57, 1, '2025-12-15');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_role` enum('user','guide','admin') DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `user_role`, `type`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(36, 10, 'guide', 'new_booking', 'New booking request from Hezekiah Sarita for 2025-12-14', NULL, 1, '2025-12-13 09:15:51'),
(37, 10, 'user', 'booking_confirmed', 'Your booking with Hezekiah Sarita for 2025-12-14 has been confirmed!', NULL, 0, '2025-12-13 09:16:20'),
(38, 10, 'guide', 'new_booking', 'New booking request from Hezekiah Sarita for 2025-12-15', NULL, 1, '2025-12-13 09:22:30'),
(39, 10, 'user', 'booking_declined', 'Your booking with Hezekiah Sarita for 2025-12-15 has been declined.', NULL, 0, '2025-12-13 09:26:58'),
(40, 10, 'guide', 'new_booking', 'New booking request from Hezekiah Sarita for 2025-12-15', NULL, 1, '2025-12-13 09:36:48'),
(41, 10, 'user', 'booking_declined', 'Your booking with Hezekiah Sarita for 2025-12-15 has been declined.', NULL, 0, '2025-12-13 09:56:24');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','guide','admin') NOT NULL DEFAULT 'user',
  `is_tourist_mode` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verification_code` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `is_tourist_mode`, `created_at`, `verification_code`, `is_verified`, `reset_token`, `reset_token_expiry`) VALUES
(10, 'Hezekiah Sarita', 'hezekiahsarita@gmail.com', '$2y$10$5cWwvNpFpZEEC/4KHRxmLuzIQ19XmlPNEweuqfPASMTEMpPFKIUXS', 'guide', 0, '2025-11-24 16:57:19', NULL, 1, '154828', '2025-12-13 09:37:32'),
(12, 'Krizzle Delavin', 'queenhezekiah04@gmail.com', '$2y$10$AW0thknW6RKj.uvz6FKEFu67IQr9cJuCgeS1zeE38y8Tf2SdCN4iC', 'user', 0, '2025-12-12 13:44:48', NULL, 1, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `guide_id` (`guide_id`);

--
-- Indexes for table `guides`
--
ALTER TABLE `guides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `guide_availability`
--
ALTER TABLE `guide_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guide_id` (`guide_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `guides`
--
ALTER TABLE `guides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `guide_availability`
--
ALTER TABLE `guide_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`guide_id`) REFERENCES `guides` (`id`);

--
-- Constraints for table `guides`
--
ALTER TABLE `guides`
  ADD CONSTRAINT `guides_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `guide_availability`
--
ALTER TABLE `guide_availability`
  ADD CONSTRAINT `guide_availability_ibfk_1` FOREIGN KEY (`guide_id`) REFERENCES `guides` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
