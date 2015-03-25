CREATE TABLE `sxweb_config` (
  `item` VARCHAR(64) PRIMARY KEY,
  `value` TEXT 
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `sxweb_config` (`item`, `value`) VALUES ('db_version', '0.3.0'), ('db_initial_version', '0.3.0'), ('db_created', NOW()),('db_modified', NOW());