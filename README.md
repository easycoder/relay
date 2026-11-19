# Relay

Connecting ideas, prototypes and projects with the people who can take them forward.

Relay is a free, open-source registry and introduction service for work that needs someone next. An owner describes what they have — or what they want to exist — and what somebody taking it on would inherit or test; a reader, increasingly working with AI agents rather than writing every line by hand, can assess it quickly and start a conversation. A listing is an **idea** (not built yet), a **prototype** (part-built) or a **live project** (in use). Each carries a six-month expiry so the catalogue stays current.

The proposal, including the product shape, the brief standard and the technical approach, is in [PROPOSAL.md](PROPOSAL.md).

## Status

Phase 0: direction agreed, the seed listing is live and the catalogue is in place. The site is at <https://relay.eclecity.net>.

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
`public/home.html`, `public/projects/`, `public/learn/`, `public/about/`,
`public/projects.json`, `public/sitemap.xml` and `public/robots.txt`. Those files are generated on the
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

## Drafting a listing with an AI

`/submit/` opens with a **Draft this with an AI, then import it** block. The
prompt inside it is generated from `submission_fields()` and
`submission_field_limits()` in `src/projects.php` — every field, its type,
whether it is required, its character limit, the allowed values (stage, status,
arrangement, revenue state, user scope, contact mode, assets, readiness), the
live category list, and the brief sections for each stage. Add or change a field
there and the prompt follows, so an AI never works from a stale shape.

The owner copies the prompt into their AI, gets back one JSON object, saves it
and imports it with the file input. The import does not submit anything: it
validates the JSON with the same validator the form uses, then prefills the form
so the owner can correct anything and tick the confirmation. Booleans map to the
AI checkboxes, lists accept either arrays or comma-separated strings, unknown
assets are dropped, and a Markdown code fence around the JSON is tolerated.
`synopsis_ai_drafted` and `brief_ai_drafted` are advisory — the owner confirms
them on the form.

### A note on long briefs

A brief of 8 KB or more is gzipped in the browser and submitted in a hidden field
(`brief_gz`), with the textarea disabled; `absorb_brief_upload()` in
`src/projects.php` inflates it back before validation. Two reasons: it is about a
fifth of the bytes on the wire, and some hosts' request filters reject a long,
code-heavy text field as though it were an attack — which is exactly what happened
on this deployment. Browsers without `CompressionStream` fall back to sending the
brief as a file part. Nothing else in the flow is aware of it. `DIFF.md` records
how to remove it if the site ever moves somewhere that accepts the plain field.

## Finding projects

`/find/` is the searchable view of the catalogue: free text over title, pitch,
synopsis and brief, plus facets for stage (idea, prototype or live), category,
what the owner is looking for, arrangement, readiness and effort in hours per
month. Choices are a GET form, so any view is a link that can be shared or
bookmarked, and the page works without JavaScript.

The default view is **approved** listings. The **Pending approval** tab shows
everything still pending, with the same search and facets. Each pending entry
opens at `/waiting/?slug=…`, which is the listing, the like form and the
reasons people gave. Both views are `noindex`; pending listings never enter
`projects.json`, the sitemap or the home page.

The result list defaults to **10 entries per page in random order**, so a new
visitor sees a fair mix rather than the same newest handful. A selector offers
10, 20 or 50 per page, and the order can be switched to **most recent**. In
random mode the seed travels in the URL, so **More** continues the same shuffle
rather than repeating entries. There is one set of records: the approved flag
is `projects.review_status`, and the pending view is a filter, not a separate
queue. Every card shows the number of likes, when there is at least one, and an
**Approved** or **Pending approval** flag; the entry page shows the count in its
facts when it is above zero. The generated catalogue pages show counts as of the
last build; `/find/` is live.

Each facet's counts are computed *without* its own filter, so a dropdown shows
what choosing that value would lead to rather than only what is already chosen.
The query lives in `search_where()` in `src/search.php`, which returns SQL and its
parameters together — the parameters are bound, and `%`/`_` in a search term are
escaped before they reach `LIKE`.

`/find/?format=json` returns the same rows as data (`query`, `total`, `page`,
`pages`, `projects`), including `stage`, `approved`, `review_status` and
`like_count`, which is the surface an agent or script reads; `/projects.json`
remains the whole-catalogue dump of approved listings, with `like_count`.
Facet URLs are `noindex` and disallowed in `robots.txt` so the generated pages
stay the indexed catalogue. The nav link and the catalogue's "Filter and
search" button appear on generated pages, so they need a publish (or a build)
to show up.

## Review and community approval

Only approved listings are published. `projects.review_status` is `pending`,
`approved`, `rejected` or `taken_down`, and `scripts/build.php` includes only
approved, unexpired rows. New submissions start `pending`.

