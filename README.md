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
for a name and an email address. **Nothing is created when the form is
submitted.** The name travels with the link (`magic_links.display_name`) and
`/login/verify/` creates the account, with that name, only when the link is
used — so an address nobody can read never leaves a `users` row behind. If the
address already exists, the name is ignored and the existing one is kept. The
same visit creates the session. The role account `hello@relay.eclecity.net` is
already an `admin`. `/submit/` requires sign-in and writes a `pending` listing;
`/dashboard/` shows an owner their listings, their review status and an Edit
link.

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

A session lasts thirty days, and it renews itself: once it has less than
fifteen days left, any page load pushes its expiry back out to thirty days. A
session you use stays signed in; one you leave alone lapses. There is no
absolute limit, so a browser you use regularly never asks for another link.

### Switching accounts in one browser

Signing in does not throw the previous account away. The account you leave
stays in a second httpOnly cookie, `relay_accounts`, as its own session row,
and `/accounts/` (linked from the dashboard) lists them: switch with one
click, forget one, or **Forget this browser** to end all of them. `/login/`
offers the same list as **Continue as** buttons, and it stays reachable while
you are signed in — **Sign in as someone else** takes you there, and the
account you sign in with is added alongside the current one rather than
replacing it. Getting to the admin account costs a click rather than an email.

Sign out ends the account you are using and leaves the others switchable, so
signing back in as the same person is still one click. The list holds up to six
accounts, one entry per address, and a switch rotates the token it activates.
Tokens stay in cookies that page scripts cannot read — nothing is kept in
`localStorage`, and no token is ever written into the HTML.

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
approval and gives the listing a six-month expiry. A rejection reason is also
stored on the listing itself (`projects.review_note`) and shown to the owner on
the dashboard and at the top of the edit form; it is cleared when they
resubmit. Both that column and the retry counter below come from `0004`:

```sh
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0004_add_review_note_and_resubmissions.sql
```

Rebuild after a decision:

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

A rejected listing shows the reviewer's reason and the retries it has used. A
listing may be resubmitted five times in any rolling thirty days: each save of
a listing that is not live is recorded in `project_resubmissions`, and at the
limit the save is refused before anything is written — the edit form shows the
date the next attempt opens and disables the save button, so a bot cannot loop
the queue. Edits to an approved listing are revisions and are not counted.
`RESUBMIT_LIMIT` and `RESUBMIT_WINDOW_DAYS` in `src/projects.php` are the
knobs.

## Limits on submissions

Two things are scarce — mail and reviewer time — so both are bounded per
account, and the refusals happen before anything is written.

- **New listings.** At most three in any rolling thirty days, and at most two
  waiting for review at once. An approval or rejection frees a queue slot; the
  next new listing opens when the oldest one ages out of the window. The point
  is the queue bound: a reviewer can never face a pile from one account,
  however the attempts are paced.
- **Resubmissions.** Five per listing in any rolling thirty days, as above.
- **Rejections.** Three rejected listings in thirty days pause the account's
  submissions and resubmissions for thirty days. Rejections are counted from
  `audit_log`, so resubmitting a listing does not erase the strike, and they
  age out of the window one at a time, so the pause lifts by itself. The pause
  keys on the listing's owner, so a moderator editing someone else's listing is
  not blocked by that account's record.
- **Sign-in.** Ten magic links per IP and five per address per fifteen minutes.

The knobs are all in `src/projects.php`: `SUBMIT_PENDING_LIMIT`,
`SUBMIT_WINDOW_LIMIT`, `SUBMIT_WINDOW_DAYS`, `SUBMIT_STRIKES`,
`RESUBMIT_LIMIT`, `RESUBMIT_WINDOW_DAYS`.

What these do **not** cover: `users` rows are still created when a link is
*requested* (`public/login/index.php` calls `create_user()` before sending), so
an unverified row exists for any address typed into the form, bounded only by
the magic-link budget. Such a row cannot submit anything — that needs a session,
which needs a consumed link — so it is a clutter-and-noise risk rather than a
submission route.

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
