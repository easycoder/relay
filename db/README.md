# Database

Relay uses MySQL 8 / MariaDB on the DreamHost account, `utf8mb4`, InnoDB.
`db/schema.sql` is the baseline schema; later changes go in `db/migrations/`
as numbered files.

## Connection details

| Setting | Value |
|---|---|
| Host | `mysql.eclecity.net` (confirmed reachable) |
| Database | `eclecity_relay` |
| Username | Usually the same as the database name — check **Manage Databases** in the DreamHost panel |
| Password | Set when the database was created; not stored in this repository |

DreamHost creates the MySQL user alongside the database and lists the exact
hostname and username on the **Manage Databases** page. The password lives in
the site's config file (below), never in git.

## Load the schema

Over SSH on the DreamHost server:

```sh
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay < db/schema.sql
```

Or in phpMyAdmin: select `eclecity_relay`, open **Import**, choose
`db/schema.sql`, and run it.

The schema file is safe to re-run: it uses `CREATE TABLE IF NOT EXISTS` and
only inserts missing categories.

## Migrations

Numbered migrations live in `db/migrations/`. Apply each new file once, in
order, before deploying code that depends on it. If `0001` is already applied,
run only the new file:

```sh
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0001_add_review_status.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0002_add_project_revisions.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0003_add_interest_details.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0004_add_review_note_and_resubmissions.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0005_add_magic_link_display_name.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0006_add_expiry_reminders.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0007_add_stages_endorsements_and_subject_audit.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0008_add_review_diffs.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0009_add_edit_review.sql
```

`0001` adds `projects.review_status` (`pending`, `approved`, `rejected`).
Existing rows are approved; new rows default to pending.

`0002` adds `project_revisions`, which holds an owner's proposed changes to an
approved listing while the live page stays published.

`0003` adds the structured answers and owner-decision fields to `interests`.

`0004` adds `projects.review_note` (the reviewer's reason, shown to the owner
and cleared on resubmission) and `project_resubmissions`, one row per retry,
which enforces the five-in-thirty-days limit on resubmitting a rejected
listing.

`0005` adds `magic_links.display_name`, so a sign-in link carries the name a
new account should get. Accounts are created when a link is used, not when one
is requested.

`0006` adds `project_expiry_reminders`, one row per reminder sent, so a cron
run cannot send the same one twice. Run `php scripts/expiry-reminders.php` on a
schedule (see the README).

`0007` adds `projects.stage` (idea/prototype/live), `approval_source` and
`auto_approval_blocked`, the `project_likes` table (reasoned likes), and
`audit_log.subject_user_id`, so submission and rejection limits count from the
audit trail even after a listing is deleted. It also gives existing pending
submissions a six-month expiry.

`0008` adds `projects.review_diff` and `project_revisions.diff`, the
submission-time diff shown beside the full text on `/review/`.

`0009` adds `projects.edited_at` and `projects.edit_review_status` for the
immediate-edit re-review window. `review_status` can now be `taken_down`.

## Load the first listing

The first real listing is Relay itself. Unlike `db/seed.sql`, this is
production content and is safe to re-run:

```sh
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/content/relay-self.sql
```

In phpMyAdmin, click **eclecity_relay** in the left sidebar first, then
**Import**. The file also contains `USE eclecity_relay;`, so it will select the
database even if you import from the server-level page.

Verify it landed:

```sql
USE eclecity_relay;
SELECT id, slug, title, status FROM projects WHERE slug = 'relay';
SELECT id, email, display_name FROM users WHERE email = 'hello@relay.eclecity.net';
```

## Build the catalogue

On the server, after the schema and the first listing are loaded:

```sh
cd /home/eclecity/relay.eclecity.net
php scripts/build.php
```

Or from the laptop:

```sh
./deploy.sh --build
```

The build writes `public/home.html`, `public/projects/`, `public/learn/`,
`public/about/`, `public/projects.json`, `public/sitemap.xml` and
`public/robots.txt`. Those files are generated on the server and excluded from
code deploys.

## Review and community approval

