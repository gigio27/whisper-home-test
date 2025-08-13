-- migrations executed on Wed Aug 13 16:44:54 IDT 2025
-- 0) Encoding security
SET NAMES utf8mb4;

-- 1) config : primary key + unique(setting)
ALTER TABLE config
  MODIFY id INT(11) NOT NULL AUTO_INCREMENT,
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY uq_setting (setting);

-- 2) contacts : primary key + index + InnoDB + utf8mb4
ALTER TABLE contacts
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4;
ALTER TABLE contacts
  MODIFY row_id INT(11) NOT NULL AUTO_INCREMENT,
  ADD PRIMARY KEY (row_id),
  ADD KEY idx_belongs_to (belongs_to_username),
  ADD KEY idx_contact (contact_id);

-- 3) messages : primary key + index + InnoDB + utf8mb4
ALTER TABLE messages
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4;
ALTER TABLE messages
  MODIFY row_id INT(11) NOT NULL AUTO_INCREMENT,
  ADD PRIMARY KEY (row_id),
  ADD KEY idx_owner_time (belongs_to_username, msg_datetime),
  ADD KEY idx_contact (contact_id);

-- 4) session token table (linked to a textual 'username')
CREATE TABLE IF NOT EXISTS auth_tokens (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(255) NOT NULL,
  token CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL,
  UNIQUE KEY uq_token (token),
  KEY idx_user_expires (username, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) read receipts (for each message et for each username)
CREATE TABLE IF NOT EXISTS messages_read (
  message_id INT(11) NOT NULL,
  username VARCHAR(255) NOT NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, username),
  KEY idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) missing values config (sound + otp)
INSERT INTO config (setting, value, comments) VALUES
('notification_sound_url', '/assets/notify.mp3', 'Default notification sound URL'),
('notification_sound_enabled', '1', '1=on,0=off'),
('otp_length','6','OTP code length'),
('otp_valid_minutes','10','OTP validity'),
('otp_req_window_sec','60','Min seconds between OTP requests'),
('otp_max_per_hour','5','Hourly rate limit'),
('otp_max_per_day','12','Daily rate limit'),
('token_expiry_hours','24','Session validity (hours)')
ON DUPLICATE KEY UPDATE value=VALUES(value), comments=VALUES(comments);
