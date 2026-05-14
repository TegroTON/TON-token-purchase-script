-- Schema for the Tegro.Money + Telegram-bot token-purchase template.
--
-- Apply once on a fresh database:
--     mysql -u $DB_USER -p $DB_NAME < migrations/001_initial.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- users — one row per Telegram chat that has interacted with the bot.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    chatid       VARCHAR(64)    NOT NULL,
    balance      DECIMAL(30, 9) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chatid)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- paylinks — one row per "buy" intent. status transitions 0 -> 1 atomically
-- when Tegro.Money confirms payment via webhook. The compound primary key
-- gives us a single-statement idempotency check (see PaylinkRepository).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS paylinks (
    rowid          VARCHAR(64)    NOT NULL,
    chatid         VARCHAR(64)    NOT NULL,
    status         TINYINT        NOT NULL DEFAULT 0
                       COMMENT '0=pending, 1=paid (terminal)',
    fiat_amount    DECIMAL(20, 2) NOT NULL,
    fiat_currency  CHAR(3)        NOT NULL DEFAULT 'RUB',
    created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at        TIMESTAMP      NULL DEFAULT NULL,
    PRIMARY KEY (rowid, chatid),
    KEY idx_paylinks_chatid_created (chatid, created_at),
    CONSTRAINT fk_paylinks_user
        FOREIGN KEY (chatid)
        REFERENCES users (chatid)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
