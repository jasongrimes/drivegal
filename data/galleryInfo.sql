CREATE TABLE `galleryInfo` (
  `googleUserId` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `galleryName` VARCHAR(255) NOT NULL,
  `credentials` TEXT NOT NULL DEFAULT '',
  `isActive` TINYINT(1) NOT NULL DEFAULT 1,
  `timeCreated` DATETIME NOT NULL,
  PRIMARY KEY (`googleUserId`),
  UNIQUE KEY `unique_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
