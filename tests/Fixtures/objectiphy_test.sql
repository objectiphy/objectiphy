SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `anomalies`
-- ----------------------------
DROP TABLE IF EXISTS `anomalies`;
CREATE TABLE `anomalies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `event_date_time` datetime DEFAULT NULL,
  `veryInconsistent_naming_Conventionhere` varchar(100) DEFAULT NULL,
  `address_line1` varchar(100) DEFAULT NULL,
  `address_line2` varchar(100) DEFAULT NULL,
  `address_town` varchar(100) DEFAULT NULL,
  `address_postcode` varchar(20) DEFAULT NULL,
  `address_country_code` varchar(2) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `anomalies`
-- ----------------------------

INSERT INTO `anomalies` VALUES
  ('1', 'Dave', 'Lister', '1988-02-15 21:00:00', 'The End', '13 Nowhere Close', 'Smuttleton', 'Frogsborough', 'FR1 1OG', 'GB', 1),
  ('2', 'Arnold', 'Rimmer', '2017-11-16 21:00:00', 'Skipper', '14 Somewhere Close', 'Frogleton', 'Smuttlesborough', 'SM1 1UT', 'GB', 2);

-- ----------------------------
--  Table structure for `assumed_pk`
-- ----------------------------
DROP TABLE IF EXISTS `assumed_pk`;
CREATE TABLE `assumed_pk` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `assumed_pk`
-- ----------------------------

INSERT INTO `assumed_pk` VALUES ('0', 'Matt Bellamy'), ('1', 'Gary Lightbody'), ('2', 'Paloma Faith');

-- ----------------------------
--  Table structure for `child`
-- ----------------------------
DROP TABLE IF EXISTS `child`;
CREATE TABLE `child` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `height_in_cm` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `child_address_line1` varchar(100) DEFAULT NULL,
  `child_address_line2` varchar(100) DEFAULT NULL,
  `child_address_town` varchar(100) DEFAULT NULL,
  `child_address_postcode` varchar(20) DEFAULT NULL,
  `child_address_country_code` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id_2` (`parent_id`),
  KEY `user_id_2` (`user_id`)/*,
  CONSTRAINT `child_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) /*ON DELETE SET NULL,
  /*CONSTRAINT `parent` FOREIGN KEY (`parent_id`) REFERENCES `parent` (`id`) /*ON DELETE SET NULL*/
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `child`
-- ----------------------------

INSERT INTO `child` VALUES
('1', '2', 'Penfold', '12', '1', '212c Baker Street', '', 'London', 'EC1', 'GB'),
('2', '4', 'Chidi', '134', '2', '51 North Parade', 'East Town', 'Loughborough', 'LE3 6BD', 'GB');
INSERT INTO `child` ( `child_address_line1`, `child_address_town`, `height_in_cm`, `name`) VALUES
( '1 Nowhere Close', 'Peterborough', '0', 'Richard');

