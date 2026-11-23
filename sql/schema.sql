-- DEV ONLY: wipes the old test database so this file can be re-imported cleanly
DROP DATABASE IF EXISTS HJA_IT107;
CREATE DATABASE IF NOT EXISTS HJA_IT107 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE HJA_IT107;

-- 1. Users. ID Number is the PRIMARY KEY (handout 4.4)
CREATE TABLE users (
    id_number            CHAR(9)      NOT NULL PRIMARY KEY,           -- xxxx-xxxx
    first_name           VARCHAR(50)  NOT NULL,
    middle_name          VARCHAR(50)  DEFAULT NULL,
    family_name          VARCHAR(50)  NOT NULL,
    name_extension       VARCHAR(10)  DEFAULT NULL,
    birthdate            DATE         NOT NULL,
    age                  TINYINT UNSIGNED NOT NULL,
    purok_street         VARCHAR(50)  NOT NULL,
    barangay             VARCHAR(50)  NOT NULL,
    municipality         VARCHAR(50)  NOT NULL,
    country              VARCHAR(50)  NOT NULL,
    zip_code             CHAR(4)      NOT NULL,
    email                VARCHAR(100) NOT NULL UNIQUE,
    username             VARCHAR(30)  NOT NULL UNIQUE,
    password_hash        VARCHAR(255) NOT NULL,
    role                 ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_verified          TINYINT(1)   NOT NULL DEFAULT 0,
    -- system fields (patient profile)
    contact_number       CHAR(11)     NOT NULL,                       -- 09xxxxxxxxx
    blood_type           TEXT         NOT NULL,                       -- AES-256-GCM encrypted
    prior_vaccination    VARCHAR(45)  NOT NULL,
    known_allergies      VARCHAR(100) DEFAULT NULL,
    privacy_consent_at   DATETIME     NOT NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 1b. Appointments booked by logged-in users, reviewed by the admin
CREATE TABLE appointments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          CHAR(9)     NOT NULL,
    category         VARCHAR(50) NOT NULL,
    animal_type      VARCHAR(30) NOT NULL,
    exposure_date    DATE        NOT NULL,
    exposure_details VARCHAR(100) DEFAULT NULL,
    preferred_date   DATE        NOT NULL,
    status           ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_appt_date (preferred_date, status),
    FOREIGN KEY (user_id) REFERENCES users(id_number) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. Security question answers (hashed, lowercase before hashing)
CREATE TABLE answers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       CHAR(9)     NOT NULL,
    question_code VARCHAR(4)  NOT NULL,
    answer_hash   VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id_number) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. One-time passwords (hashed). Resend limits are counted from created_at.
CREATE TABLE otps (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    CHAR(9)  NOT NULL,
    otp_hash   VARCHAR(255) NOT NULL,
    purpose    ENUM('register','login','reset') NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts   TINYINT  NOT NULL DEFAULT 0,
    is_used    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_lookup (user_id, purpose, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id_number) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Append-only activity log. The app only ever INSERTs and SELECTs here.
CREATE TABLE activity_log (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    event_type     VARCHAR(40)  NOT NULL,
    username_typed VARCHAR(100) NOT NULL DEFAULT '',
    user_id        CHAR(9)      DEFAULT NULL,
    ip_address     VARCHAR(45)  NOT NULL,
    user_agent     VARCHAR(500) NOT NULL,
    details        TEXT         DEFAULT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_time (event_type, created_at),
    INDEX idx_ip_time (ip_address, created_at)
) ENGINE=InnoDB;

-- 5. Flags written by scripts/monitor.php (no duplicates per pattern+target+day)
CREATE TABLE security_flags (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    flag_type         VARCHAR(40)  NOT NULL,
    severity          VARCHAR(4)   NOT NULL,
    target_identifier VARCHAR(100) NOT NULL,
    description       TEXT         NOT NULL,
    flagged_date      DATE         NOT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_flag (flag_type, target_identifier, flagged_date)
) ENGINE=InnoDB;

-- 6. Password history (last 3 hashes)
CREATE TABLE password_history (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       CHAR(9)      NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id_number) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. IP blocks
CREATE TABLE ip_blocks (
    ip_address    VARCHAR(45) NOT NULL PRIMARY KEY,
    blocked_until DATETIME    NOT NULL
) ENGINE=InnoDB;

-- 8. Lockout counters, keyed by the username TYPED (real and fake usernames behave identically)
CREATE TABLE lockouts (
    username_key VARCHAR(100) NOT NULL PRIMARY KEY,
    failures     INT          NOT NULL DEFAULT 0,
    locked_until DATETIME     DEFAULT NULL,
    tier_alerted TINYINT      NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- OPTIONAL (handout "Protecting the log itself"): least-privilege DB account for the app.
-- Then put ar_app in config.php. The app can then never UPDATE/DELETE the log.
-- CREATE USER 'ar_app'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON HJA_IT107.users            TO 'ar_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON HJA_IT107.appointments     TO 'ar_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON HJA_IT107.answers          TO 'ar_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON HJA_IT107.otps             TO 'ar_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON HJA_IT107.password_history TO 'ar_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON HJA_IT107.ip_blocks        TO 'ar_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON HJA_IT107.lockouts         TO 'ar_app'@'localhost';
-- GRANT SELECT, INSERT                 ON HJA_IT107.activity_log     TO 'ar_app'@'localhost';
-- GRANT SELECT, INSERT                 ON HJA_IT107.security_flags   TO 'ar_app'@'localhost';
