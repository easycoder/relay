-- Relay — baseline schema
--
-- Target: MySQL 8.0+ or MariaDB 10.4+, InnoDB, utf8mb4.
-- Load with:
--   mysql -h HOST -u USER -p DATABASE < db/schema.sql
--
-- This file is the current full schema. Later changes ship as numbered
-- migrations in db/migrations/ so an existing database can be upgraded
-- without being rebuilt.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- Reference data
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS categories (
    slug        VARCHAR(64)      NOT NULL,
    name        VARCHAR(100)     NOT NULL,
    description VARCHAR(255)     NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (slug, name, sort_order) VALUES
    ('web-apps-saas',          'Web apps & SaaS',          10),
    ('developer-tools',        'Developer tools & libraries', 20),
    ('home-automation-iot',    'Home automation & IoT',    30),
    ('data-ai',                'Data & AI',                40),
    ('infrastructure-operations', 'Infrastructure & operations', 50),
    ('desktop-mobile',         'Desktop & mobile',         60),
    ('games-interactive',      'Games & interactive',      70),
    ('education',              'Education',                80),
    ('community-non-profit',   'Community & non-profit',   90),
    ('content-publishing',     'Content & publishing',    100),
    ('business-productivity',  'Business & productivity', 110),
    ('hardware-embedded',      'Hardware & embedded',     120),
    ('science-research',       'Science & research',      130),
    ('legacy-migration',       'Legacy & migration',      140);

-- ---------------------------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    email         VARCHAR(190)   NOT NULL,
    display_name  VARCHAR(100)   NOT NULL,
    role          VARCHAR(16)    NOT NULL DEFAULT 'user', -- user | moderator | admin
    github_id     BIGINT UNSIGNED NULL,
    github_login  VARCHAR(100)   NULL,
    verified_at   DATETIME       NULL,
    last_login_at DATETIME       NULL,
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_github_id (github_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Listings
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS projects (
    id                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    slug                   VARCHAR(190)  NOT NULL,
    owner_id               INT UNSIGNED  NOT NULL,

    title                  VARCHAR(200)  NOT NULL,
    pitch                  VARCHAR(300)  NOT NULL,
    synopsis               TEXT          NOT NULL,
    brief                  MEDIUMTEXT    NULL,

    -- draft | seeking-successor | seeking-co-maintainer | seeking-buyer |
    -- temporary-steward | seeking-collaborator | seeking-feedback |
    -- seeking-testers | dormant | adopted | archived
    status                 VARCHAR(32)   NOT NULL DEFAULT 'draft',
    -- idea | prototype | live. Separate from status, which says what the owner
    -- is looking for rather than how far the work has got.
    stage                  VARCHAR(16)   NOT NULL DEFAULT 'live',
    -- pending | approved | rejected | taken_down. Only approved listings are
    -- built into the public catalogue; new submissions start pending.
    review_status          VARCHAR(16)   NOT NULL DEFAULT 'pending',
    -- admin | community. How the current approval was reached.
    approval_source        VARCHAR(16)   NULL,
    -- Set by a reviewer's rejection. A later run of endorsements must not
    -- override that decision; a person has to approve the listing.
    auto_approval_blocked  TINYINT(1)    NOT NULL DEFAULT 0,
    -- The diff generated when an owner edits a listing, against the version
    -- it replaced. Null on a new submission or when an edit was accepted.
    review_diff            JSON          NULL,
    -- The reviewer's reason for a rejection, shown to the owner and cleared
    -- when the listing is resubmitted.
    review_note            VARCHAR(500)  NULL,
    -- gift | sale | revenue-share | sponsorship | co-maintenance | other
    arrangement            VARCHAR(32)   NULL,
    category               VARCHAR(64)   NOT NULL,

    -- none | potential | proven | costs-only
    revenue_state          VARCHAR(24)   NULL,
    revenue_model          VARCHAR(120)  NULL,
    monthly_cost           DECIMAL(10,2) NULL,
    currency               CHAR(3)       NULL,
    cost_description       VARCHAR(500)  NULL,

    effort_hours_per_month SMALLINT UNSIGNED NULL,
    effort_description     VARCHAR(500)  NULL,

    -- none | individuals | businesses | public-sector | mixed
    user_scope             VARCHAR(24)   NULL,
    licence                VARCHAR(64)   NULL,
    readiness              TINYINT UNSIGNED NOT NULL DEFAULT 0, -- L0..L4

    jurisdiction           VARCHAR(64)   NULL,
    language               VARCHAR(16)   NULL,
    timezone               VARCHAR(64)   NULL,

    repo_url               VARCHAR(255)  NULL,
    site_url               VARCHAR(255)  NULL,
    docs_url               VARCHAR(255)  NULL,
    demo_url               VARCHAR(255)  NULL,

    -- via-site | public
    contact_mode           VARCHAR(16)   NOT NULL DEFAULT 'via-site',
    public_contact         VARCHAR(255)  NULL,

    -- AI disclosure: mark fields that were drafted with AI and must be
    -- checked by the owner; confirmed_at is the owner's confirmation.
    synopsis_ai_drafted    TINYINT(1)    NOT NULL DEFAULT 0,
    brief_ai_drafted       TINYINT(1)    NOT NULL DEFAULT 0,

    -- Facts checked by the build step, so pages do not depend on a live
    -- GitHub or site request. Never present these as a quality score.
    repo_last_commit_at    DATETIME      NULL,
    repo_open_issues       INT UNSIGNED  NULL,
    licence_detected       VARCHAR(64)   NULL,
    site_reachable_at      DATETIME      NULL,
    brief_completeness     TINYINT UNSIGNED NULL, -- 0..100
    facts_checked_at       DATETIME      NULL,

    published_at           DATETIME      NULL,
    confirmed_at           DATETIME      NULL,
    -- When a live listing's content was last edited. The public "Edited"
    -- marker shows only while the edit is awaiting re-review.
    edited_at              DATETIME      NULL,
    -- accepted | pending | taken_down. An edit is accepted explicitly, or
    -- automatically after the re-review window.
    edit_review_status     VARCHAR(16)   NOT NULL DEFAULT 'accepted',
    expires_at             DATETIME      NULL,
    created_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_slug (slug),
    KEY idx_projects_owner (owner_id),
    KEY idx_projects_status (status),
    KEY idx_projects_stage (stage),
    KEY idx_projects_review_status (review_status),
    KEY idx_projects_edit_review (edit_review_status, edited_at),
    KEY idx_projects_category (category),
    KEY idx_projects_readiness (readiness),
    KEY idx_projects_expires (expires_at),
    FULLTEXT KEY ft_projects (title, pitch, synopsis, brief),
    CONSTRAINT fk_projects_owner
        FOREIGN KEY (owner_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_projects_category
        FOREIGN KEY (category) REFERENCES categories (slug)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_keywords (
    project_id INT UNSIGNED NOT NULL,
    keyword    VARCHAR(100) NOT NULL,
    PRIMARY KEY (project_id, keyword),
    KEY idx_project_keywords_keyword (keyword),
    CONSTRAINT fk_project_keywords_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_skills (
    project_id INT UNSIGNED NOT NULL,
    skill      VARCHAR(100) NOT NULL,
    PRIMARY KEY (project_id, skill),
    KEY idx_project_skills_skill (skill),
    CONSTRAINT fk_project_skills_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_assets (
    project_id INT UNSIGNED NOT NULL,
    -- repo | domain | hosting | data | broker | mail | trademark |
    -- contracts | documentation
    asset      VARCHAR(32)  NOT NULL,
    PRIMARY KEY (project_id, asset),
    KEY idx_project_assets_asset (asset),
    CONSTRAINT fk_project_assets_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_obligations (
    project_id INT UNSIGNED NOT NULL,
    obligation VARCHAR(190) NOT NULL,
    PRIMARY KEY (project_id, obligation),
    CONSTRAINT fk_project_obligations_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Endorsements on a pending listing. A threshold of reasoned endorsements can
-- approve a listing unless a reviewer has already rejected it. One row per
-- account per listing; the reason is shown on the waiting page and to the
-- reviewer, and is the record used to judge abuse.
CREATE TABLE IF NOT EXISTS project_likes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    reason     VARCHAR(500) NOT NULL,
    ip_hash    CHAR(64)     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_likes_project_user (project_id, user_id),
    KEY idx_project_likes_user (user_id),
    KEY idx_project_likes_ip_created (ip_hash, created_at),
    KEY idx_project_likes_created (created_at),
    CONSTRAINT fk_project_likes_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_likes_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Proposed revisions to an approved listing
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS project_revisions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id    INT UNSIGNED NOT NULL,
    created_by    INT UNSIGNED NOT NULL,
    payload       JSON         NOT NULL,
    -- The diff against the live listing, generated at submission time.
    diff          JSON         NULL,
    -- pending | approved | rejected
    review_status VARCHAR(16)  NOT NULL DEFAULT 'pending',
    note          VARCHAR(500) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   DATETIME     NULL,
    reviewed_by   INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_revisions_project (project_id),
    KEY idx_revisions_status (review_status),
    CONSTRAINT fk_revisions_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_revisions_creator
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_revisions_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per resubmission of a listing that is not live. The retry rate is
-- limited by counting these, so a bot cannot resubmit endlessly.
CREATE TABLE IF NOT EXISTS project_resubmissions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resubmissions_project_created (project_id, created_at),
    KEY idx_resubmissions_user (user_id),
    CONSTRAINT fk_resubmissions_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_resubmissions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per expiry reminder sent for a listing. The unique key stops a cron
-- run sending the same reminder twice; a reconfirmation moves expires_at, so
-- the listing becomes eligible again next time round.
CREATE TABLE IF NOT EXISTS project_expiry_reminders (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    expires_at DATETIME     NOT NULL, -- the expiry this reminder was about
    sent_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expiry_reminder (project_id, expires_at),
    KEY idx_expiry_reminders_sent (sent_at),
    CONSTRAINT fk_expiry_reminder_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Expressions of interest
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS interests (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id    INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    experience    TEXT         NULL,
    availability  VARCHAR(120) NULL,
    first_30_days TEXT         NULL,
    wants         TEXT         NULL,
    message       TEXT         NOT NULL,
    -- new | accepted | declined | withdrawn
    status        VARCHAR(16)  NOT NULL DEFAULT 'new',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at    DATETIME     NULL,
    decided_by    INT UNSIGNED NULL,
    decision_note VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_interests_project_user (project_id, user_id),
    KEY idx_interests_status (status),
    KEY idx_interests_user (user_id),
    CONSTRAINT fk_interests_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_interests_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_interests_decider
        FOREIGN KEY (decided_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Phase 2: private project rooms
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS rooms (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  INT UNSIGNED NOT NULL,
    opened_by   INT UNSIGNED NOT NULL,
    -- open | closed | archived
    status      VARCHAR(16)  NOT NULL DEFAULT 'open',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at   DATETIME     NULL,
    -- Retention: close + 90 days, then delete + 90 days (set by the app).
    delete_after DATETIME    NULL,
    PRIMARY KEY (id),
    KEY idx_rooms_project (project_id),
    KEY idx_rooms_status (status),
    KEY idx_rooms_delete_after (delete_after),
    CONSTRAINT fk_rooms_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rooms_opened_by
        FOREIGN KEY (opened_by) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_members (
    room_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    -- owner | adopter
    role       VARCHAR(16)  NOT NULL DEFAULT 'adopter',
    joined_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_at DATETIME     NULL,
    PRIMARY KEY (room_id, user_id),
    KEY idx_room_members_user (user_id),
    CONSTRAINT fk_room_members_room
        FOREIGN KEY (room_id) REFERENCES rooms (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_room_members_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id    INT UNSIGNED    NOT NULL,
    sender_id  INT UNSIGNED    NOT NULL,
    body       MEDIUMTEXT      NOT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at  DATETIME        NULL,
    PRIMARY KEY (id),
    KEY idx_messages_room_created (room_id, created_at),
    KEY idx_messages_sender (sender_id),
    CONSTRAINT fk_messages_room
        FOREIGN KEY (room_id) REFERENCES rooms (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Authentication
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS magic_links (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(190) NOT NULL,
    -- The name a new account should get, carried until the link is used: the
    -- users row is created then, never when a link is requested.
    display_name    VARCHAR(100) NULL,
    token_hash      CHAR(64)     NOT NULL, -- SHA-256 of the token; never the token itself
    -- login | verify | edit-listing
    purpose         VARCHAR(32)  NOT NULL DEFAULT 'login',
    project_id      INT UNSIGNED NULL,
    expires_at      DATETIME     NOT NULL,
    used_at         DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    requested_ip_hash CHAR(64)   NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_magic_links_token (token_hash),
    KEY idx_magic_links_email (email),
    KEY idx_magic_links_expires (expires_at),
    CONSTRAINT fk_magic_links_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    token_hash   CHAR(64)     NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME     NOT NULL,
    last_seen_at DATETIME     NULL,
    ip_hash      CHAR(64)     NULL,
    user_agent   VARCHAR(255) NULL,
    PRIMARY KEY (token_hash),
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_expires (expires_at),
    CONSTRAINT fk_sessions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Trust, safety and audit
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS reports (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_user_id INT UNSIGNED NULL,
    project_id       INT UNSIGNED NULL,
    message_id       BIGINT UNSIGNED NULL,
    reason           VARCHAR(64)  NOT NULL,
    details          TEXT         NULL,
    -- open | resolved | dismissed
    status           VARCHAR(16)  NOT NULL DEFAULT 'open',
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at      DATETIME     NULL,
    resolved_by      INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_reports_status (status),
    KEY idx_reports_project (project_id),
    KEY idx_reports_message (message_id),
    CONSTRAINT fk_reports_reporter
        FOREIGN KEY (reporter_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reports_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reports_message
        FOREIGN KEY (message_id) REFERENCES messages (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reports_resolver
        FOREIGN KEY (resolved_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id   INT UNSIGNED    NULL,
    -- The person a project event is about (normally the owner), which is not
    -- always the person who acted. Limits count from here, so deleting a
    -- listing cannot erase its record.
    subject_user_id INT UNSIGNED    NULL,
    action          VARCHAR(64)     NOT NULL,
    entity_type     VARCHAR(32)     NOT NULL,
    entity_id       BIGINT UNSIGNED NULL,
    detail          TEXT            NULL,
    ip_hash         CHAR(64)        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_actor (actor_user_id),
    KEY idx_audit_subject (subject_user_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_actor
        FOREIGN KEY (actor_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_audit_subject
        FOREIGN KEY (subject_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