Only approved listings are published. A listing starts `pending` and is visible
in the waiting list at `/find/?view=pending` and on `/waiting/?slug=…` (both
`noindex`). A pending listing can be approved by a threshold of reasoned
likes (`like_threshold`, default 5) unless a reviewer has rejected it.
Reviewers with the `admin` or `moderator` role use `/review/`; the CLI remains:

```sh
php scripts/review.php list
php scripts/review.php approve <slug>
php scripts/review.php reject <slug> --note "reason"
php scripts/review.php veto <slug> --note "reason"
php scripts/review.php accept-edit <slug>
php scripts/review.php takedown <slug> --note "reason"
```

A like requires a reason, one per account, and an account older than
`like_min_account_age_days`; one IP is capped per day. A rejection requires a
reason, blocks automatic approval, and clears the likes on resubmission.
A veto reverses a community approval and does not count as a submission strike.

Like counts are the number of `project_likes` rows. `/find/` and the cards read
them live; the build writes them into `projects.json`.

Edits to a live listing apply immediately. The listing is marked Edited and
joins the Recently edited list for `edit_review_days` (default 14) days; if
nobody acts it is accepted and the marker disappears. A reviewer can accept
the edit (`accept-edit`) or take it down (`takedown`). A take-down sets
`review_status='taken_down'`, emails the owner and removes the page on the
next build; a correction returns to initial review. Legacy pending revisions
remain in an Earlier revisions section.

Then rebuild with `php scripts/build.php`. Each decision writes an `audit_log`
row; the web queue sets `published_at` on first approval. Schedule the build
too, so approvals and expiries do not wait for a button press.

## Check the connection

On the server, with the config file in place:

```sh
cd /home/eclecity/relay.eclecity.net
php scripts/db-check.php
```

It reports the config path, server version, charset/collation, table count,
engine and table collations, and exits non-zero if anything is wrong.

## Where the config file goes

Follow the estate convention: a JSON file named after the host, one level
above the repository. For the current server directory that is:

```
/home/eclecity/relay.eclecity.net/            repository (current web root)
/home/eclecity/relay.eclecity.net/public/     public tree
/home/eclecity/relay.eclecity.net.txt         config (above both)
```

The web root is currently the repository root; `index.php` there serves
`public/index.php`, and `.htaccess` denies source paths. The preferred layout
points DreamHost's **Web Directory** at `public/`; use that once the vhost
serves it. The config file sits one level above the repository, exactly like
`doclets.eclecity.net.txt`, not inside it.

Create it with the flat keys the other sites use:

```json
{
  "site_url": "https://relay.eclecity.net",
  "db_host": "mysql.eclecity.net",
  "db_name": "eclecity_relay",
  "db_user": "eclecity_relay",
  "db_pass": "REPLACE_WITH_THE_DATABASE_PASSWORD",
  "db_charset": "utf8mb4"
}
```

```sh
chmod 600 /home/eclecity/relay.eclecity.net.txt
```

The `.txt` extension keeps it inert, but the protection is its location: it is
outside the web root. Unlike the MQTT credential files, this one must never be
served by a `credentials.php` endpoint — it contains the database password.

`bootstrap.php` searches, in order:

1. `RELAY_CONFIG`
2. `../<directory-name>.txt` — the deployment file above
3. `./config.php`, then `./config.json` — local development
4. `../relay-config/config.php`, then `../config.php` — earlier layouts

So `php scripts/db-check.php` works with no extra settings. You can also be
explicit:

```sh
RELAY_CONFIG=/home/eclecity/relay.eclecity.net.txt php scripts/db-check.php
```

If the web process runs as a different user and cannot read the file, use
`640` with the appropriate group instead of `600`.

## Local development

Load `db/schema.sql` into a local MySQL/MariaDB, then optionally load
`db/seed.sql`:

```sh
mysql -u root relay_dev < db/schema.sql
mysql -u root relay_dev < db/seed.sql
```

`db/seed.sql` is **local development only**. It creates demo rows and must
never be run against the DreamHost database.
