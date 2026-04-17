CREATE TABLE IF NOT EXISTS piwigo_familink_cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  image_id INT NOT NULL,
  print_format ENUM('10x15cm', '15x20cm') NOT NULL DEFAULT '10x15cm',
  copies INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_user_image_format (user_id, image_id, print_format),
  KEY idx_user (user_id),
  KEY idx_image (image_id)
);

CREATE TABLE IF NOT EXISTS piwigo_familink_bridge_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token CHAR(64) NOT NULL,
  user_id INT NOT NULL,
  image_id INT NOT NULL,
  print_format ENUM('10x15cm', '15x20cm') NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_token (token),
  KEY idx_user (user_id),
  KEY idx_image (image_id),
  KEY idx_expires (expires_at)
);