A pending listing is visible at `/find/?view=pending` and on its own waiting
page, `/waiting/?slug=…`. Both are `noindex`; neither is in `projects.json`,
the sitemap or the home page. The waiting page shows the brief and the reasons
people gave for liking it. The default view of `/find/` remains approved
only.

A like is a reasoned act, not a click:

- one per account per listing (`project_likes` has a unique key);
- the owner cannot like their own listing;
- the account must be at least `like_min_account_age_days` old;
- one IP may leave at most `like_ip_daily_limit` likes in a day;
- the reason is required (20–500 characters) and is shown publicly on the
  waiting page and to reviewers.

When the count reaches `like_threshold` (default 5) and the listing is not
blocked, it is approved automatically with `approval_source = 'community'`.
The settings live in the `community` block of the config, or as flat
`like_*` keys in the host-named JSON file:

```php
'community' => [
    'like_threshold' => 5,
    'like_min_account_age_days' => 7,
    'like_ip_daily_limit' => 10,
    'edit_review_days' => 14,
],
```

A person can approve or reject any listing at `/review/`, or with the CLI:

```sh
php scripts/review.php list
php scripts/review.php approve <slug>
php scripts/review.php reject <slug> --note "reason"
php scripts/review.php veto <slug> --note "reason"
php scripts/review.php accept-edit <slug>
php scripts/review.php takedown <slug> --note "reason"
```

`accept-edit` lets an edited listing stay; `takedown` removes it pending a
correction and emails the owner.

An approval needs no reason. A rejection stores the reason (`review_note`),
shows it to the owner on the dashboard and the edit form, and sets
`auto_approval_blocked`: later likes cannot approve that listing. The
owner can edit and resubmit; the likes are cleared because they were
about the old content, and a person must approve the new version. A reviewer
can veto a community approval on `/review/`; the veto is recorded as
`project.vetoed` and does not count as a submission strike.

Edits to a live listing are different: they apply immediately, the listing is
marked **Edited**, and it joins a separate **Recently edited** list on
`/review/`. If nobody acts, the edit is accepted after `edit_review_days`
(default 14), and the Edited marker disappears. A reviewer can accept the edit
sooner, or take the listing down pending a correction, giving a reason that is
emailed to the owner and shown on their dashboard. A taken-down correction goes
back through initial review, and `auto_approval_blocked` keeps likes from
putting it back on their own. Legacy pending revisions remain in an **Earlier
revisions** section and are superseded by a live edit.

When an owner edits a listing, the diff against the version being replaced is
created at submission and stored in `projects.review_diff` — for a live edit as
well as for a pending, rejected or taken-down listing. Legacy revision rows keep
their diff in `project_revisions.diff`. `/review/` offers **Changes** and
**Full text** tabs. A new submission has no previous version, so it keeps the
full-text view. The tabs work without JavaScript.

Apply the migrations before deploying this code:

```sh
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0007_add_stages_endorsements_and_subject_audit.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0008_add_review_diffs.sql
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0009_add_edit_review.sql
```

Approving a listing does not publish it on its own, and a take-down does not
remove the page on its own. Press **Publish now** on `/review/`, or run
`./deploy.sh --build` / `php scripts/build.php`. The build
takes a lock (`storage/publish.lock`), so a cron build and a button press cannot
interleave. It needs `public/` writable by the PHP user, which it already is
after a deploy.

An operator address in the config (`mail.admin`) is emailed when the queue
grows: a new listing, a listing edited back into the initial queue, or a
community approval. Live edits are not emailed; they appear in the re-review
list. One message per event, subject prefixed `Relay:`, and never when the
operator is the person who acted — so a filter can divert them and a busy day
cannot bury an inbox. Leave the key out to send none.

## Editing a listing

The dashboard has an **Edit** link for each listing. An edit to a live listing
applies immediately and the public page updates on the next build. The listing
is marked **Edited** and joins the reviewer's re-review list for
`edit_review_days` (default 14) days unless accepted; a reviewer can take it
down for correction. A pending, rejected or taken-down listing is updated in
place and returns to the initial review queue; its likes are cleared,
because they were about the old content. If a reviewer had rejected or taken it
down, the new version still needs a person. The review page's **Changes** tab
shows what this save changed; **Full text** shows the complete version.

The dashboard title is also a link: an approved listing opens its public page;
a pending, rejected or taken-down listing opens `/preview/?slug=…`, an
owner-only preview with an Edit button. The preview is `noindex` and never
published.

