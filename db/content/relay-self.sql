-- Relay's first listing: the project itself.
--
-- Real content, not local test data. Load once into the DreamHost database:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay < db/content/relay-self.sql
--
-- In phpMyAdmin, select eclecity_relay in the left sidebar before importing.
-- The USE below makes the file self-contained; remove it if you are importing
-- into a differently named database.
--
-- Safe to re-run: it updates the same user and listing, and replaces the
-- keyword, skill, asset and obligation rows.
--
-- The owner account is a role address. Change it to the maintainer's real
-- address before enabling magic-link login.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

START TRANSACTION;

INSERT INTO users (email, display_name, role, verified_at)
VALUES ('hello@relay.eclecity.net', 'Relay', 'admin', NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    role = VALUES(role),
    verified_at = COALESCE(verified_at, VALUES(verified_at));

SET @owner_id = (SELECT id FROM users WHERE email = 'hello@relay.eclecity.net');

INSERT INTO projects (
    slug, owner_id, title, pitch, synopsis, brief,
    status, review_status, arrangement, category,
    revenue_state, revenue_model, monthly_cost, currency, cost_description,
    effort_hours_per_month, effort_description,
    user_scope, licence, readiness,
    jurisdiction, language, timezone,
    repo_url, site_url, docs_url, demo_url,
    contact_mode, public_contact,
    synopsis_ai_drafted, brief_ai_drafted,
    repo_last_commit_at, repo_open_issues, licence_detected,
    site_reachable_at, brief_completeness, facts_checked_at,
    published_at, confirmed_at, expires_at
) VALUES (
    'relay',
    @owner_id,
    'Relay',
    'A free, open-source registry that helps a project find its next maintainer.',
    'Relay is a free, open-source registry and introduction service for projects that need a next maintainer. An owner describes what the project is, what it costs to run and what a successor would inherit; an adopter — increasingly someone working with AI agents rather than writing every line by hand — can assess the project quickly and start a conversation. The site is a public catalogue, a search index and a consent-based introduction relay. It is not a broker, an escrow service or a party to the transfer. This is the first listing, and it is Relay itself. The name, domain, database schema, deployment and design are in place. The first catalogue build is in place; the submission flow and the interest relay are next. The project is deliberately small, self-hostable and documented well enough for someone else to run. It is looking for a co-maintainer who can help with the product, the code or the moderation — not a buyer, and not a rescue. The aim is to prove the standard on the project itself before asking anyone else to trust it with their own succession.',
    '## What this is

Relay is a registry and introduction service for projects that need a next maintainer. It is free, open source and self-hostable. The unit of value is the adoption-ready package: a project described clearly enough for a capable stranger to decide whether to take it on.

This is the first listing: the project itself.

## The state of play

- The name is Relay; the site is [relay.eclecity.net](https://relay.eclecity.net).
- The MySQL schema is imported on DreamHost: 15 tables, InnoDB, utf8mb4.
- The deployment is PHP 8 on DreamHost, with rsync deployment and the config kept above the web root.
- The first catalogue build and this listing page are in place; the source tree is protected.
- Submission, search and the expression-of-interest relay are the next milestone.
- The repository is [easycoder/relay](https://github.com/easycoder/relay). The proposal is in PROPOSAL.md.

## What a co-maintainer would do

- Help build the first catalogue: the build script, listing pages, search export and submission flow.
- Share moderation and the safety process as listings arrive.
- Review the privacy notice, the terms and the continuity plan before launch.
- Bring a view of what a good handover looks like; the standard matters more than the codebase.

## What is not for transfer

- No customer data or credentials are part of this listing.
- The domain and hosting remain with the current owner until a written handover.
- The project is not for sale and has no revenue to share.

## What it costs

Hosting and mail are already in place. The incremental cash cost is small. Curation and moderation are the real cost, and they are why a co-maintainer is needed rather than a buyer.

## How to verify this brief

- Repository: [easycoder/relay](https://github.com/easycoder/relay)
- Proposal: [PROPOSAL.md](https://github.com/easycoder/relay/blob/main/PROPOSAL.md)
- Database and deployment notes: db/README.md in the repository.',
    'seeking-co-maintainer',
    'approved',
    'co-maintenance',
    'community-non-profit',
    'costs-only',
    NULL,
    NULL,
    NULL,
    'Hosting and mail are already in place. The incremental cash cost is small; curation and moderation are the real cost.',
    4,
    'Curation, moderation, code review and occasional deployment.',
    'individuals',
    'Apache-2.0',
    1,
    'GB',
    'en',
    'Europe/London',
    'https://github.com/easycoder/relay',
    'https://relay.eclecity.net',
    NULL,
    NULL,
    'via-site',
    NULL,
    1,
    1,
    NULL,
    NULL,
    'Apache-2.0',
    NOW(),
    NULL,
    NOW(),
    NOW(),
    NULL,
    DATE_ADD(NOW(), INTERVAL 6 MONTH)
)
ON DUPLICATE KEY UPDATE
    owner_id = VALUES(owner_id),
    title = VALUES(title),
    pitch = VALUES(pitch),
    synopsis = VALUES(synopsis),
    brief = VALUES(brief),
    status = VALUES(status),
    review_status = VALUES(review_status),
    arrangement = VALUES(arrangement),
    category = VALUES(category),
    revenue_state = VALUES(revenue_state),
    revenue_model = VALUES(revenue_model),
    monthly_cost = VALUES(monthly_cost),
    currency = VALUES(currency),
    cost_description = VALUES(cost_description),
    effort_hours_per_month = VALUES(effort_hours_per_month),
    effort_description = VALUES(effort_description),
    user_scope = VALUES(user_scope),
    licence = VALUES(licence),
    readiness = VALUES(readiness),
    jurisdiction = VALUES(jurisdiction),
    language = VALUES(language),
    timezone = VALUES(timezone),
    repo_url = VALUES(repo_url),
    site_url = VALUES(site_url),
    docs_url = VALUES(docs_url),
    demo_url = VALUES(demo_url),
    contact_mode = VALUES(contact_mode),
    public_contact = VALUES(public_contact),
    synopsis_ai_drafted = VALUES(synopsis_ai_drafted),
    brief_ai_drafted = VALUES(brief_ai_drafted),
    repo_last_commit_at = VALUES(repo_last_commit_at),
    repo_open_issues = VALUES(repo_open_issues),
    licence_detected = VALUES(licence_detected),
    site_reachable_at = VALUES(site_reachable_at),
    brief_completeness = VALUES(brief_completeness),
    facts_checked_at = VALUES(facts_checked_at),
    expires_at = VALUES(expires_at);

SET @project_id = (SELECT id FROM projects WHERE slug = 'relay');

DELETE FROM project_keywords WHERE project_id = @project_id;
DELETE FROM project_skills WHERE project_id = @project_id;
DELETE FROM project_assets WHERE project_id = @project_id;
DELETE FROM project_obligations WHERE project_id = @project_id;

INSERT INTO project_keywords (project_id, keyword) VALUES
    (@project_id, 'continuity'),
    (@project_id, 'handover'),
    (@project_id, 'maintainer'),
    (@project_id, 'open-source'),
    (@project_id, 'registry'),
    (@project_id, 'succession');

INSERT INTO project_skills (project_id, skill) VALUES
    (@project_id, 'allspeak'),
    (@project_id, 'css'),
    (@project_id, 'documentation'),
    (@project_id, 'html'),
    (@project_id, 'moderation'),
    (@project_id, 'mysql'),
    (@project_id, 'php');

INSERT INTO project_assets (project_id, asset) VALUES
    (@project_id, 'data'),
    (@project_id, 'documentation'),
    (@project_id, 'domain'),
    (@project_id, 'hosting'),
    (@project_id, 'mail'),
    (@project_id, 'repo');

INSERT INTO project_obligations (project_id, obligation) VALUES
    (@project_id, 'Keep the catalogue free to use and free of paid placement.'),
    (@project_id, 'Moderate reports and remove listings that contain secrets or personal data.'),
    (@project_id, 'Keep the privacy notice and data retention current.'),
    (@project_id, 'Maintain deployment, database backups and the content export.'),
    (@project_id, 'Publish a continuity plan for the site and name a backup maintainer.');

COMMIT;

-- The import should show one row here. If it shows none, the listing did not
-- land and the build will have nothing to publish.
SELECT id, slug, title, status, review_status, published_at, expires_at
FROM projects
WHERE slug = 'relay';
