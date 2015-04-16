-- Update the user table structure
ALTER TABLE `users` ADD `login` VARCHAR(255) NOT NULL UNIQUE;
UPDATE `users` SET `login` = `email`;
ALTER TABLE `users` DROP `passwd`;

ALTER TABLE `users` DROP INDEX `secret_key`;

-- Update the db version
UPDATE `sxweb_config` SET `value` = '0.4.0' WHERE `item` = 'db_version';
UPDATE `sxweb_config` SET `value` = NOW() WHERE `item` = 'db_modified';
