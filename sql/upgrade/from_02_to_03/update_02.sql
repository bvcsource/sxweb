alter table `users` MODIFY `passwd` VARCHAR(255) NOT NULL;
alter table `user_reset_password` MODIFY `hash` VARCHAR(255) NOT NULL;