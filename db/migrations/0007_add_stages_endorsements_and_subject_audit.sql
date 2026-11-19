-- 0007 — stages, community endorsements and attributed audit rows.
--
-- Relay now lists three kinds of thing: an idea, a prototype and a live
-- project. `stage` is separate from `status`, which says what the owner is
-- looking for. The public catalogue gains a pending view, and a pending
-- listing can be approved by a threshold of reasoned endorsements unless a
-- reviewer has already rejected it.
--
-- `audit_log.subject_user_id` attributes project events to the owner even when
-- a moderator acted, and even after the listing row is deleted. The submission
-- and rejection limits count from the audit trail for that reason.
--
-- Run once, before deploying the code that depends on it:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
--     < db/migrations/0007_add_stages_endorsements_and_subject_audit.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

ALTER TABLE projects
    ADD COLUMN stage VARCHAR(16) NOT NULL DEFAULT 'live' AFTER status,
    ADD COLUMN approval_source VARCHAR(16) NULL AFTER review_status,
    ADD COLUMN auto_approval_blocked TINYINT(1) NOT NULL DEFAULT 0 AFTER approval_source,
    ADD KEY idx_projects_stage (stage);

-- Existing listings predate the stage axis and are live. Existing approvals
-- were made by a person; an existing rejection must not be overridden by a
-- later run of endorsements.
UPDATE projects SET approval_source = 'admin'
WHERE review_status = 'approved' AND approval_source IS NULL;

UPDATE projects SET auto_approval_blocked = 1
WHERE review_status = 'rejected';

-- A pending submission now has the same six-month clock as a published one.
-- Existing pending rows predate that and would otherwise sit in the queue for
-- ever.
UPDATE projects
SET expires_at = DATE_ADD(created_at, INTERVAL 6 MONTH)
WHERE review_status = 'pending' AND expires_at IS NULL;

CREATE TABLE IF NOT EXISTS project_likes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    -- The endorsement is reasoned: a click alone is too cheap to be a signal.
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

ALTER TABLE audit_log
    ADD COLUMN subject_user_id INT UNSIGNED NULL AFTER actor_user_id,
    ADD KEY idx_audit_subject (subject_user_id),
    ADD CONSTRAINT fk_audit_subject
        FOREIGN KEY (subject_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Attribute events for listings that still exist. Events for deleted listings
-- cannot be recovered; new events carry the id from the moment they are written.
UPDATE audit_log a
JOIN projects p ON p.id = a.entity_id
SET a.subject_user_id = p.owner_id
WHERE a.entity_type = 'project'
  AND a.subject_user_id IS NULL;

SELECT stage, COUNT(*) AS projects
FROM projects
GROUP BY stage;

SELECT review_status, approval_source, COUNT(*) AS projects
FROM projects
GROUP BY review_status, approval_source;

SELECT COUNT(*) AS endorsements FROM project_likes;
