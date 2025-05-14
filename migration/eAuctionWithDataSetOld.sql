-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 30, 2025 at 04:02 PM
-- Server version: 10.4.6-MariaDB
-- PHP Version: 8.3.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `auction28`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`blk`@`%` FUNCTION `LEVENSHTEIN2` (`s1` VARCHAR(255), `s2` VARCHAR(255)) RETURNS INT(11) DETERMINISTIC BEGIN

    DECLARE s1_len, s2_len, i, j, c, c_temp INT;

    DECLARE cost INT;

    DECLARE s1_char CHAR;

    DECLARE cv0, cv1 VARBINARY(256);

    

    SET s1_len = CHAR_LENGTH(s1);

    SET s2_len = CHAR_LENGTH(s2);

    IF s1_len = 0 THEN

        RETURN s2_len;

    END IF;

    IF s2_len = 0 THEN

        RETURN s1_len;

    END IF;

    

    SET cv1 = 0x00;

    SET j = 1;

    WHILE j <= s2_len DO

        SET cv1 = CONCAT(cv1, UNHEX(HEX(j)));

        SET j = j + 1;

    END WHILE;

    

    SET i = 1;

    WHILE i <= s1_len DO

        SET s1_char = SUBSTRING(s1, i, 1);

        SET c = i;

        SET cv0 = UNHEX(HEX(i));

        SET j = 1;

        WHILE j <= s2_len DO

            SET c_temp = c;

            SET c = CONV(HEX(SUBSTRING(cv1, j, 1)), 16, 10);

            IF s1_char = SUBSTRING(s2, j, 1) THEN

                SET cost = 0;

            ELSE

                SET cost = 1;

            END IF;

            SET cv0 = CONCAT(cv0, UNHEX(HEX(LEAST(c + 1, c_temp + 1, c + cost))));

            SET j = j + 1;

        END WHILE;

        SET cv1 = cv0;

        SET i = i + 1;

    END WHILE;

    RETURN CONV(HEX(SUBSTRING(cv1, s2_len, 1)), 16, 10);

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `auctions`
--

