ALTER TABLE `users_act_keys` CHANGE `key` `activation_key` VARCHAR(255) UNIQUE NOT NULL;
ALTER TABLE `users_act_keys` CHANGE `data` `key_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;
