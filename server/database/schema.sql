-- Rishe database schema (MySQL 5.7+ / MariaDB 10.3+)
-- All tables use the rishe_ prefix and utf8mb4.

CREATE TABLE IF NOT EXISTS rishe_admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  name_en VARCHAR(120) NOT NULL DEFAULT '',
  mobile VARCHAR(11) NOT NULL,
  password_hash VARCHAR(255) NULL,
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token_hash),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_otps (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mobile VARCHAR(11) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_throttle (
  throttle_key VARCHAR(190) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  PRIMARY KEY (throttle_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_courses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  title_en VARCHAR(190) NOT NULL DEFAULT '',
  code VARCHAR(10) NOT NULL DEFAULT '',
  level VARCHAR(20) NOT NULL DEFAULT '',
  description TEXT NULL,
  hours SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  color ENUM('brand','amber','mute') NOT NULL DEFAULT 'brand',
  is_premium TINYINT(1) NOT NULL DEFAULT 0,
  price INT UNSIGNED NOT NULL DEFAULT 0,
  pass_score TINYINT UNSIGNED NOT NULL DEFAULT 70,
  sort INT NOT NULL DEFAULT 0,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_chapters (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_lessons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  chapter_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  minutes TINYINT UNSIGNED NOT NULL DEFAULT 5,
  is_free TINYINT(1) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  PRIMARY KEY (id),
  KEY idx_chapter (chapter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_phrases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lesson_id INT UNSIGNED NOT NULL,
  en VARCHAR(500) NOT NULL,
  fa VARCHAR(500) NOT NULL DEFAULT '',
  ipa VARCHAR(255) NOT NULL DEFAULT '',
  pron_fa VARCHAR(255) NOT NULL DEFAULT '',
  words_json TEXT NULL,
  audio_path VARCHAR(255) NULL,
  teacher_audio_path VARCHAR(255) NULL,
  sort INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_lesson (lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- owner_type 'lesson' = practice question of a lesson, 'exam' = final exam question of a course
CREATE TABLE IF NOT EXISTS rishe_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_type ENUM('lesson','exam') NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  type ENUM('meaning','listen') NOT NULL DEFAULT 'meaning',
  prompt VARCHAR(500) NOT NULL,
  say_text VARCHAR(500) NOT NULL DEFAULT '',
  audio_path VARCHAR(255) NULL,
  options_json TEXT NOT NULL,
  answer TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_owner (owner_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_progress (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NOT NULL,
  correct SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  xp SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  completed_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_lesson (user_id, lesson_id),
  KEY idx_user_date (user_id, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_activity (
  user_id INT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  PRIMARY KEY (user_id, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_exam_results (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  score TINYINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_user_course (user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_certificates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  status ENUM('pending_payment','pending','approved','rejected') NOT NULL DEFAULT 'pending',
  score TINYINT UNSIGNED NULL,
  hours SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  name_fa VARCHAR(120) NOT NULL,
  name_en VARCHAR(120) NOT NULL DEFAULT '',
  course_title VARCHAR(190) NOT NULL,
  course_code VARCHAR(10) NOT NULL DEFAULT '',
  reject_reason VARCHAR(255) NULL,
  requested_at DATETIME NOT NULL,
  approved_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  UNIQUE KEY uq_user_course (user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(120) NOT NULL,
  days SMALLINT UNSIGNED NOT NULL,
  price INT UNSIGNED NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_subscriptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  payment_id INT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_user_end (user_id, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_purchases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  payment_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_course (user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- amount is stored in Toman and converted to Rial only when talking to the gateway
CREATE TABLE IF NOT EXISTS rishe_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('subscription','course','certificate') NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  amount INT UNSIGNED NOT NULL,
  gateway VARCHAR(20) NOT NULL DEFAULT 'zibal',
  track_id VARCHAR(64) NULL,
  ref_number VARCHAR(64) NULL,
  card_number VARCHAR(32) NULL,
  status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_track (track_id),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rishe_settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
