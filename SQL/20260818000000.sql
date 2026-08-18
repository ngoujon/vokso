CREATE TABLE IF NOT EXISTS `rate_limit` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `route` VARCHAR(50) NOT NULL,
    `requested_at` DATETIME NOT NULL,
    INDEX `idx_rate_limit_ip_route_time` (`ip_address`, `route`, `requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
