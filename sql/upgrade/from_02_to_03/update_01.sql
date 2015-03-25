-- Rename the old misleading autoken 
ALTER TABLE `users` CHANGE `autoken` `secret_key` VARCHAR(255) UNIQUE NOT NULL;

-- Adds the user roles
ALTER TABLE `users` ADD `user_role` enum('guest','registered','admin') NOT NULL DEFAULT 'registered';