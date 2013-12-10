-- phpMyAdmin SQL Dump
-- version 3.5.3
-- http://www.phpmyadmin.net
--
-- Хост: 127.0.0.1:3306
-- Время создания: Дек 10 2013 г., 10:11
-- Версия сервера: 5.1.65-community-log
-- Версия PHP: 5.3.18

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- База данных: `btcbot`
--

-- --------------------------------------------------------

--
-- Структура таблицы `btc`
--

DROP TABLE IF EXISTS `btc`;
CREATE TABLE IF NOT EXISTS `btc` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `count` decimal(10,6) NOT NULL,
  `price` decimal(30,6) NOT NULL,
  `summ` decimal(30,6) NOT NULL,
  `dtm` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=33 ;

--
-- Дамп данных таблицы `btc`
--

INSERT INTO `btc` (`id`, `count`, `price`, `summ`, `dtm`) VALUES
(26, '0.003300', '30421.309990', '100.591104', '2013-12-08 21:39:02'),
(27, '0.000300', '29489.900000', '8.864664', '2013-12-09 00:15:02'),
(28, '0.000300', '29719.580000', '8.933706', '2013-12-09 00:36:02'),
(29, '0.000200', '29875.990000', '5.987148', '2013-12-09 05:18:01'),
(30, '0.000200', '30400.000000', '6.092160', '2013-12-09 07:09:02'),
(31, '0.000200', '30788.490000', '6.170013', '2013-12-09 08:09:01'),
(32, '0.000200', '31239.960000', '6.260488', '2013-12-09 20:50:54');

-- --------------------------------------------------------

--
-- Структура таблицы `sell`
--

DROP TABLE IF EXISTS `sell`;
CREATE TABLE IF NOT EXISTS `sell` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `btc_id` int(11) NOT NULL,
  `price` decimal(30,6) NOT NULL,
  `count` decimal(30,6) NOT NULL,
  `summ` decimal(30,6) NOT NULL,
  `income` decimal(30,6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `status`
--

DROP TABLE IF EXISTS `status`;
CREATE TABLE IF NOT EXISTS `status` (
  `param` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `value` varchar(150) NOT NULL,
  PRIMARY KEY (`param`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `status`
--

INSERT INTO `status` (`param`, `title`, `value`) VALUES
('balance', 'Баланс в рублях', '57.1007169231'),
('last_visit', 'Последняя проверка статуса', '2013-01-01 00:00:00');
