-- phpMyAdmin SQL Dump
-- version 3.5.3
-- http://www.phpmyadmin.net
--
-- Хост: 127.0.0.1:3306
-- Время создания: Дек 09 2013 г., 11:39
-- Версия сервера: 5.1.65-community-log
-- Версия PHP: 5.3.18

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `btcbot`
--

-- --------------------------------------------------------

--
-- Структура таблицы `btc`
--

CREATE TABLE IF NOT EXISTS `btc` (
  `id` int(11) NOT NULL,
  `count` decimal(10,6) NOT NULL,
  `price` decimal(10,6) NOT NULL,
  `summ` decimal(10,6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `exchange`
--

CREATE TABLE IF NOT EXISTS `exchange` (
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `buy` decimal(10,6) NOT NULL,
  `sell` decimal(10,6) NOT NULL,
  PRIMARY KEY (`dt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `status`
--

CREATE TABLE IF NOT EXISTS `status` (
  `balance_RUB` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `balance_BTC` decimal(10,6) NOT NULL DEFAULT '0.000000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