A rejected or taken-down listing shows the reviewer's reason and the retries it
has used. A listing may be resubmitted five times in any rolling thirty days:
each save of a listing that is not live is recorded in `project_resubmissions`,
and at the limit the save is refused before anything is written — the edit form
shows the date the next attempt opens and disables the save button, so a bot
cannot loop the queue. Edits to a live listing are not resubmissions and are
not counted; they are re-reviewed after the fact. `RESUBMIT_LIMIT` and
`RESUBMIT_WINDOW_DAYS` in `src/projects.php` are the knobs.

### Deleting a listing

The edit page ends with a **Delete this listing** button. It is only offered
when nobody is interacting with the listing: any expression of interest (in any
state), or an earlier revision awaiting a decision, blocks it, and the page says which.
Deleting removes the listing, its revisions and its resubmissions; the
`audit_log` entry (`project.deleted`, with the title and slug) stays. Run a
build afterwards — `scripts/build.php` now prunes `public/projects/<slug>/` for
anything it no longer publishes, so the page goes with it.

## Limits on submissions

Two things are scarce — mail and reviewer time — so both are bounded per
account, and the refusals happen before anything is written.

- **New listings.** At most three in any rolling thirty days, and at most two
  pending approval at once. An approval or rejection frees a queue slot; the
  next new listing opens when the oldest one ages out of the window. The point
  is the queue bound: a reviewer can never face a pile from one account,
  however the attempts are paced. Counted from `project.submitted` in the
  audit trail, so deleting a listing does not reset the window.
- **Resubmissions.** Five per listing in any rolling thirty days, as above.
- **Live edits.** Not counted as submissions or resubmissions. They are
  re-reviewed after the fact; a take-down is not a rejection strike.
- **Rejections.** Three rejected listings in thirty days pause the account's
  submissions and resubmissions for thirty days. Rejections are counted from
  `project.rejected` with the owner as `subject_user_id`, so deleting or
  resubmitting a listing does not erase the strike, and they age out of the
  window one at a time, so the pause lifts by itself. A veto of a community
  approval or a take-down is a separate action and does not count. The pause
  keys on the listing's owner, so a moderator editing someone else's listing is
  not blocked by that account's record.
- **Sign-in.** Ten magic links per IP and five per address per fifteen minutes.

The knobs are all in `src/projects.php`: `SUBMIT_PENDING_LIMIT`,
`SUBMIT_WINDOW_LIMIT`, `SUBMIT_WINDOW_DAYS`, `SUBMIT_STRIKES`,
`RESUBMIT_LIMIT`, `RESUBMIT_WINDOW_DAYS`, `EDIT_REVIEW_DAYS`. The last is the
default for the `community.edit_review_days` setting.

## Expiry and reconfirmation

A listing carries a six-month expiry (`projects.expires_at`). It is set when a
submission is created, renewed on approval, when a live edit is saved and when a
correction is approved, and pushed out by a reconfirmation. A pending
submission has the same clock, so an abandoned submission does not sit in the
waiting list for ever. The build and search only include rows that have not
expired, so an unrenewed listing leaves the catalogue quietly — which is why
owners get a warning first.

```sh
php scripts/expiry-reminders.php            # remind about the next 14 days
php scripts/expiry-reminders.php --days=30  # a wider window
php scripts/expiry-reminders.php --dry-run  # say who would be emailed
```

Run it once a day from cron, for example:

```cron
17 7 * * * cd /home/eclecity/relay.eclecity.net && php scripts/expiry-reminders.php >> /home/eclecity/expiry.log 2>&1
```

Expiry only takes effect when the build runs. Schedule a build too, so an
approval or an expiry does not wait for someone to press **Publish now**:

```cron
*/10 * * * * cd /home/eclecity/relay.eclecity.net && php scripts/build.php >> /home/eclecity/build.log 2>&1
```

The reminder covers approved listings only; a pending owner sees the expiry on
the dashboard.

Each run emails the owner of every approved listing that expires inside the
window and has not been reminded about *this* expiry; `project_expiry_reminders`
records the send, so a repeated run is silent, and a reconfirmation (which moves
the expiry) makes the listing eligible again. The email carries a link to
`/reconfirm/?token=…`: single use, valid 30 days, no sign-in needed, because
reading the link proves the owner still has the address. Using it pushes the
expiry out six months, marks the listing confirmed and audits
`project.reconfirmed`. The dashboard shows the same state — "Expires in 9 days"
inside three weeks, "Expired on …" once it has gone.

Mail is best-effort: a failure leaves the reminder unrecorded so the next run
tries again, and the dashboard line is the fallback.

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
ssh eclecity@eclecity.net \
  'cd /home/eclecity/relay.eclecity.net && php scripts/db-check.php'
```

## Licence

Apache-2.0; see [LICENSE](LICENSE).
