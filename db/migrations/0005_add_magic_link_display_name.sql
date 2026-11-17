-- 0005 — carry the chosen display name on the sign-in link.
--
-- Accounts are now created when a link is *used*, not when one is requested.
-- The name a new account should get travels on the link until then, so a
-- mistyped or hostile address never leaves a `users` row behind.
--
-- Run once, before deploying the deferred sign-up:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
--     < db/migrations/0005_add_magic_link_display_name.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

ALTER TABLE magic_links
    ADD COLUMN display_name VARCHAR(100) NULL AFTER email;

SELECT COUNT(*) AS magic_links FROM magic_links;
SELECT COUNT(*) AS unverified_users FROM users WHERE verified_at IS NULL;
