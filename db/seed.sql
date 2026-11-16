-- Relay — local development seed data
--
-- LOCAL DEVELOPMENT ONLY. Never load this into the DreamHost database.
-- It is repeatable: it updates the demo rows rather than duplicating them.
--
--   mysql -h HOST -u USER -p DATABASE < db/seed.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

START TRANSACTION;

INSERT INTO users (email, display_name, role, verified_at)
VALUES ('demo@example.test', 'Demo Owner', 'user', NOW())
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

SET @owner_id = (SELECT id FROM users WHERE email = 'demo@example.test');

INSERT INTO projects (
    slug, owner_id, title, pitch, synopsis, brief,
    status, review_status, arrangement, category,
    revenue_state, revenue_model, monthly_cost, currency, cost_description,
    effort_hours_per_month, effort_description,
    user_scope, licence, readiness,
    jurisdiction, language, timezone,
    repo_url, site_url,
    contact_mode,
    synopsis_ai_drafted, brief_ai_drafted,
    published_at, confirmed_at, expires_at
) VALUES (
    'demo-project',
    @owner_id,
    'Demo Project',
    'A small self-hosted tool looking for a co-maintainer.',
    'This is a development-only listing used to exercise the schema, the build step and search. It is not real content and must not be published.',
    '## What this is\n\nA demo listing for local development.\n\n## What a successor inherits\n\nA repository, a domain and a short deployment recipe.\n\n## What is not for transfer\n\nNothing in this row is real.',
    'seeking-co-maintainer',
    'approved',
    'co-maintenance',
    'developer-tools',
    'costs-only', NULL, 12.00, 'GBP', 'Domain and a small VPS.',
    4, 'Security updates and occasional bug fixes.',
    'individuals', 'MIT', 1,
    'GB', 'en', 'Europe/London',
    'https://github.com/example/demo-project', 'https://demo.example.test',
    'via-site',
    1, 1,
    NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 6 MONTH)
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    pitch = VALUES(pitch),
    review_status = VALUES(review_status),
    updated_at = NOW();

SET @project_id = (SELECT id FROM projects WHERE slug = 'demo-project');

INSERT IGNORE INTO project_keywords (project_id, keyword) VALUES
    (@project_id, 'demo'),
    (@project_id, 'self-hosted');

INSERT IGNORE INTO project_skills (project_id, skill) VALUES
    (@project_id, 'php'),
    (@project_id, 'mysql');

INSERT IGNORE INTO project_assets (project_id, asset) VALUES
    (@project_id, 'repo'),
    (@project_id, 'domain'),
    (@project_id, 'hosting');

INSERT IGNORE INTO project_obligations (project_id, obligation) VALUES
    (@project_id, 'Keep the demo domain renewable.');

COMMIT;
