DROP TABLE IF EXISTS `shared`;
CREATE TABLE `shared` (
  -- The unique hash of this shared file
  file_id VARCHAR(255) PRIMARY KEY,

  -- SX Auth Token of the user
  user_auth_token VARCHAR(255) NOT NULL,

  -- The file path (without beginning / and including the volume)
  file_path VARCHAR(255) NOT NULL,

  -- Creation date
  created_at DATETIME NOT NULL,

  -- Expire date
  expire_at DATETIME NOT NULL,

  -- The SHA1 of the password to download the file
  -- If empty there's no password
  file_password VARCHAR(64) NOT NULL DEFAULT ''

) ENGINE=InnoDB DEFAULT CHARSET=utf8;