-- 0003 — structured expressions of interest and the owner's decision.
--
-- The applicant's account email is never shown to the owner until the owner
-- accepts; accepting also sends an introduction email to both parties.
--
-- Run once, before deploying the interest relay:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
--     < db/migrations/0003_add_interest_details.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

ALTER TABLE interests
    ADD COLUMN experience TEXT NULL AFTER user_id,
    ADD COLUMN availability VARCHAR(120) NULL AFTER experience,
    ADD COLUMN first_30_days TEXT NULL AFTER availability,
    ADD COLUMN wants TEXT NULL AFTER first_30_days,
    ADD COLUMN decided_by INT UNSIGNED NULL AFTER decided_at,
    ADD COLUMN decision_note VARCHAR(500) NULL AFTER decided_by,
    ADD CONSTRAINT fk_interests_decider
        FOREIGN KEY (decided_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

SELECT status, COUNT(*) AS interests
FROM interests
GROUP BY status;