CREATE TABLE `auctions` (
  `auctionId` int(11) NOT NULL,
  `auctionTitle` varchar(200) NOT NULL,
  `auctionStartPrice` decimal(10,2) NOT NULL,
  `auctionStartDate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `auctionEndDate` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `auctionProductImg` varchar(300) NOT NULL,
  `auctionProductType` enum('organic','hybrid') NOT NULL,
  `auctionProductQuantity` decimal(10,4) NOT NULL,
  `auctionProductUnit` enum('kg','ton','nos') NOT NULL,
  `auctionAddress` varchar(300) NOT NULL,
  `auctionDescription` longtext NOT NULL,
  `auctionCategoryId` int(11) NOT NULL,
  `auctionCreatedBy` int(11) NOT NULL,
  `auctionStatus` enum('activate','deactivate','suspend') DEFAULT 'activate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `auctions`
--

INSERT INTO `auctions` (`auctionId`, `auctionTitle`, `auctionStartPrice`, `auctionStartDate`, `auctionEndDate`, `auctionProductImg`, `auctionProductType`, `auctionProductQuantity`, `auctionProductUnit`, `auctionAddress`, `auctionDescription`, `auctionCategoryId`, `auctionCreatedBy`, `auctionStatus`, `createdAt`) VALUES
(1, 'Whole Grain Bron Rice', 33000.00, '2024-12-21 02:04:00', '2024-12-21 02:04:00', 'prod_676d4ee69bd1d.webp', 'organic', 1.0000, 'ton', 'Pollachi, Comibatore,Tamil Nadu ', 'The brown is nerich in the vitamin, proteins and minerals ', 1, 1, 'activate', '2024-12-26 12:41:10'),
(2, 'Carrot', 24000.00, '2024-12-25 02:13:00', '2024-12-24 04:13:00', 'prod_676d4f8daa79b.webp', 'organic', 2.0000, 'ton', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', 'The High commissioner ', 3, 1, 'activate', '2024-12-26 12:43:57'),
(3, 'Morise Banna Bunchs', 1550.00, '2024-12-27 02:18:00', '2024-12-26 02:18:00', 'auction_67751d9f44f313.20307425.webp', 'organic', 70.0000, 'kg', 'Chennai , Tamil Nadu , india - 6400001', 'The High quality banana there ready in 2 days for harvest ', 3, 1, 'activate', '2024-12-26 12:50:20'),
(4, 'Halo Potato', 24000.00, '2024-12-19 03:09:00', '2024-12-31 03:10:00', 'prod_676d5cc30126d.webp', 'hybrid', 3.0000, 'ton', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', '6337', 3, 12, 'activate', '2024-12-26 13:40:19'),
(5, 'Banana stem', 9000.00, '2024-12-27 03:14:00', '2025-01-02 03:14:00', 'prod_676d5de39c33c.webp', 'hybrid', 250.0000, 'nos', 'Udumalipettai, Tiruppur,Tamil Nadu', 'The Banana stem is good for health ', 3, 2, 'activate', '2024-12-26 13:45:07'),
(6, 'Sunflower seeds', 45000.00, '2024-12-27 03:28:00', '2024-12-26 03:28:00', 'prod_676d6108e7694.webp', 'organic', 360.0000, 'kg', '1/283,somavarapatti', 'Sunflower seeds ', 4, 2, 'activate', '2024-12-26 13:58:32'),
(7, 'Musted Oli seeds', 20000.00, '2024-12-25 03:38:00', '2024-12-26 03:38:00', 'prod_676d638432d39.webp', 'hybrid', 2.0000, 'ton', 'Udumalipettai, Tiruppur,Tamil Nadu', 'Oli seeds', 4, 2, 'activate', '2024-12-26 14:09:08'),
(8, 'radish', 500.00, '2025-01-05 13:57:00', '2025-01-09 13:57:00', 'auction_677ea16404b682.95128271.webp', 'organic', 120.0000, 'kg', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', 'radish Radish ', 3, 1, 'activate', '2025-01-05 13:57:21'),
(9, 'Wheat', 4664.00, '2025-01-05 21:37:00', '2025-01-08 21:37:00', 'prod_677ab53d02ef4.webp', 'hybrid', 4564.0000, 'kg', 'Dbs', 'Gshs', 3, 1, 'activate', '2025-01-05 16:37:17'),
(10, 'Radishs', 5478.00, '2025-01-06 06:13:00', '2025-01-06 10:13:00', 'auction_677e9e288df205.57973118.webp', 'organic', 12.0000, 'kg', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', 'Tj', 3, 1, 'activate', '2025-01-06 06:14:53'),
(11, 'Carrot', 15000.00, '2025-01-12 03:18:00', '2025-01-15 03:18:00', 'prod_6783349836484.webp', 'organic', 140.0000, 'kg', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', 'Good for health ', 3, 1, 'activate', '2025-01-12 03:18:48'),
(12, 'Banana', 15800.00, '2025-01-12 03:27:00', '2025-01-18 03:27:00', 'prod_678336bf99098.webp', 'organic', 149.0000, 'nos', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', 'Banana ', 2, 1, 'activate', '2025-01-12 03:27:59'),
(13, 'Apple', 15000.00, '2025-01-30 15:16:00', '2025-02-02 15:16:00', 'prod_679b97d9cc467.webp', 'organic', 120.0000, 'kg', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', 'Hs', 2, 1, 'activate', '2025-01-30 15:16:41');

-- --------------------------------------------------------

--
-- Table structure for table `bids`
--

CREATE TABLE `bids` (
  `bidId` int(11) NOT NULL,
  `bidAuctionId` int(11) NOT NULL,
  `bidUserId` int(11) NOT NULL,
  `bidAmount` decimal(10,2) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `bids`
--

INSERT INTO `bids` (`bidId`, `bidAuctionId`, `bidUserId`, `bidAmount`, `createdAt`) VALUES
(1, 1, 2, 34000.00, '2024-12-26 12:51:07'),
(2, 1, 8, 36000.00, '2024-12-25 12:51:50'),
(3, 2, 8, 25500.00, '2024-12-26 12:52:07'),
(4, 1, 12, 37000.00, '2024-12-24 12:52:43'),
(5, 3, 12, 16000.00, '2024-12-26 13:00:24'),
(6, 1, 13, 39000.00, '2024-12-20 13:34:57'),
(7, 3, 13, 17500.00, '2024-12-25 13:35:08'),
(8, 1, 2, 40000.00, '2024-12-25 13:35:48'),
(9, 4, 13, 26000.00, '2024-12-19 13:41:37'),
(10, 2, 13, 27000.00, '2024-12-23 13:41:49'),
(11, 3, 14, 18500.00, '2024-12-24 13:42:12'),
(12, 4, 1, 27500.00, '2024-12-23 13:46:29'),
(13, 5, 1, 15000.00, '2024-12-25 13:46:46'),
(14, 5, 13, 15550.00, '2024-12-24 13:47:59'),
(15, 5, 12, 16000.00, '2024-12-20 13:48:31'),
(16, 1, 2, 50000.00, '2024-12-23 13:49:40'),
(17, 4, 1, 28000.00, '2024-12-20 13:51:19'),
(18, 6, 12, 47000.00, '2024-12-26 13:58:55'),
(19, 6, 14, 48000.00, '2024-12-23 13:59:13'),
(20, 6, 1, 49850.00, '2024-12-20 13:59:37'),
(21, 7, 1, 25000.00, '2024-12-24 14:09:34'),
(22, 7, 12, 27800.00, '2024-12-21 14:10:00'),
(23, 7, 1, 28000.00, '2024-12-26 14:10:32'),
(24, 1, 13, 39000.00, '2024-12-11 13:34:57'),
(25, 5, 1, 16002.00, '2024-12-27 02:00:22'),
(26, 11, 2, 15500.00, '2025-01-12 03:47:12');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `categoryId` int(11) NOT NULL,
  `categoryName` varchar(100) NOT NULL,
  `categoryImg` varchar(300) NOT NULL,
  `categoryStatus` enum('activate','deactivate','suspend') NOT NULL DEFAULT 'activate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`categoryId`, `categoryName`, `categoryImg`, `categoryStatus`, `createdAt`) VALUES
(1, 'Grains/Cereals', 'cat_676d4b18c7ed7.webp', 'activate', '2024-12-26 12:24:56'),
(2, 'Fruits', 'cat_676d4b722954c.webp', 'activate', '2024-12-26 12:26:26'),
(3, 'Vegetables', 'cat_676d4b9891e11.webp', 'activate', '2024-12-26 12:27:04'),
(4, 'Oilseeds', 'cat_676d4bdf031ca.webp', 'activate', '2024-12-26 12:28:15'),
(5, 'Pulses', 'cat_676d4c17a901f.webp', 'activate', '2024-12-26 12:29:11'),
(6, 'Spices', 'cat_676d4c63e3c1c.webp', 'activate', '2024-12-26 12:30:27');

-- --------------------------------------------------------

--
-- Table structure for table `heroes`
--

CREATE TABLE `heroes` (
  `heroId` int(11) NOT NULL,
  `heroTitle` varchar(100) NOT NULL,
  `heroImg` varchar(100) NOT NULL,
  `heroMessage` varchar(50) NOT NULL,
  `heroContent` longtext NOT NULL,
  `heroStatus` enum('activate','deactivate','suspend') NOT NULL DEFAULT 'deactivate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `heroes`
--

INSERT INTO `heroes` (`heroId`, `heroTitle`, `heroImg`, `heroMessage`, `heroContent`, `heroStatus`, `createdAt`) VALUES
(1, 'Welcome to eAuction78', 'hero_677522e35a761.webp', 'Join our online auction platform!', '<p>eAuction is an online platform where farmers and buyers can participate in live auctions of agricultural products. It ensures transparent bidding and fair market prices.</p>', 'suspend', '2024-12-29 14:01:33'),
(2, 'Agri Marketplace', 'img/2.jpg', 'Buy and sell agricultural products easily!', 'Our platform helps farmers and buyers connect easily for buying and selling agricultural products. It promotes fair trade and efficient distribution.', 'activate', '2024-12-29 14:01:33'),
(3, 'Vendor Partnership', 'img/3.jpg', 'Join our vendor network and grow your business!', 'Vendors can showcase their agricultural products to a wide audience through our eAgri Auction platform, connecting with farmers and buyers across the country.', 'activate', '2024-12-29 14:01:33'),
(4, 'The', 'hero_6772c798ccc51.webp', 'The', '<p style=\"padding-left: 40px;\">The</p>', 'activate', '2024-12-30 16:17:28'),
(5, 'Happy New Year 2025', 'hero_677536c45a89c.webp', 'This is to good year', '<p>&nbsp;</p>\r\n<div class=\"dots dot1\">&nbsp;</div>\r\n<div class=\"dots dot2\">&nbsp;</div>\r\n<div class=\"dots dot3\">&nbsp;</div>\r\n<div class=\"dots dot4\">&nbsp;</div>\r\n<div class=\"dots dot5\">&nbsp;</div>\r\n<div class=\"dots dot6\">&nbsp;</div>\r\n<div class=\"dots dot7\">Hello</div>\r\n<div class=\"dots dot8\">&nbsp;</div>\r\n<div class=\"dots dot9\">&nbsp;</div>\r\n<div class=\"dots dot10\">&nbsp;</div>', 'activate', '2025-01-01 11:16:20'),
(6, 'Tes', 'hero_67752ae152a12.webp', 'Hell', '<p>Eje</p>\r\n<p><strong>hi</strong></p>', 'activate', '2025-01-01 11:45:37');

-- --------------------------------------------------------

--
-- Table structure for table `interests`
--

CREATE TABLE `interests` (
  `interestId` int(11) NOT NULL,
  `interestUserId` int(11) NOT NULL,
  `interestCategoryId` int(11) NOT NULL,
  `interestProductType` enum('organic','hybrid','both') DEFAULT NULL,
  `interestKeywords` varchar(255) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `interests`
--

INSERT INTO `interests` (`interestId`, `interestUserId`, `interestCategoryId`, `interestProductType`, `interestKeywords`, `createdAt`) VALUES
(1, 1, 3, 'organic', 'Carrot', '2025-01-28 14:57:48'),
(2, 1, 4, 'organic', 'Carrot', '2025-01-28 14:57:48'),
(3, 2, 2, 'both', 'banana ', '2025-01-30 02:34:04'),
(4, 2, 4, 'both', 'banana ', '2025-01-30 02:34:04'),
(5, 1, 2, 'both', 'grapefruit', '2025-01-30 15:48:43'),
(6, 1, 4, 'both', 'grapefruit', '2025-01-30 15:48:43');

-- --------------------------------------------------------

--
-- Table structure for table `moments`
--

CREATE TABLE `moments` (
  `momentId` int(11) NOT NULL,
  `momentUserId` int(11) NOT NULL,
  `momentImg` varchar(200) NOT NULL,
  `momentStatus` enum('activate','deactivate','suspend') NOT NULL DEFAULT 'deactivate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `moments`
--

INSERT INTO `moments` (`momentId`, `momentUserId`, `momentImg`, `momentStatus`, `createdAt`) VALUES
(1, 1, 'moment_677bd5d01dfed.jpg', 'deactivate', '2025-01-06 13:08:32'),
(2, 1, 'moment_677bd5d67b852.jpg', 'deactivate', '2025-01-06 13:08:38'),
(3, 1, 'moment_677bd5ddea4a2.jpg', 'activate', '2025-01-06 13:08:45'),
(4, 1, 'moment_677bd5e44fe55.jpg', 'activate', '2025-01-06 13:08:52'),
(5, 1, 'moment_677bd5eb7a08e.jpg', 'activate', '2025-01-06 13:08:59'),
(6, 1, 'moment_677bd5f242b65.jpg', 'activate', '2025-01-06 13:09:06'),
(7, 1, 'moment_677bd5fb35a03.jpg', 'activate', '2025-01-06 13:09:15'),
(8, 1, 'moment_677bd6017c383.jpg', 'activate', '2025-01-06 13:09:21'),
(9, 1, 'moment_677bd6081c7f7.jpg', 'activate', '2025-01-06 13:09:28'),
(10, 1, 'moment_677bd6102ed0d.jpg', 'activate', '2025-01-06 13:09:36'),
(11, 1, 'moment_677bd61781928.jpg', 'activate', '2025-01-06 13:09:43'),
(12, 1, 'moment_677bd61d2472f.jpg', 'activate', '2025-01-06 13:09:49');

-- --------------------------------------------------------

--
-- Table structure for table `passResets`
--

CREATE TABLE `passResets` (
  `passResetId` int(11) NOT NULL,
  `passResetUserId` int(11) NOT NULL,
  `passResetToken` varchar(64) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `reviewId` int(11) NOT NULL,
  `reviewUserId` int(11) NOT NULL,
  `reviewMessage` mediumtext NOT NULL,
  `reviewStatus` enum('activate','deactivate','suspend') NOT NULL DEFAULT 'activate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`reviewId`, `reviewUserId`, `reviewMessage`, `reviewStatus`, `createdAt`) VALUES
(1, 1, 'This platform is excellent for connecting farmers directly with buyers. Keep up the good work!', 'activate', '2024-12-29 10:48:51'),
(2, 2, 'I appreciate the efforts to simplify agricultural auctions, but adding more product categories would be helpful.', 'activate', '2024-12-29 10:48:51'),
(3, 3, 'Amazing user interface! It’s easy to navigate and understand.', 'suspend', '2024-12-29 10:48:51'),
(4, 4, 'The bidding system is great, but there should be an option to set reminders for auction deadlines.', 'activate', '2024-12-29 10:48:51'),
(6, 1, 'The', 'deactivate', '2025-01-06 08:37:12'),
(7, 1, 'Hello', 'activate', '2025-01-06 08:40:30'),
(8, 1, 'Hello', 'deactivate', '2025-01-06 08:40:32'),
(9, 1, 'Hello', 'deactivate', '2025-01-06 09:06:17'),
(10, 1, 'He', 'deactivate', '2025-01-06 09:07:14'),
(11, 1, 'Ej', 'deactivate', '2025-01-30 15:49:23'),
(12, 1, 'Sj', 'deactivate', '2025-01-30 15:52:15'),
(13, 1, 'Jo', 'deactivate', '2025-01-30 15:52:32'),
(14, 1, 'Dj', 'deactivate', '2025-01-30 15:57:04'),
(15, 1, 'Sn', 'deactivate', '2025-01-30 15:57:39'),
(16, 1, '3D', 'deactivate', '2025-01-30 15:58:08'),
(17, 1, 'Eh', 'deactivate', '2025-01-30 15:59:22'),
(18, 1, '83', 'deactivate', '2025-01-30 16:00:30'),
(19, 1, 'Eh', 'deactivate', '2025-01-30 16:01:34');

-- --------------------------------------------------------

--
-- Table structure for table `trans`
--

CREATE TABLE `trans` (
  `transId` int(11) NOT NULL,
  `transTrackingId` varchar(30) NOT NULL,
  `transCardNo` varchar(30) NOT NULL,
  `transAccountNo` varchar(30) NOT NULL,
  `transUserId` int(11) NOT NULL,
  `transAmount` decimal(10,2) NOT NULL,
  `transAuctionId` int(11) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `trans`
--

INSERT INTO `trans` (`transId`, `transTrackingId`, `transCardNo`, `transAccountNo`, `transUserId`, `transAmount`, `transAuctionId`, `createdAt`) VALUES
(1, 'txn_676d68b350bb66.18771088', '987774567890123', '11130100007375', 1, 48850.00, 6, '2024-12-26 14:31:15'),
(2, 'txn_676e0b1bced9d7.43560180', '1234567894561', '11130100007375', 1, 28000.00, 7, '2024-12-27 02:04:11'),
(3, 'txn_677515b9d5c351.00985673', '9876543210273', '11130100007375', 1, 49850.00, 6, '2025-01-01 10:15:21'),
(4, 'txn_677ba1ef37d263.75877239', '123456272827828', '36738383847885', 1, 28000.00, 4, '2025-01-06 09:27:11');

-- --------------------------------------------------------

--
-- Table structure for table `userActivate`
--

CREATE TABLE `userActivate` (
  `userActivateId` int(11) NOT NULL,
  `userActivateUserId` int(11) NOT NULL,
  `userActivateToken` varchar(64) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `userActivate`
--

INSERT INTO `userActivate` (`userActivateId`, `userActivateUserId`, `userActivateToken`, `createdAt`) VALUES
(5, 53, 'EXPIRED', '2025-01-08 15:24:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userId` int(11) NOT NULL,
  `userName` varchar(100) NOT NULL,
  `userFirstName` varchar(100) DEFAULT NULL,
  `userLastName` varchar(100) DEFAULT NULL,
  `userEmail` varchar(100) NOT NULL,
  `userPassword` varchar(255) NOT NULL,
  `userPhone` varchar(100) DEFAULT NULL,
  `userAddress` varchar(250) DEFAULT NULL,
  `userProfileImg` varchar(300) DEFAULT 'profile.webp',
  `userAccountNo` varchar(30) DEFAULT NULL,
  `userRole` enum('user','admin') NOT NULL DEFAULT 'user',
  `userStatus` enum('activate','deactivate','suspend') DEFAULT 'deactivate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userId`, `userName`, `userFirstName`, `userLastName`, `userEmail`, `userPassword`, `userPhone`, `userAddress`, `userProfileImg`, `userAccountNo`, `userRole`, `userStatus`, `createdAt`) VALUES
(1, 'blk', 'Nishanth', 'Pechimuthu', 'nishanthpechimuthu@gmail.com', '$2y$10$HWW4lymluIu7nK.4RyprBumuf7b6i8MeI5pM4OBJe5F94FQm53Fye', '+91 8015864344', 'Udumalipettai, Tiruppur,Tamil Nadu', 'img_674561fd68014.webp', '11130100005354', 'admin', 'activate', '2024-12-11 08:46:29'),
(2, 'root', 'Nishanth', 'Root', 'nishanthpechimuthu@outlook.com', '$2y$10$hdJIVulgWHZj.RL7UyCqm.yigqyBsZP6Qkk3YceNQ43/xCloDWffC', '+91 9500814344', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', 'img_674b2bcaafe57.webp', '11130100007375', 'user', 'activate', '2024-11-23 05:15:40'),
(3, 'black', NULL, NULL, 'EXP:nishanthpechimuthu@gmail.com', '$2y$10$QiirJV1S8KSjbT59BPI1geJRP/mcyoxya0X2KXndcXbVJAlVVF8ga', 'NULL', 'NULL', 'profile.webp', NULL, 'user', 'suspend', '2024-10-16 07:54:13'),
(4, '22ct19', NULL, NULL, '22ct19nishanth@gmail.com', '$2y$10$izGcmfVhW29bJYIjNnOAOutb8FcEiwciE1NLdRDvoBos1F3jHEMtO', 'NULL', 'NULL', 'profile.webp', NULL, 'user', 'activate', '2024-11-30 09:48:24'),
(8, 'yellow', 'Yellow ', 'Orange ', 'yellow@gmail.com', '$2y$10$YHXU9AbUcpNYjfV9tawDtOqnLgUIsqH75T6s4FWSAvKKYFQPzYYFG', '+91 8015864344', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', 'profile.webp', '1234567890', 'user', 'activate', '2024-10-16 15:15:50'),
(9, 'gray', NULL, NULL, 'EXP:EXP:gray@gmail.com', '$2y$10$naTRT6QM0npWIQudxP8wXe0GbYKEJyiWUDJG82f3/6qCDBHit.ytW', NULL, NULL, 'profile.webp', NULL, 'user', 'suspend', '2024-09-11 07:32:34'),
(11, 'gray2', NULL, NULL, 'gray@gmail.com', '$2y$10$90srGWeR80x9YJc8fct0TuBAGG2xuKiQigr3o03wnBphlA3FLuAT2', NULL, NULL, 'profile.webp', NULL, 'user', 'deactivate', '2024-12-07 11:35:36'),
(12, '1', 'The Last', 'Dragon ', '1@gmail.com', '$2y$10$EkDIISfH4hF4J1B0WcM8oewRhBhFN5hvp/mmNKy.ORvX0zmdXMRNe', '+91 7200524344', 'Udumalipettai, Tiruppur,Tamil Nadu,Indian-642205', 'profile.webp', '36738383847885', 'user', 'activate', '2024-12-09 10:41:35'),
(13, '2', NULL, NULL, '2@gmail.com', '$2y$10$C67Zjmuqe9b40.Oqh8qmheoep.ICJsai91CPkH8Z3ijRS7nMFlL8q', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-09-17 10:41:53'),
(14, '3', NULL, NULL, '3@gmail.com', '$2y$10$KgfkSYJlVbM4vGoS0fIIe.ACmWp0q6cL6bYqv6yUMqW.qx8RSgQbm', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-11-13 10:43:06'),
(15, '4', NULL, NULL, '4@gmail.com', '$2y$10$nXVyBwakWyyOMpB5BEPNPe7/LkuJi/DPeNnAPZPemIS6HRpb3hsIq', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-12-09 10:43:51'),
(16, '5', NULL, NULL, '5@gmail.com', '$2y$10$GNX/MytCLguewkaJ77t8sOYXdcxpwPqpL8UvUzS/BQbvcylPG7KLq', NULL, NULL, 'profile.webp', NULL, 'user', 'deactivate', '2024-12-09 10:46:08'),
(17, '6', NULL, NULL, '6@gmail.com', '$2y$10$9cRrSGIRd4UnkGzOBGxJ0eim1Isl7A9HOgfG1SyAQ/lShrGw5B.6a', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-10-15 10:46:25'),
(18, '7', NULL, NULL, '7@gmail.com', '$2y$10$M8mTpVq5latigiP7fb9N9Oo4b5h3qkTsuzxMlWmOms7crF4Uk1V8G', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-11-09 10:47:45'),
(19, 'john_doe', NULL, NULL, 'john_doe@example.com', '$2y$10$Qj4rWjPZJqER5sA1vQ8WbJQdxpsXE1y7h6taKp9djU3hY7pblpHS6', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-04-12 08:52:18'),
(20, 'alice_smith', NULL, NULL, 'alice_smith@example.com', '$2y$10$yL91Pbxd3XzgyVRQnJMjMErIVobggQ1x.Nlj5ZX9WvHIm58gQyCq2', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-05-03 04:00:42'),
(21, 'bob_johnson', NULL, NULL, 'bob_johnson@example.com', '$2y$10$OHwT1Q7Wi8zPbls5Gf2H6.fYnbV0BIC6yO0YoGXY3b45yRtozmUeG', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-06-25 06:15:56'),
(22, 'carol_williams', NULL, NULL, 'carol_williams@example.com', '$2y$10$9.Zj4f0X9bETK9Dwp7jm5aePxwMzYBmdzt.mWQT9uwrdJclpYpRmG', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-03-18 11:40:23'),
(23, 'david_brown', NULL, NULL, 'david_brown@example.com', '$2y$10$ghuVpwFZBy2mvhtAfgh0aeHo1wTrHNjf6T.S6iEDN28T5tqdoHGii', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-02-14 06:30:01'),
(24, 'emma_davis', NULL, NULL, 'emma_davis@example.com', '$2y$10$6hUJ0vld8cmR6AWYn44O8ux5e5MYIbMPkIuZKvcQ.Owph5TBiNU4K', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-04-10 10:50:37'),
(25, 'fred_miller', NULL, NULL, 'fred_miller@example.com', '$2y$10$Y47AZyXTj5.EyIQQ2uz5GqosRQW30uVjzCOxMlAPXeZfTq5oVdyAm', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-05-29 01:48:50'),
(26, 'grace_garcia', NULL, NULL, 'grace_garcia@example.com', '$2y$10$hsMKiaQw0MVs37NSsRry/ft7Uu5ECv3ZphZ93rU3nH6XTzp6u/Ju2', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-07-02 08:25:41'),
(27, 'hank_martinez', NULL, NULL, 'hank_martinez@example.com', '$2y$10$wDQOZg9od9AQzGs8tM5GfFjtWbwRPWfBcBdFwQ2MiBzm7UG0uAoTi', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-01-19 02:35:02'),
(28, 'irene_hernandez', NULL, NULL, 'irene_hernandez@example.com', '$2y$10$Wr6k3kaQHo.Bs8Q0h7d6xuA3q24t5BCf.Z7d0J49tk2kcs.AxC6b2', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-06-14 10:02:10'),
(29, 'jack_moore', NULL, NULL, 'jack_moore@example.com', '$2y$10$OTsmk0r0XzTY33s2fw/eU24eLRllhwA06Jg3ALQDb3HJXOUhXFEa6', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-08-09 04:44:47'),
(30, 'kate_jackson', NULL, NULL, 'kate_jackson@example.com', '$2y$10$CTTY0kXr1K8uhz0fYrFq5LBYlzN52zFoyRh5A7hBzQQMkFsqCO6t.', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-04-30 11:24:55'),
(31, 'leo_thomas', NULL, NULL, 'leo_thomas@example.com', '$2y$10$L5JqBB2IGKiL7rT1RaW.V3Wld7HgAP4nMK7fzAwMLHn8VhbTZSt8W', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-02-06 12:32:16'),
(32, 'mia_white', NULL, NULL, 'mia_white@example.com', '$2y$10$5vvZIQiJ1Ybz.kdYh5HnYenPzx06Y3WmrQwFPgFUOeqj4pLnbgLwG', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-07-22 07:14:03'),
(33, 'nina_smith', NULL, NULL, 'nina_smith@example.com', '$2y$10$Jwfh3mQH3lMmw96fRA.XnH2r1LzWeioQhDsbgR8LIM.LtkZt6JlbW', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-06-05 09:05:01'),
(34, 'oscar_davis', NULL, NULL, 'oscar_davis@example.com', '$2y$10$2smE3JHcUmsLUwD5Rp4zwPSROTDJgqaTYWsdbI2tB7u0xBzYwX2u6', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-05-19 04:32:47'),
(35, 'paul_jones', NULL, NULL, 'paul_jones@example.com', '$2y$10$AMQs5yAKQ25G8fpRVHR4hqsFb7Xuhhgw0EkQ2MwDNBwxAlblpCtvG', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-03-25 05:48:56'),
(36, 'quinn_clark', NULL, NULL, 'quinn_clark@example.com', '$2y$10$ZToh9eAB0IYblq7Di43DbXG9u9UxnMZT7gt2FPURZkCqymuoV9a1u', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-04-02 13:57:05'),
(37, 'rachel_lee', NULL, NULL, 'rachel_lee@example.com', '$2y$10$gM2a2eHD5A5Ta6wzR9FOSyDaedkTPntRPqTI0QJ34uO9KhV5Qf/rm', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-07-14 08:26:21'),
(38, 'samuel_wilson', NULL, NULL, 'samuel_wilson@example.com', '$2y$10$kEehmmhxRs.GVpXFEqtFfPOz9neFdp6.wRjlUQXtZz9BeCpHspmdW', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-02-23 14:41:36'),
(39, 'tina_moore', NULL, NULL, 'tina_moore@example.com', '$2y$10$FM5jj1VfS54L4HJvLCZtHuMe3ObzZq2m5JhM2MIHGGShYdBFCeT4q', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-06-20 04:18:08'),
(40, 'dg', NULL, NULL, 'ct@dg.com', '$2$5ghhggghghddrhu6k', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2024-05-23 06:50:12'),
(53, 'optamizhan', NULL, NULL, 'gamingtamizhan28@gmail.com', '$2y$10$txKv3SLxBjVFWE.vXgvLNuFy/yltK2ktv61zWEm/LH7Jm6RMrMt5K', NULL, NULL, 'profile.webp', NULL, 'user', 'activate', '2025-01-08 15:24:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auctions`
--
ALTER TABLE `auctions`
  ADD PRIMARY KEY (`auctionId`),
  ADD KEY `auctionCategoryId` (`auctionCategoryId`),
  ADD KEY `auctionCreatedBy` (`auctionCreatedBy`);

--
-- Indexes for table `bids`
--
ALTER TABLE `bids`
  ADD PRIMARY KEY (`bidId`),
  ADD KEY `bidAuctionId` (`bidAuctionId`),
  ADD KEY `bidUserId` (`bidUserId`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`categoryId`),
  ADD UNIQUE KEY `categoryName` (`categoryName`);

--
-- Indexes for table `heroes`
--
ALTER TABLE `heroes`
  ADD PRIMARY KEY (`heroId`);

--
-- Indexes for table `interests`
--
ALTER TABLE `interests`
  ADD PRIMARY KEY (`interestId`),
  ADD KEY `interests_interestUserId_users_userId` (`interestUserId`),
  ADD KEY `interests_interestCategoryId_categories_categoryId` (`interestCategoryId`);

--
-- Indexes for table `moments`
--
ALTER TABLE `moments`
  ADD PRIMARY KEY (`momentId`),
  ADD KEY `moments_momentUserId_users_userId` (`momentUserId`);

--
-- Indexes for table `passResets`
--
ALTER TABLE `passResets`
  ADD PRIMARY KEY (`passResetId`),
  ADD KEY `passResets_passRestUserId_users_userId` (`passResetUserId`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`reviewId`),
  ADD KEY `reviews_reviewUserId_users_userId` (`reviewUserId`);

--
-- Indexes for table `trans`
--
ALTER TABLE `trans`
  ADD PRIMARY KEY (`transId`),
  ADD KEY `trans_transAccountNo_users_userAccountNo` (`transAccountNo`),
  ADD KEY `trans_transUserId_users_userId` (`transUserId`),
  ADD KEY `trans_transAuctionId_auctions_auctionId` (`transAuctionId`);

--
-- Indexes for table `userActivate`
--
ALTER TABLE `userActivate`
  ADD PRIMARY KEY (`userActivateId`),
  ADD KEY `userActivate_userActivateUserId_users_userId` (`userActivateUserId`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userId`),
  ADD UNIQUE KEY `userEmail` (`userEmail`),
  ADD UNIQUE KEY `userName` (`userName`),
  ADD UNIQUE KEY `userAccountNo` (`userAccountNo`),
  ADD UNIQUE KEY `userAccountNo_2` (`userAccountNo`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auctions`
--
ALTER TABLE `auctions`
  MODIFY `auctionId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `bids`
--
ALTER TABLE `bids`
  MODIFY `bidId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `categoryId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `heroes`
--
ALTER TABLE `heroes`
  MODIFY `heroId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `interests`
--
ALTER TABLE `interests`
  MODIFY `interestId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `moments`
--
ALTER TABLE `moments`
  MODIFY `momentId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `passResets`
--
ALTER TABLE `passResets`
  MODIFY `passResetId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `reviewId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `trans`
--
ALTER TABLE `trans`
  MODIFY `transId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `userActivate`
--
ALTER TABLE `userActivate`
  MODIFY `userActivateId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auctions`
--
ALTER TABLE `auctions`
  ADD CONSTRAINT `auctions_ibfk_1` FOREIGN KEY (`auctionCategoryId`) REFERENCES `categories` (`categoryId`),
  ADD CONSTRAINT `auctions_ibfk_2` FOREIGN KEY (`auctionCreatedBy`) REFERENCES `users` (`userId`);

--
-- Constraints for table `bids`
--
ALTER TABLE `bids`
  ADD CONSTRAINT `bids_ibfk_1` FOREIGN KEY (`bidAuctionId`) REFERENCES `auctions` (`auctionId`),
  ADD CONSTRAINT `bids_ibfk_2` FOREIGN KEY (`bidUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `interests`
--
ALTER TABLE `interests`
  ADD CONSTRAINT `interests_interestCategoryId_categories_categoryId` FOREIGN KEY (`interestCategoryId`) REFERENCES `categories` (`categoryId`),
  ADD CONSTRAINT `interests_interestUserId_users_userId` FOREIGN KEY (`interestUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `moments`
--
ALTER TABLE `moments`
  ADD CONSTRAINT `moments_momentUserId_users_userId` FOREIGN KEY (`momentUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `passResets`
--
ALTER TABLE `passResets`
  ADD CONSTRAINT `passResets_passRestUserId_users_userId` FOREIGN KEY (`passResetUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_reviewUserId_users_userId` FOREIGN KEY (`reviewUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `trans`
--
ALTER TABLE `trans`
  ADD CONSTRAINT `trans_transAccountNo_users_userAccountNo` FOREIGN KEY (`transAccountNo`) REFERENCES `users` (`userAccountNo`),
  ADD CONSTRAINT `trans_transAuctionId_auctions_auctionId` FOREIGN KEY (`transAuctionId`) REFERENCES `auctions` (`auctionId`),
  ADD CONSTRAINT `trans_transUserId_users_userId` FOREIGN KEY (`transUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `userActivate`
--
ALTER TABLE `userActivate`
  ADD CONSTRAINT `userActivate_userActivateUserId_users_userId` FOREIGN KEY (`userActivateUserId`) REFERENCES `users` (`userId`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
