-- 0008 — store the reviewer's diff at submission time.
--
-- An owner's edit can be one word or a complete rewrite. The review page shows
-- a Changes tab beside the full text, and the safest record of the change is
-- the diff computed when the edit was saved, not a comparison made later
-- against a listing that may have moved on.
--
-- `projects.review_diff` holds the diff for an edited pending or rejected
-- listing. `project_revisions.diff` holds it for a live listing's revision.
-- Both are null for a new submission, which has no previous version.
--
-- Run once, before deploying the code that depends on it:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
--     < db/migrations/0008_add_review_diffs.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

ALTER TABLE projects
    ADD COLUMN review_diff JSON NULL AFTER auto_approval_blocked;

ALTER TABLE project_revisions
    ADD COLUMN diff JSON NULL AFTER payload;

SELECT COUNT(*) AS projects_with_review_diff
FROM projects
WHERE review_diff IS NOT NULL;

SELECT COUNT(*) AS revisions_with_diff
FROM project_revisions
WHERE diff IS NOT NULL;
