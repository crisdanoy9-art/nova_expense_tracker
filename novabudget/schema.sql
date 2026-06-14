-- ============================================================
-- NovaBudget — PostgreSQL Schema v2.0
-- Run: psql -U postgres -d novabudget -f schema.sql
-- ============================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Drop in reverse-dependency order
DROP TABLE IF EXISTS audit_log      CASCADE;
DROP TABLE IF EXISTS notifications  CASCADE;
DROP TABLE IF EXISTS budgets        CASCADE;
DROP TABLE IF EXISTS expenses       CASCADE;
DROP TABLE IF EXISTS categories     CASCADE;
DROP TABLE IF EXISTS sessions       CASCADE;
DROP TABLE IF EXISTS users          CASCADE;

-- ── USERS ──────────────────────────────────────
CREATE TABLE users (
    id               UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    name             VARCHAR(100) NOT NULL,
    email            VARCHAR(150) NOT NULL,
    password_hash    TEXT        NOT NULL,
    avatar_initials  VARCHAR(3),
    currency         VARCHAR(10) NOT NULL DEFAULT 'USD',
    timezone         VARCHAR(60) NOT NULL DEFAULT 'UTC',
    plan             VARCHAR(20) NOT NULL DEFAULT 'free'
                     CHECK (plan IN ('free','pro','enterprise')),
    is_active        BOOLEAN     NOT NULL DEFAULT TRUE,
    email_verified   BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT chk_users_email CHECK (email ~* '^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$')
);
CREATE INDEX idx_users_email ON users (LOWER(email));
CREATE INDEX idx_users_active ON users (is_active);

-- ── SESSIONS ───────────────────────────────────
CREATE TABLE sessions (
    id          UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id     UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  TEXT        NOT NULL,
    ip_address  INET,
    user_agent  TEXT,
    expires_at  TIMESTAMPTZ NOT NULL DEFAULT (NOW() + INTERVAL '30 days'),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_session_token UNIQUE (token_hash)
);
CREATE INDEX idx_sessions_user    ON sessions (user_id);
CREATE INDEX idx_sessions_token   ON sessions (token_hash);
CREATE INDEX idx_sessions_expires ON sessions (expires_at);

-- ── CATEGORIES ─────────────────────────────────
CREATE TABLE categories (
    id          UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id     UUID        REFERENCES users(id) ON DELETE CASCADE,
    name        VARCHAR(100) NOT NULL,
    emoji       VARCHAR(10)  NOT NULL DEFAULT '💰',
    color       VARCHAR(7)   NOT NULL DEFAULT '#00e5ff',
    icon        VARCHAR(50),
    is_system   BOOLEAN     NOT NULL DEFAULT FALSE,
    sort_order  INT         NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_cat_user_name UNIQUE (user_id, name)
);
CREATE INDEX idx_categories_user ON categories (user_id);