-- ----------------------------
--  Table structure for `contact`
-- ----------------------------
DROP TABLE IF EXISTS `contact`;
CREATE TABLE `contact` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title_code` varchar(5) DEFAULT NULL,
  `title` varchar(20) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `postcode` varchar(29) DEFAULT NULL,
  `security_pass_id` int(11) DEFAULT NULL,
  `child_nebulous_identifier` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=170 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `contact`
-- ----------------------------

INSERT INTO `contact` VALUES
('123', '001', 'Master', 'Luke', 'Skywalker', null, 6, 'Ariadne'),
('124', '002', 'Dark Lord', 'Anakin', 'Skywalker', null, 5, 'Lambeth'),
('125', '003', 'Mr', 'Han', 'Solo', null, 4, 'Lambeth'),
('126', '003', 'Mr', 'Boba', 'Fett', null, 3, 'Ariadne'),
('127', '004', 'Ms', 'Ellen', 'Ripley', null, 2, 'Ariadne'),
('128', '005', 'Captain', 'Arthur', 'Dallas', null, 1, 'Lambeth'),
('129', '003', 'Mr', 'Samuel', 'Brett', null, null, null),
('130', '001', 'Master', 'Gilbert', 'Kane', null, null, null),
('131', '007', 'Mrs', 'Joan', 'Lambert', null, null, null),
('132', '003', 'Mr', 'Dennis', 'Parker', null, null, null),
('133', '001', 'Master', 'Marty', 'McFly', null, null, null),
('134', '008', 'Doctor', 'Emmet', 'Brown', null, null, null),
('135', '003', 'Mr', 'Biff', 'Tannen', null, null, null),
('136', '006', 'Miss', 'Lorraine', 'Baines', null, null, null),
('137', '003', 'Mr', 'George', 'McFly', null, null, null),
('138', '003', 'Mr', 'Ulysses', 'McGill', 'PO27AJ', null, null),
('139', '008', 'Doctor', 'Carla', 'Porter', null, null, null),
('140', '003', 'Mr', 'James', 'Walker', null, null, null),
('141', '007', 'Mrs', 'Famke', 'Elliot', null, null, null),
('142', '006', 'Miss', 'Selena', 'Johnson', null, null, null),
('143', '003', 'Mr', 'Adrian', 'Thorn', null, null, null),
('144', '006', 'Miss', 'Katherine', "O'Neil", null, null, null),
('145', '006', 'Miss', 'Rachael', 'Darlington', null, null, null),
('146', '003', 'Mr', 'Ashley', 'Giles', null, null, null),
('147', '003', 'Mr', 'Mark', 'McMillan', null, null, null),
('148', '003', 'Mr', 'Jack', 'Butler', null, null, null),
('149', '003', 'Mr', 'Francis', 'Smith', null, null, null),
('150', '006', 'Miss', 'Katrina', 'Leaver', null, null, null),
('151', '003', 'Mr', 'Charles', 'Barnet', null, null, null),
('152', '007', 'Mrs', 'Sarah', 'Rumbelow', null, null, null),
('153', '006', 'Miss', 'Amelia', 'Dermot', null, null, null),
('154', '003', 'Mr', 'Graham', 'Christopher', null, null, null),
('155', '003', 'Mr', 'Axel', 'Robertson', null, null, null),
('156', '007', 'Mrs', 'Kate', 'Morgado', null, null, null),
('157', '008', 'Doctor', 'Jerry', 'Thatcham', null, null, null),
('158', '008', 'Doctor', 'Liz', 'Newman', null, null, null),
('159', '003', 'Mr', 'Andrew', 'Kent', null, null, null),
('160', '006', 'Miss', 'Michaela', 'Holloway', null, null, null),
('161', '007', 'Mrs', 'Gabby', 'Fenchurch', null, null, null),
('162', '003', 'Mr', 'Frank', 'Urquhart', null, null, null),
('163', '007', 'Mrs', 'Emma', 'Cartwright', null, null, null),
('164', '003', 'Mr', 'Mohammed', 'Patel', null, null, null),
('165', '003', 'Mr', 'Felix', 'Dodgson', null, null, null);

-- ----------------------------
--  Table structure for `country`
-- ----------------------------
DROP TABLE IF EXISTS `country`;
CREATE TABLE `country` (
  `code` varchar(2) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `country`
-- ----------------------------

INSERT INTO `country` VALUES ('GB', 'United Kingdom'), ('EU', 'Somewhere in Europe'), ('US', 'United States'), ('XX', 'Deepest Darkest Peru');

-- ----------------------------
--  Table structure for `security_pass`
-- ----------------------------
DROP TABLE IF EXISTS `security_pass`;
CREATE TABLE `security_pass` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serial_no` varchar(100),
  `employee_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `employee`
-- ----------------------------

INSERT INTO `security_pass` VALUES (1, 'ABCDEFG', 5);
INSERT INTO `security_pass` VALUES (2, 'GFEDCBA', 4);
INSERT INTO `security_pass` VALUES (3, '1234567', 3);
INSERT INTO `security_pass` VALUES (4, '7654321', 2);
INSERT INTO `security_pass` VALUES (5, 'AABBCCD', 1);
INSERT INTO `security_pass` VALUES (6, 'DDCCBBA', 6);

-- ----------------------------
--  Table structure for `employee` (self-referencing)
-- ----------------------------
DROP TABLE IF EXISTS `employee`;
CREATE TABLE `employee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100),
  `mentor_id` int(11) DEFAULT NULL,
  `mentee_id` int(11) DEFAULT NULL,
  `union_rep_id` int(11) DEFAULT NULL,
  `position_code` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `mentor` FOREIGN KEY (`mentor_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mentee` FOREIGN KEY (`mentee_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unionRep` FOREIGN KEY (`union_rep_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `employee`
-- ----------------------------

INSERT INTO `employee` VALUES (1, 'Steve', 3, 5, 2, 'GD');
INSERT INTO `employee` VALUES (2, 'Rob', 4, 3, 3, 'DD');
INSERT INTO `employee` VALUES (3, 'Becky', 2, 1, 3, 'SB');
INSERT INTO `employee` VALUES (4, 'Jack', 5, 2, 3, 'DD');
INSERT INTO `employee` VALUES (5, 'Tim', 1, 4, 2, 'VP');
INSERT INTO `employee` VALUES (6, 'Ben', 5, 2, 3, 'MD');

-- ----------------------------
--  Table structure for `position`
-- ----------------------------
DROP TABLE IF EXISTS `position`;
CREATE TABLE `position` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100),
  `value` varchar(2) DEFAULT NULL,
  `description` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `position`
-- ----------------------------

INSERT INTO `position` VALUES (1, 'Managing Director', 'MD', 'The big boss');
INSERT INTO `position` VALUES (2, 'Acting Deputy Assistant Vice Principal', 'VP', 'Pretends to be important');
INSERT INTO `position` VALUES (3, 'Supreme Being', 'SB', 'Lead PHP Developer and master of the black arts');
INSERT INTO `position` VALUES (4, 'Developer', 'DD', 'The one who does all the work');
INSERT INTO `position` VALUES (5, 'General Dogsbody', 'GD', 'Tea boy');

-- ----------------------------
--  Table structure for `parent`
-- ----------------------------
DROP TABLE IF EXISTS `parent`;
CREATE TABLE `parent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `address_line1` varchar(100) DEFAULT NULL,
  `address_line2` varchar(100) DEFAULT NULL,
  `address_town` varchar(100) DEFAULT NULL,
  `address_postcode` varchar(20) DEFAULT NULL,
  `address_country_code` varchar(2) DEFAULT NULL,
  `modified_date_time` datetime ON UPDATE CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)/*,
  CONSTRAINT `parent_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) /*ON DELETE SET NULL*/
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `parent`
-- ----------------------------

INSERT INTO `parent` VALUES ('1', '1', 'Danger Mouse', '221b Baker Street', null, 'London', 'W1A', 'GB', NOW());
INSERT INTO `parent` VALUES ('2', '3', 'Eleanor Shellstrop', 'The Good Place', null, 'Peterborough', 'PE3 8AF', 'XX', NOW());
INSERT INTO `parent` VALUES ('3', null, 'Arthur Mendoza', 'The Medium Place', null, 'Loughborough', 'LE3 8AF', 'XX', NOW());

-- ----------------------------
--  Table structure for `non_pk_child`
-- ----------------------------
DROP TABLE IF EXISTS `non_pk_child`;
CREATE TABLE `non_pk_child` (
  `user_id` int(11) DEFAULT NULL,
  `nebulous_identifier` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `second_parent_id` int(11) DEFAULT NULL,
  `foster_parent_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `non_pk_child`
-- ----------------------------

INSERT INTO `non_pk_child` VALUES ('1', 'Lambeth', '1', '2', 'Eselbeth'), ('2', 'Ariadne', '2', '1', 'Eselbeth');

-- ----------------------------
--  Table structure for `parent_of_non_pk_child`
-- ----------------------------
DROP TABLE IF EXISTS `parent_of_non_pk_child`;
CREATE TABLE `parent_of_non_pk_child` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `unmapped` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `parent_of_non_pk_child`
-- ----------------------------

INSERT INTO `parent_of_non_pk_child` VALUES ('1', 'Angus', null);
INSERT INTO `parent_of_non_pk_child` VALUES ('2', 'Eselbeth', null);

-- ----------------------------
--  Table structure for `pets`
-- ----------------------------
DROP TABLE IF EXISTS `pets`;
CREATE TABLE `pets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int,
  `type` varchar(255),
  `name` varchar(255),
  `weight_in_grams` int(11),
  PRIMARY KEY (`id`)
);

-- ----------------------------
--  Records of `pets`
-- ----------------------------
INSERT INTO `pets` (`id`, `parent_id`, `type`, `name`, `weight_in_grams`) VALUES
(1, 1, 'dog', 'Odie', 7450),
(2, 1, 'cat', 'Ludwig', 4125),
(3, 1, 'gerbil', 'Flame', 72),
(4, 2, 'terrapin', 'Rodney', 185),
(5, 2, 'frog', 'Guzzler', 9),
(6, 2, 'fish', 'Rambo', 4),
(7, 2, 'fish', 'Splendour', 7),
(8, 2, 'fish', 'Randy', 3),
(9, 2, 'fish', 'Julius', 6),
(10, 2, 'fish', 'Pepper', 6),
(11, 1, 'shrimp', 'Stripe', 4),
(12, 3, 'gerbil', 'Spot', 69),
(13, 3, 'cat', 'Trixie', 4238);

-- ----------------------------
--  Table structure for `policy`
-- ----------------------------
DROP TABLE IF EXISTS `policy`;
CREATE TABLE `policy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `underwriter_id` int(11) NOT NULL,
  `policy_number` varchar(255) DEFAULT NULL,
  `effective_start_date_time` datetime DEFAULT NULL,
  `effective_end_date_time` datetime DEFAULT NULL,
  `policy_status` varchar(255) DEFAULT NULL,
  `modification` varchar(255) DEFAULT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `login_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19072016 DEFAULT CHARSET=utf8mb4;

ALTER TABLE `policy` ADD INDEX `policy_number` USING BTREE (`policy_number`) comment '', ADD INDEX `policy_status` USING BTREE (`policy_status`) comment '', ADD INDEX `modification` USING BTREE (`modification`) comment '', ADD INDEX `login_id` USING BTREE (`login_id`) comment '';

-- ----------------------------
--  Records of `policy`
-- ----------------------------

INSERT INTO `policy` VALUES ('19071973', '1', 'P123457', '2018-12-03 00:01:00', '2019-12-02 23:59:59', 'INFORCE', null, '124', '1'),
('19071974', '1', 'P123456', '2018-12-03 00:01:00', '2019-12-02 23:59:59', 'UNPAID', null, '123', '1'),
('19071975', '2', 'P123458', '2018-12-04 00:01:00', '2019-12-03 23:59:59', 'PAID', null, '125', '2'),
('19071976', '1', 'P123459', '2018-11-04 00:01:00', '2019-12-03 23:59:59', 'UNPAID', null, '126', '1'),
('19071977', '1', 'P123458', '2018-12-06 00:01:00', '2019-12-05 23:59:59', 'INFORCE', null, '127', '2'),
('19071978', '1', 'P123461', '2018-11-06 00:01:00', '2019-12-05 23:59:59', 'INFORCE', null, '128', '6'),
('19071979', '1', 'P123462', '2018-11-07 00:01:00', '2019-12-06 23:59:59', 'INFORCE', null, '129', '7'),
('19071980', '1', 'P123463', '2018-12-08 00:01:00', '2019-12-07 23:59:59', 'UNPAID', null, '130', '8'),
('19071981', '1', 'P123464', '2018-11-08 00:01:00', '2019-12-07 23:59:59', 'UNPAID', null, '131', '9'),
('19071982', '1', 'P123465', '2018-12-08 00:01:00', '2019-12-07 23:59:59', 'UNPAID', null, '132', '10'),
('19071983', '1', 'P123466', '2018-12-08 00:01:00', '2019-12-07 23:59:59', 'INFORCE', null, '133', '11'),
('19071984', '1', 'P123467', '2018-12-09 00:01:00', '2019-12-08 23:59:59', 'INFORCE', null, '134', '12'),
('19071985', '2', 'P123468', '2018-12-11 00:01:00', '2019-12-10 23:59:59', 'UNPAID', null, '135', '13'),
('19071986', '1', 'P123469', '2018-11-15 00:01:00', '2019-12-14 23:59:59', 'INFORCE', null, '136', '14'),
('19071987', '1', 'P123470', '2018-12-15 00:01:00', '2019-12-14 23:59:59', 'UNPAID', null, '137', '15'),
('19071988', '1', 'P123471', '2018-12-15 00:01:00', '2019-12-14 23:59:59', 'UNPAID', null, '138', '16'),
('19071989', '1', 'P123472', '2018-12-15 00:01:00', '2019-12-14 23:59:59', 'UNPAID', null, '139', '17'),
('19071990', '2', 'P123473', '2018-12-15 00:01:00', '2019-12-14 23:59:59', 'INFORCE', null, '140', '18'),
('19071991', '2', 'P123474', '2018-12-16 00:01:00', '2019-12-15 23:59:59', 'INFORCE', null, '141', '19'),
('19071992', '1', 'P123475', '2018-12-18 00:01:00', '2019-12-17 23:59:59', 'UNPAID', null, '142', '20'),
('19071993', '1', 'P123476', '2018-12-18 00:01:00', '2019-12-17 23:59:59', 'PAID', null, '143', '21'),
('19071994', '1', 'P123477', '2018-12-19 00:01:00', '2019-12-18 23:59:59', 'UNPAID', null, '144', '22'),
('19071995', '1', 'P123478', '2018-12-19 00:01:00', '2019-12-18 23:59:59', 'UNPAID', null, '145', '23'),
('19071996', '1', 'P123479', '2018-12-19 00:01:00', '2019-12-18 23:59:59', 'INFORCE', null, '146', '24'),
('19071997', '1', 'P123480', '2018-12-20 00:01:00', '2019-12-19 23:59:59', 'UNPAID', null, '147', '25'),
('19071998', '1', 'P123481', '2018-12-20 00:01:00', '2019-12-19 23:59:59', 'UNPAID', null, '148', '26'),
('19071999', '1', 'P123482', '2018-12-21 00:01:00', '2019-12-20 23:59:59', 'UNPAID', null, '149', '27'),
('19072000', '1', 'P123483', '2018-12-23 00:01:00', '2019-12-22 23:59:59', 'INFORCE', null, '150', '28'),
('19072001', '1', 'P123484', '2018-12-23 00:01:00', '2019-12-22 23:59:59', 'INFORCE', null, '151', '29'),
('19072002', '1', 'P123485', '2018-12-24 00:01:00', '2019-12-23 23:59:59', 'UNPAID', null, '152', '30'),
('19072003', '1', 'P123486', '2018-12-24 00:01:00', '2019-12-23 23:59:59', 'INFORCE', null, '153', '31'),
('19072004', '1', 'P123487', '2018-12-24 00:01:00', '2019-12-23 23:59:59', 'UNPAID', null, '154', '32'),
('19072005', '1', 'P123488', '2018-12-26 00:01:00', '2019-12-25 23:59:59', 'UNPAID', null, '155', '33'),
('19072006', '1', 'P123489', '2018-12-27 00:01:00', '2019-12-26 23:59:59', 'UNPAID', null, '156', '34'),
('19072007', '1', 'P123490', '2018-12-28 00:01:00', '2019-12-27 23:59:59', 'PAID', null, '157', '35'),
('19072008', '1', 'P123491', '2018-12-28 00:01:00', '2019-12-27 23:59:59', 'UNPAID', null, '158', '36'),
('19072009', '2', 'P123492', '2018-12-28 00:01:00', '2019-12-27 23:59:59', 'UNPAID', null, '159', '37'),
('19072010', '1', 'P123493', '2018-12-29 00:01:00', '2019-12-28 23:59:59', 'UNPAID', null, '160', '38'),
('19072011', '1', 'P123594', '2018-12-30 00:01:00', '2019-12-29 23:59:59', 'INFORCE', null, '161', '39'),
('19072012', '1', 'P123595', '2018-12-30 00:01:00', '2019-12-29 23:59:59', 'INFORCE', null, '162', '40'),
('19072013', '1', 'P123596', '2018-12-30 00:01:00', '2019-12-29 23:59:59', 'UNPAID', null, '163', '41'),
('19072014', '2', 'P123597', '2018-12-30 00:01:00', '2019-12-29 23:59:59', 'UNPAID', null, '164', '42'),
('19072015', '1', 'P123598', '2018-12-31 00:01:00', '2019-12-30 23:59:59', 'PAID', null, '165', '43'),
('19072016', '1', 'P123595', '2018-12-30 00:01:00', '2019-12-29 23:59:59', 'INFORCE', 'MTA', '162', '40');

-- ----------------------------
--  Table structure for `telematics_box`
-- ----------------------------
DROP TABLE IF EXISTS `telematics_box`;
CREATE TABLE `telematics_box` (
  `vehicle_id` int(11) NOT NULL,
  `imei` varchar(255) DEFAULT NULL,
  `status_date` datetime DEFAULT NULL,
  PRIMARY KEY (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `telematics_box`
-- ----------------------------

INSERT INTO `telematics_box` VALUES ('1', '1234567812345678', '2018-12-25 12:00:18'), ('2', '1234567812347567', '2018-12-25 12:00:18'), ('3', '1234567812453478', '2018-12-25 12:00:18'), ('4', '1248558812345678', '2018-12-25 12:00:18'), ('5', '1234455792345678', '2018-12-25 12:00:18'), ('6', '1234567845525678', '2018-12-25 12:00:18'), ('7', '1234567812312211', '2018-12-25 12:00:18'), ('8', '1234567812375678', '2018-12-25 12:00:18'), ('9', '1212457812345678', '2018-12-25 12:00:18'), ('10', '1234567812312877', '2018-12-25 12:00:18'), ('11', '1234567812988878', '2018-12-25 12:00:18'), ('12', '1234567812341323', '2018-12-25 12:00:18'), ('13', '1234423412345678', '2018-12-25 12:00:18'), ('14', '1234567812453478', '2018-12-25 12:00:18'), ('15', '1234567422536578', '2018-12-25 12:00:18'), ('16', '1234567812365444', '2018-12-25 12:00:18'), ('17', '1234567844521148', '2018-12-25 12:00:18'), ('18', '1234567819851448', '2018-12-25 12:00:18'), ('19', '1234567812343214', '2018-12-25 12:00:18'), ('20', '1234567214565948', '2018-12-25 12:00:18'), ('21', '1234567812982167', '2018-12-25 12:00:18'), ('22', '1234567812321568', '2018-12-25 12:00:18'), ('23', '1234567812369841', '2018-12-25 12:00:18'), ('24', '1234567812454541', '2018-12-25 12:00:18'), ('25', '1234567812302114', '2018-12-25 12:00:18'), ('26', '1234567865455848', '2018-12-25 12:00:18'), ('27', '1234567812345877', '2018-12-25 12:00:18'), ('28', '1234565645614178', '2018-12-25 12:00:18'), ('29', '1234567812985211', '2018-12-25 12:00:18'), ('30', '1234567832168771', '2018-12-25 12:00:18'), ('31', '1234567854859988', '2018-12-25 12:00:18'), ('32', '1234567816521001', '2018-12-25 12:00:18'), ('33', '1234567818774118', '2018-12-25 12:00:18'), ('34', '1234567819856522', '2018-12-25 12:00:18'), ('35', '1234567865101010', '2018-12-25 12:00:18'), ('36', '1234567817474561', '2018-12-25 12:00:18'), ('37', '1234567865210012', '2018-12-25 12:00:18'), ('38', '1234567812974899', '2018-12-25 12:00:18'), ('39', '1234567811145208', '2018-12-25 12:00:18'), ('40', '1234568741020678', '2018-12-25 12:00:18'), ('41', '1234567818110501', '2018-12-25 12:00:18'), ('42', '1234567812966385', '2018-12-25 12:00:18'), ('43', '1234567811002124', '2018-12-25 12:00:18');

-- ----------------------------
--  Table structure for `underwriter`
-- ----------------------------
DROP TABLE IF EXISTS `underwriter`;
CREATE TABLE `underwriter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `underwriter`
-- ----------------------------

INSERT INTO `underwriter` VALUES ('1', 'Scarfolk Insurance'), ('2', 'Springfield');

-- ----------------------------
--  Table structure for `user`
-- ----------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `user`
-- ----------------------------

INSERT INTO `user` VALUES ('1', 'branch', 'danger.mouse@example.com'), ('2', 'staff', 'penfold.hamster@example.com'),
('3', 'broker', 'eleanor.shellstrop@example.com'), ('4', 'staff', 'chidi@example.com');

-- ----------------------------
--  Table structure for `user_alternative`
-- ----------------------------
DROP TABLE IF EXISTS `user_alternative`;
CREATE TABLE `user_alternative` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `user_alternative`
-- ----------------------------

INSERT INTO `user_alternative` VALUES ('1', 'test1', 'alternative1@example.com'), ('2', 'test2', 'alternative2@example.com'),
('3', 'test3', 'alternative3@example.com'), ('4', 'test4', 'alertnative4@example.com');

-- ----------------------------
--  Table structure for `vehicle`
-- ----------------------------
DROP TABLE IF EXISTS `vehicle`;
CREATE TABLE `vehicle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `policy_id` int(11) DEFAULT NULL,
  `abi_code` varchar(20) DEFAULT NULL,
  `reg_no` varchar(15) DEFAULT NULL,
  `make` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `telematics_box_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4;

ALTER TABLE `objectiphy_test`.`vehicle` ADD INDEX `regNo` USING BTREE (`reg_no`) comment '';

-- ----------------------------
--  Records of `vehicle`
-- ----------------------------

INSERT INTO `vehicle` VALUES ('1', '19071974', '12345678', 'PJ63LXR', 'Vauxhall', 'Corsa', '1'), ('2', '19071975', '12345679', 'PJ63LXA', 'Fiat', '500', '2'), ('3', '19071976', '12345678', 'PJ63LXB', 'Ford', 'Fiesta', '3'), ('4', '19071977', '12345680', 'PJ63LXC', 'Vauxhall', 'Corsa', '4'), ('5', '19071978', '12345678', 'PJ63LXD', 'Vauxhall', 'Corsa', '5'), ('6', '19071979', '12345681', 'PJ63LXE', 'Ford', 'Fiesta', '6'), ('7', '19071980', '12345682', 'PJ63LXF', 'Ford', 'Fiesta', '7'), ('8', '19071981', '12345678', 'PJ63LXG', 'Vauxhall', 'Corsa', '8'), ('9', '19071982', '12345683', 'PJ63LXH', 'Fiat', '500', '9'), ('10', '19071983', '12345678', 'PJ63LXI', 'Vauxhall', 'Corsa', '10'), ('11', '19071984', '12345684', 'PJ63LXJ', 'Ford', 'Fiesta', '11'), ('12', '19071985', '12345678', 'PJ63LXK', 'Vauxhall', 'Corsa', '12'), ('13', '19071986', '12345678', 'PJ63LXL', 'Vauxhall', 'Corsa', '13'), ('14', '19071987', '12345688', 'PJ63LXM', 'Ford', 'Fiesta', '14'), ('15', '19071988', '12345678', 'PJ63LXN', 'Vauxhall', 'Corsa', '15'), ('16', '19071989', '12345699', 'PJ63LXO', 'Audi', 'A2', '16'), ('17', '19071990', '12345678', 'PJ63LXP', 'Vauxhall', 'Corsa', '17'), ('18', '19071991', '12345612', 'PJ63LXQ', 'Hyundai', 'i20', '18'), ('19', '19071992', '12345678', 'PJ63LXR', 'Vauxhall', 'Corsa', '19'), ('20', '19071993', '12345615', 'PJ63LXS', 'Audi', 'A2', '20'), ('21', '19071994', '12345678', 'PJ63LXT', 'Vauxhall', 'Corsa', '21'), ('22', '19071995', '12345678', 'PJ63LXU', 'Vauxhall', 'Corsa', '22'), ('23', '19071996', '12345716', 'PJ63LXV', 'Vauxhall', 'Corsa', '23'), ('24', '19071997', '12345668', 'PJ63LXW', 'Audi', 'A2', '24'), ('25', '19071998', '12345645', 'PJ63LXX', 'Vauxhall', 'Corsa', '25'), ('26', '19071999', '12345664', 'PJ63LXY', 'Vauxhall', 'Corsa', '26'), ('27', '19072000', '12345678', 'PJ63LXZ', 'Fiat', '500', '27'), ('28', '19072001', '12345678', 'PJ63LAR', 'Vauxhall', 'Corsa', '28'), ('29', '19072002', '12345678', 'PJ63LBR', 'Fiat', '500', '29'), ('30', '19072003', '12345672', 'PJ63LCR', 'Fiat', '500', '30'), ('31', '19072004', '12345678', 'PJ63LDR', 'Vauxhall', 'Corsa', '31'), ('32', '19072005', '12345678', 'PJ63LER', 'Hyundai', 'i20', '32'), ('33', '19072006', '12345678', 'PJ63LFR', 'Vauxhall', 'Corsa', '33'), ('34', '19072007', '12345678', 'PJ63LGR', 'Audi', 'A2', '34'), ('35', '19072008', '12345678', 'PJ63LHR', 'Vauxhall', 'Corsa', '35'), ('36', '19072009', '12345678', 'PJ63LJR', 'Volkswagon', 'Polo', '36'), ('37', '19072010', '12345678', 'PJ63LKR', 'Vauxhall', 'Corsa', '37'), ('38', '19072011', '12345678', 'PJ63LLR', 'Vauxhall', 'Corsa', '38'), ('39', '19072012', '12345678', 'PJ63LMR', 'Vauxhall', 'Corsa', '39'), ('40', '19072013', '12345678', 'PJ63LNR', 'Hyundai', 'i20', '40'), ('41', '19072014', '12345678', 'PJ63LPR', 'Hyundai', 'i20', '41');

-- ----------------------------
--  Table structure for `wheel`
-- ----------------------------
DROP TABLE IF EXISTS `wheel`;
CREATE TABLE `wheel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `load_bearing` tinyint(4) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
--  Records of `wheel`
-- ----------------------------

INSERT INTO `wheel` VALUES ('1', '1', '1', 'front nearside'), ('2', '1', '1', 'front offside'), ('3', '1', '1', 'rear nearside'), ('4', '1', '1', 'rear offside'), ('5', '1', '0', 'steering'), ('6', '2', '1', 'front middle'), ('7', '2', '1', 'rear nearside'), ('8', '2', '1', 'rear offside'), ('9', '2', '0', 'steering');

SET FOREIGN_KEY_CHECKS = 1;
