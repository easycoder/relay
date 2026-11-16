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

The migration approves existing listings, including Relay itself. The review
queue is a CLI for now; the web moderation screen comes with the submission
flow:

```sh
php scripts/review.php list
php scripts/review.php approve <slug>
php scripts/review.php reject <slug> --note "reason"
```

Then rebuild. The CLI writes an `audit_log` entry for each decision and sets
`published_at` on first approval.

## Guides

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