-- ── EXPENSES ───────────────────────────────────
CREATE TABLE expenses (
    id             UUID          PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id        UUID          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category_id    UUID          NOT NULL REFERENCES categories(id),
    description    TEXT          NOT NULL,
    amount         NUMERIC(12,2) NOT NULL CHECK (amount > 0),
    payment_method VARCHAR(50)   NOT NULL DEFAULT 'cash'
                   CHECK (payment_method IN
                     ('cash','credit_card','debit_card','bank_transfer',
                      'digital_wallet','crypto','other')),
    expense_date   DATE          NOT NULL DEFAULT CURRENT_DATE,
    notes          TEXT,
    receipt_url    TEXT,
    is_recurring   BOOLEAN       NOT NULL DEFAULT FALSE,
    recurrence     VARCHAR(20)   CHECK (recurrence IN ('daily','weekly','monthly','yearly',NULL)),
    status         VARCHAR(20)   NOT NULL DEFAULT 'completed'
                   CHECK (status IN ('completed','pending','failed','refunded')),
    tags           JSONB,
    created_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_exp_user      ON expenses (user_id);
CREATE INDEX idx_exp_category  ON expenses (category_id);
CREATE INDEX idx_exp_date      ON expenses (expense_date DESC);
CREATE INDEX idx_exp_user_date ON expenses (user_id, expense_date DESC);
CREATE INDEX idx_exp_status    ON expenses (status);
CREATE INDEX idx_exp_tags      ON expenses USING GIN (tags);

-- ── BUDGETS ────────────────────────────────────
CREATE TABLE budgets (
    id              UUID          PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id         UUID          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category_id     UUID          REFERENCES categories(id) ON DELETE SET NULL,
    amount          NUMERIC(12,2) NOT NULL CHECK (amount > 0),
    month           SMALLINT      NOT NULL CHECK (month BETWEEN 1 AND 12),
    year            SMALLINT      NOT NULL CHECK (year BETWEEN 2000 AND 2100),
    alert_threshold SMALLINT      NOT NULL DEFAULT 80
                    CHECK (alert_threshold BETWEEN 1 AND 100),
    created_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_budget_user_cat_month UNIQUE (user_id, category_id, month, year)
);
CREATE INDEX idx_budgets_user   ON budgets (user_id);
CREATE INDEX idx_budgets_period ON budgets (user_id, year, month);

-- ── NOTIFICATIONS ──────────────────────────────
CREATE TABLE notifications (
    id         UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id    UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type       VARCHAR(50) NOT NULL
               CHECK (type IN ('budget_alert','weekly_digest','ai_tip','system','transaction')),
    title      VARCHAR(200) NOT NULL,
    message    TEXT        NOT NULL,
    is_read    BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_notif_user    ON notifications (user_id);
CREATE INDEX idx_notif_unread  ON notifications (user_id, is_read) WHERE is_read = FALSE;

-- ── AUDIT LOG ──────────────────────────────────
CREATE TABLE audit_log (
    id         BIGSERIAL   PRIMARY KEY,
    user_id    UUID        REFERENCES users(id) ON DELETE SET NULL,
    action     VARCHAR(50) NOT NULL,
    table_name VARCHAR(50),
    record_id  UUID,
    old_data   JSONB,
    new_data   JSONB,
    ip_address INET,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_audit_user ON audit_log (user_id);
CREATE INDEX idx_audit_time ON audit_log (created_at DESC);

-- ── TRIGGERS ───────────────────────────────────
CREATE OR REPLACE FUNCTION fn_set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN NEW.updated_at = NOW(); RETURN NEW; END;
$$;

CREATE TRIGGER trg_users_upd    BEFORE UPDATE ON users    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
CREATE TRIGGER trg_cat_upd      BEFORE UPDATE ON categories FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
CREATE TRIGGER trg_exp_upd      BEFORE UPDATE ON expenses  FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
CREATE TRIGGER trg_budgets_upd  BEFORE UPDATE ON budgets   FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

-- ── VIEWS ──────────────────────────────────────
CREATE OR REPLACE VIEW v_monthly_summary AS
SELECT user_id,
       DATE_TRUNC('month', expense_date)::DATE AS month_start,
       EXTRACT(MONTH FROM expense_date)::INT   AS month,
       EXTRACT(YEAR  FROM expense_date)::INT   AS year,
       COUNT(*)                                AS transaction_count,
       SUM(amount)                             AS total_spent,
       AVG(amount)                             AS avg_transaction,
       MAX(amount)                             AS max_transaction
FROM expenses WHERE status = 'completed'
GROUP BY user_id, DATE_TRUNC('month', expense_date);

CREATE OR REPLACE VIEW v_category_summary AS
SELECT e.user_id, e.category_id, c.name AS category_name, c.emoji, c.color,
       EXTRACT(MONTH FROM e.expense_date)::INT AS month,
       EXTRACT(YEAR  FROM e.expense_date)::INT AS year,
       COUNT(*)     AS transaction_count,
       SUM(e.amount) AS total_spent
FROM expenses e JOIN categories c ON c.id = e.category_id
WHERE e.status = 'completed'
GROUP BY e.user_id, e.category_id, c.name, c.emoji, c.color,
         EXTRACT(MONTH FROM e.expense_date), EXTRACT(YEAR FROM e.expense_date);

CREATE OR REPLACE VIEW v_budget_vs_actual AS
SELECT b.user_id, b.month, b.year, b.category_id,
       c.name              AS category_name,
       b.amount            AS budget_amount,
       COALESCE(cs.total_spent, 0) AS actual_spent,
       b.amount - COALESCE(cs.total_spent, 0) AS remaining,
       ROUND((COALESCE(cs.total_spent, 0) / b.amount * 100)::NUMERIC, 2) AS usage_pct,
       b.alert_threshold
FROM budgets b
LEFT JOIN categories c ON c.id = b.category_id
LEFT JOIN v_category_summary cs
  ON cs.user_id = b.user_id AND cs.category_id = b.category_id
 AND cs.month   = b.month   AND cs.year = b.year;

-- ── SYSTEM SEED: Default categories (user_id = NULL) ──
INSERT INTO categories (user_id, name, emoji, color, is_system, sort_order) VALUES
  (NULL,'Food & Dining',    '🍔','#00e5ff',TRUE, 1),
  (NULL,'Transportation',   '🚗','#a855f7',TRUE, 2),
  (NULL,'Rent & Housing',   '🏠','#22c55e',TRUE, 3),
  (NULL,'Shopping',         '🛍','#ec4899',TRUE, 4),
  (NULL,'Entertainment',    '🎬','#f59e0b',TRUE, 5),
  (NULL,'Health & Medical', '💊','#06b6d4',TRUE, 6),
  (NULL,'Education',        '📚','#8b5cf6',TRUE, 7),
  (NULL,'Utilities & Bills','⚡','#f97316',TRUE, 8),
  (NULL,'Travel',           '✈', '#14b8a6',TRUE, 9),
  (NULL,'Technology',       '💻','#6366f1',TRUE,10),
  (NULL,'Subscriptions',    '🔄','#84cc16',TRUE,11),
  (NULL,'Savings',          '🏦','#fbbf24',TRUE,12);

-- ── DEMO USER (password: demo1234 — Argon2id hash) ──
-- Replace hash below with: password_hash('demo1234', PASSWORD_ARGON2ID) from PHP
-- This is a bcrypt fallback; change to Argon2id in production


-- Copy system categories to demo user
INSERT INTO categories (user_id, name, emoji, color, is_system, sort_order)
SELECT (SELECT id FROM users WHERE email='demo@novabudget.ai'),
       name, emoji, color, FALSE, sort_order
FROM categories WHERE user_id IS NULL;

-- Demo budget
INSERT INTO budgets (user_id, category_id, amount, month, year, alert_threshold)
VALUES (
  (SELECT id FROM users WHERE email='demo@novabudget.ai'),
  NULL,
  3000.00,
  EXTRACT(MONTH FROM NOW())::INT,
  EXTRACT(YEAR  FROM NOW())::INT,
  80
);

-- ── MAINTENANCE QUERIES ─────────────────────────
-- Delete expired sessions:
-- DELETE FROM sessions WHERE expires_at < NOW();

-- Get budget usage for a user in current month:
-- SELECT * FROM v_budget_vs_actual WHERE user_id=$1
--   AND month=EXTRACT(MONTH FROM NOW()) AND year=EXTRACT(YEAR FROM NOW());

-- ── GRANT (adjust role name) ────────────────────
-- CREATE ROLE novabudget_app LOGIN PASSWORD 'your_pass';
-- GRANT SELECT,INSERT,UPDATE,DELETE ON ALL TABLES    IN SCHEMA public TO novabudget_app;
-- GRANT USAGE,SELECT               ON ALL SEQUENCES  IN SCHEMA public TO novabudget_app;

COMMIT;
