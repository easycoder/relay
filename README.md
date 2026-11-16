# Relay

Connecting projects that need a next maintainer with the people who can take them on.

Relay is a free, open-source registry and introduction service for continuity. A project owner describes what they have, what it costs and what a successor would inherit; an adopter — increasingly someone working with AI agents rather than writing every line by hand — can assess the project quickly and start a conversation. The site is the missing human layer above a project's own continuity plan and handover mechanism.

The proposal, including the product shape, the Adoption Brief standard and the technical approach, is in [PROPOSAL.md](PROPOSAL.md).

## Status

Phase 0: direction agreed, seed content and the first build in progress. The site will live at <https://relay.eclecity.net>.

## Development

The database is MySQL/MariaDB (`utf8mb4`, InnoDB). The schema, connection
details and import steps are in [db/README.md](db/README.md).

Copy `config.example.php` to `config.php` for local development and fill in
the database password; both are gitignored. In deployment the config is the
host-named JSON file `relay.eclecity.net.txt`, one level above the web root —
see [db/README.md](db/README.md).

## The catalogue

The first listing is Relay itself, in `db/content/relay-self.sql`. Load it
once (via phpMyAdmin's Import, or over SSH). In phpMyAdmin, select
`eclecity_relay` in the left sidebar first; the file also contains
`USE eclecity_relay;`, so it selects the database itself.

```sh
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/content/relay-self.sql
```

**Deploying the file only uploads it; it does not execute it.** The import is
a separate step. The file ends with a `SELECT` that shows the inserted row.

Then deploy and build in one step:

```sh
./deploy.sh --build
```

`scripts/build.php` reads the published listings and writes
`public/home.html`, `public/projects/`, `public/learn/`, `public/projects.json`,
`public/sitemap.xml` and `public/robots.txt`. Those files are generated on the
server and excluded from deploys, so `--delete` cannot remove them. Run the
build again after any content, template or CSS change that affects the pages
(CSS is static and does not need a build).

The first listing stays marked **Draft** until you confirm the AI-drafted
synopsis and brief. Once you have read them:

```sql
UPDATE projects SET confirmed_at = NOW() WHERE slug = 'relay';
```

Then rebuild.

## Sign-in and submission

Sign-in uses a single-use email link; there are no passwords. The form asks
for a name and an email address. For a new account the name is used as the
display name; if the address already exists, the name is ignored and the
existing one is kept. `/login/verify/` creates a session. The role account
`hello@relay.eclecity.net` is already an `admin`. `/submit/` requires sign-in
and writes a `pending` listing; `/dashboard/` shows an owner their listings,
their review status and an Edit link.

If PHP `mail()` is unavailable, the maintainer can print a link on the server.
For a new address, add a name:

```sh
php scripts/login-link.php hello@relay.eclecity.net
php scripts/login-link.php info@allspeak.ai "Info"
```

To change an existing display name:

```sql
UPDATE users SET display_name = '…' WHERE email = '…';
```

Set `mail_from` in the server config to the sender address (default
`no-reply@relay.eclecity.net`), and configure SPF and DKIM for the domain. Test
the mail path directly on the server:

```sh
php scripts/mail-test.php hello@relay.eclecity.net
```

If email is unavailable, the CLI link above still signs you in.

The header shows the signed-in name and email on every page. Interactive pages
render it server-side; the pre-rendered catalogue pages fetch `/session.php`
after load, so they remain usable without JavaScript.

## Human review

A listing is not published until a person approves it. `projects.review_status`
is `pending`, `approved` or `rejected`, and `scripts/build.php` only includes
approved rows. New submissions default to `pending`, so an automated flood of
repositories cannot reach the catalogue without a human decision on each one.

Apply the migration before deploying the build that uses it:

```sh
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0001_add_review_status.sql
```

The migration approves existing listings, including Relay itself. Reviewers
with the `admin` or `moderator` role use `/review/`; the CLI remains for new
listings:

```sh
php scripts/review.php list
php scripts/review.php approve <slug>
php scripts/review.php reject <slug> --note "reason"
```

The web queue writes an `audit_log` entry, sets `published_at` on first
approval and gives the listing a six-month expiry. Rebuild after a decision:

```sh
./deploy.sh --build
```

## Editing a listing

The dashboard has an **Edit** link for each listing. A pending or rejected
listing is updated directly and returns to the review queue. An approved
listing stays live while the edit is stored as a pending revision; `/review/`
shows the proposed content, and approving it applies the change, renews the
six-month expiry and writes an `audit_log` entry. Rejecting it leaves the live
listing untouched.

The dashboard title is also a link: an approved listing opens its public page;
a pending or rejected listing opens `/preview/?slug=…`, an owner-only preview
with an Edit button. The preview is `noindex` and never published.

Apply `db/migrations/0002_add_project_revisions.sql` before deploying the edit
flow. The live page is only regenerated after the revision is approved and the
build runs.

## Expressions of interest

An approved listing has an **Express interest** button at `/interest/?project=…`.
It requires sign-in. The applicant answers four short questions — relevant
experience, time available, the first 30 days and what they want — and can add
a note. Their email is not shown to the owner.

The owner opens `/interests/` from the dashboard, or from a project's “new
expression” badge. They read the answers and accept or decline:

- **Accept** reveals each party's email address, sends an introduction to both
  and records the decision in `audit_log`. The applicant's dashboard shows the
  owner's email.
- **Decline** keeps both addresses hidden and shows the owner's optional reason
  in the applicant's dashboard.

Apply `db/migrations/0003_add_interest_details.sql` before deploying the relay.
The pending dummy is not open for interest until it is approved and rebuilt;
use an approved listing to test the full loop.

## Deploying

The first guide is `/learn/handover/`, *When the maintainer disappears*. It is
generated from `content/learn/handover.md` through the guide list in
`content/learn/guides.php`. To add a guide, add an entry to that array and a
Markdown file; the build writes `public/learn/<slug>/index.html` and the
`/learn/` index.

## Deploying

The current DreamHost vhost serves the repository root, so the working layout
is:

```
/home/eclecity/relay.eclecity.net/            repository + web root
/home/eclecity/relay.eclecity.net.txt         config (above)
```

`index.php` at the root serves `public/index.php`, and `.htaccess` denies
`scripts/`, `db/`, `bootstrap.php` and the documentation. `public/` is kept
separate so the site can move to a `public/` Web Directory later.

The preferred layout is to set DreamHost's **Web Directory** for
`relay.eclecity.net` to `/home/eclecity/relay.eclecity.net/public`. The current
vhost does not serve that path (static files return 404), so the root layout
above is in use. If DreamHost fixes it, point the panel at `public/` and remove
the root `index.php`.

Copy `deploy.conf.example` to `deploy.conf` (gitignored), then:

```sh
./deploy.sh                  # push the repository, no deletions
./deploy.sh --build          # push, then run scripts/build.php on the server
./deploy.sh --delete         # mirror, removing stale files
DEPLOY_DRY_RUN=1 ./deploy.sh # print the rsync command only
```

The script excludes `config.php`, `config.json`, `*.eclecity.net.txt`, `.env`,
`deploy.conf` and `db/seed.sql`. The config file is created on the server by
hand and is never uploaded.

After the first deploy, check the database:

```sh
ssh eclecity@doclets.eclecity.net \
  'cd /home/eclecity/relay.eclecity.net && php scripts/db-check.php'
```

## Licence

Apache-2.0; see [LICENSE](LICENSE).
