-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 12, 2025 at 08:17 PM
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
-- Database: `remkonstore`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `store_id` int(11) DEFAULT 1,
  `role` enum('admin','cashier') NOT NULL DEFAULT 'cashier',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `store_id`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$RwSQBySgxNa2/cb2qRex0e5n0tUyz0zWIXWLZLAumYh5wOygTE48K', 'Administrator', '', 1, 'admin', '2025-08-02 23:42:08'),
(2, 'cashier', '$2y$10$UVtubvD0w9Ftd9EUWl0hjeIXMNf4biuPf/43ZCb5rqHFIXmBvZgzm', 'Cashier User', '', 1, 'cashier', '2025-08-02 23:42:08'),
(3, 'Olansgee', '$2y$10$Ci2UqkYQIjbaq294vM/I/OM5S8z70h9Dgq5UoKDTwwzW/a4mYN45q', 'Soneye G. Olanrewaju', 'olansgee@gmail.com', 2, 'cashier', '2025-08-05 13:41:54'),
(8, 'Olansgee1', '$2y$10$Hq9NDGygfjV2AbIOXgEd3em.4461TYRyOk1/ZMWCug3pnTHo53ahy', 'Soneye Adekunle', 'olansgee@yahoo.com', 1, 'cashier', '2025-08-05 13:47:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
