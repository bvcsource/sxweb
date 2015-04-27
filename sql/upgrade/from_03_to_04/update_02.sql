ALTER TABLE `user_reset_password` DROP FOREIGN  KEY `user_reset_password_ibfk_2`;
ALTER TABLE `user_reset_password` DROP `uid`;
ALTER TABLE `user_reset_password` ADD `login` VARCHAR(255) NOT NULL UNIQUE;
ALTER TABLE `user_reset_password` ADD `email` VARCHAR(255) NOT NULL;



