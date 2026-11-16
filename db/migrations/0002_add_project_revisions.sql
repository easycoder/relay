-- 0002 — proposed revisions to an approved listing.
--
-- The live projects row stays published while an owner edits it. The edit is
-- stored here as a JSON payload and is applied only when a reviewer approves
-- it, so a public page never changes without human review.
--
-- Run once, before deploying the edit flow:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
--     < db/migrations/0002_add_project_revisions.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

CREATE TABLE IF NOT EXISTS project_revisions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id    INT UNSIGNED NOT NULL,
    created_by    INT UNSIGNED NOT NULL,
    payload       JSON         NOT NULL,
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

SELECT review_status, COUNT(*) AS revisions
FROM project_revisions
GROUP BY review_status;
