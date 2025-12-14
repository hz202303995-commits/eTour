-- Migration: Create guide_monthly_stats table
-- Run this with: mysql -u <user> -p <database> < 001_create_guide_monthly_stats.sql

CREATE TABLE IF NOT EXISTS guide_monthly_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guide_id INT NOT NULL,
    month CHAR(7) NOT NULL,
    confirmed INT DEFAULT 0,
    pending INT DEFAULT 0,
    cancelled INT DEFAULT 0,
    revenue DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_guide_month (guide_id, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;