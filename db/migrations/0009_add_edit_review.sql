-- 0009 — live edits and the post-hoc re-review window.
--
-- An edit to a live listing now applies immediately. It is marked "Edited"
-- until a reviewer accepts it or the re-review window passes (`edit_review_days`,
-- 14 by default). The reviewer can instead take the listing down for a
-- correction; `projects.review_status` gains the value `taken_down`.
--
-- Existing listings have no edit marker. A new submission has no previous
-- version, so it does not enter the re-review list.
--
-- Run once, before deploying the code that depends on it:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
--     < db/migrations/0009_add_edit_review.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

ALTER TABLE projects
    ADD COLUMN edited_at DATETIME NULL AFTER confirmed_at,
    ADD COLUMN edit_review_status VARCHAR(16) NOT NULL DEFAULT 'accepted' AFTER edited_at,
    ADD KEY idx_projects_edit_review (edit_review_status, edited_at);

SELECT COUNT(*) AS edits_awaiting_review
FROM projects
WHERE edit_review_status = 'pending';

SELECT review_status, COUNT(*) AS projects
FROM projects
GROUP BY review_status;
