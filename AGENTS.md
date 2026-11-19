# Relay — agent instructions

Relay is a free, open-source registry and introduction service for work that needs someone next. It is not a code marketplace, a broker, an escrow service or a handover authority. Read `PROPOSAL.md` for the product shape and `README.md` for operations.

## What a listing is

A listing has two independent axes:

- **Stage** — `idea` (not built yet), `prototype` (part-built) or `live` (in use). This is `projects.stage`.
- **Intent** — what the owner is looking for: a successor, a collaborator, testers, feedback, a buyer, a temporary steward. This is `projects.status`.

The stage decides which fields matter. An idea has no repository, users, revenue or handover yet; a live project has all of them. The brief is called an **Idea Brief**, **Project Brief** or **Adoption Brief** depending on the stage, but it is one `projects.brief` column. Do not force handover fields on an idea.

A published listing carries a six-month expiry. A pending submission has the same clock. `scripts/expiry-reminders.php` warns owners of approved listings before they leave the catalogue; a reconfirmation pushes the expiry out six months. Expired rows stay in the database but are excluded by the build and by search.

## Review and community approval

`projects.review_status` is `pending`, `approved`, `rejected` or `taken_down`. Only approved rows are built into the catalogue. The rules are deliberate:

1. A new submission is `pending`. It appears in the waiting list at `/find/?view=pending` and on `/waiting/?slug=…`, both `noindex`. It is not in the catalogue, `projects.json` or the sitemap.
2. A pending listing can be liked. A like requires a written reason (`project_likes.reason`), one account may like a listing once, the owner cannot like their own listing, and the account must be older than `like_min_account_age_days`. There is also a per-IP daily cap.
3. When the like count reaches `like_threshold` (default 5) and `auto_approval_blocked` is 0, `record_endorsement()` approves the listing through `review_project()` with `approval_source = 'community'`. The threshold and gates are in the `community` block of the config.
4. A person can approve or reject any listing at `/review/` or with `php scripts/review.php`. A rejection requires a reason (shown to the owner) and sets `auto_approval_blocked = 1`. Likes cannot override a rejection; the owner must edit and resubmit, and a person must approve it. Resubmission clears the likes, because they were about the old content.
5. A reviewer can veto a community approval. A veto is recorded as `project.vetoed`, sets `review_status = 'rejected'` and blocks automatic approval. It does not count as a submission strike, because the owner did not resubmit anything.
6. An edit to a live listing applies immediately through `record_live_edit()`. The listing is marked Edited (`edited_at`, `edit_review_status = 'pending'`) and joins the **Recently edited** list on `/review/` for `edit_review_days` (default 14) days. If nobody acts, the build's `accept_stale_edits()` accepts it and the Edited marker disappears. A reviewer can `accept_edit()` or `take_down_project()`; a take-down needs a reason, sets `review_status = 'taken_down'`, emails the owner and removes the page on the next build. A correction goes back through initial review. Legacy pending revisions are superseded by a live edit.
7. A pending, rejected or taken-down listing is updated in place and returns to initial review. Its likes are cleared, and `auto_approval_blocked` keeps a rejected or taken-down correction out of automatic approval.
8. Approving a listing does not publish it on its own. Run `php scripts/build.php` or press **Publish now** on `/review/`. The build takes a lock, so a cron build and a button press cannot interleave. A take-down also needs a build to remove the page.

The operator notifications are controlled by `mail.admin` in the config; leave it empty to send none. Live edits are not emailed to the operator; they appear in the re-review list. A take-down emails the owner.

## Data and audit invariants

- `review_project()` in `src/review.php` handles an initial-review decision: it sets `review_status`, `published_at`, `expires_at`, `approval_source`, `auto_approval_blocked`, clears `review_diff`, sets `edit_review_status = 'accepted'` and writes the audit row. A live edit uses `record_live_edit()` instead: it applies the data, sets `edited_at` and `edit_review_status = 'pending'`, stores the diff, clears the approval source and note, and supersedes any legacy pending revision. `accept_edit()` accepts; `take_down_project()` sets `taken_down`, emails the owner and blocks automatic approval; `accept_stale_edits()` accepts edits whose window has passed.
- The review diff is generated at submission by `src/diff.php` and stored in `projects.review_diff` for any edit, live or pending. Legacy revision rows keep theirs in `project_revisions.diff`. Do not recompute it at review time; it is the owner's change as submitted.
- The public Edited marker is `edit_marker_visible()`: an approved listing with `edit_review_status = 'pending'` and an `edited_at` inside `edit_review_days`. Accepting the edit, taking it down, or the window passing removes it. Do not show it after acceptance.
- `taken_down` is a review status: the listing is not in the catalogue, the waiting list or the re-review list. The owner sees the reason on the dashboard and the edit form; a correction returns to initial review.
- `audit_log.subject_user_id` is the person an event is about, normally the owner. Submission and rejection limits count from `audit_log`, not from `projects`, so deleting a listing cannot reset the window or erase a strike. Set the subject on every project audit row.
- `project_likes` has a unique `(project_id, user_id)` key. The like reason is public on the waiting page and shown to reviewers; treat it as personal data and keep it out of the catalogue.
- `clear_project_likes()` is called when a pending, rejected or taken-down listing is edited. Do not carry likes across a content change.
- The build only includes approved, non-expired rows whose status is not `draft` or `archived`. Search mirrors that rule; `search_where()` is the shared clause builder.
- Keep pending rows out of `projects.json`, the sitemap, the home page and feeds.

## Stack and layout

- PHP 8 with PDO and prepared statements. No framework.
- MySQL/MariaDB, `utf8mb4`, InnoDB. `db/schema.sql` is the baseline; numbered migrations in `db/migrations/`.
- Public catalogue: pre-rendered HTML written by `src/build.php` to `public/home.html`, `public/projects/`, `public/learn/`, `public/about/`, `public/projects.json`, `public/sitemap.xml` and `public/robots.txt`. Generated files are gitignored and produced on the server.
- Interactive surfaces: PHP rendered per request (`public/find/`, `public/submit/`, `public/dashboard/`, `public/review/`, `public/interests/`, `public/waiting/`, `public/like/`).
- Templates live in `templates/`; helpers and escaping in `src/html.php`.
- The site must work with JavaScript off. JavaScript may enhance a page, never gate it.
- JSON is the machine interface: `/projects.json` for the whole catalogue and `/find/?format=json` for a filtered view.
- `/find/` defaults to approved entries in random order, 10 per page. `per_page` offers 10/20/50, `sort` offers random/recent, and the random seed is kept in page links so More continues the same order. Cards and entry pages show the like count when it is above zero, plus an Approved/Pending approval flag. The approved flag is `review_status = 'approved'`; the count is the number of `project_likes` rows. There is one `projects` table: pending is a view, not a separate queue.

### Where things live

| Path | Purpose |
|---|---|
| `bootstrap.php` | Config discovery, PDO, `app_log()` |
| `src/projects.php` | Field definitions, validation, creation, edit-review helpers, limits, AI prompt |
| `src/review.php` | `review_project()`, `veto_project()`, `review_revision()`, `record_live_edit()`, `accept_edit()`, `take_down_project()` |
| `src/diff.php` | Submission-time diff and the CSS-only review tabs |
| `src/likes.php` | Like rules, recording, threshold approval, clearing |
| `src/search.php` | `search_filters_from()`, `search_where()`, results and facet counts |
| `src/build.php` | Catalogue build, stale-edit sweep and lock |
| `src/auth.php` | Magic links, sessions, remembered accounts, mail |
| `src/interests.php` | Expressions of interest |
| `public/` | Web entry points |
| `templates/` | Server-rendered views |
| `db/` | Schema, migrations, content import |
| `scripts/` | Build, review CLI, expiry reminders, DB check |

## Conventions

- Start PHP files with `declare(strict_types=1);`.
- Write doc comments that say **why** a block exists, not what the syntax does. Keep functions small and named after the domain.
- Escape every dynamic value with `e()`. Render Markdown only through `render_markdown()`, which escapes first. Use prepared statements; never interpolate user input into SQL.
- CSRF-check every state-changing POST. Use the existing `csrf_token()` / `verify_csrf()`.
- No secrets or personal data in the repository. The config file sits above the web root on the server; `config.php` is gitignored locally.
- Numbered migrations only. Apply one once, in order, before deploying code that depends on it. Never edit an applied migration.
- After a content, template or CSS change that affects generated pages, rebuild. CSS alone is static.
- Keep `DIFF.md` at the project root, **rewritten** after every change, leading with the action the human must take. It is not a changelog.
- Session conversation notes under `conversation/` are local only and gitignored.
- Do not add AllSpeak files or `.allspeak` code. This project is PHP. `.allspeak-init` is retained only because the local visualizer reads it; it does not make Relay an AllSpeak project.

## Verify

There is no automated test suite. At minimum:

```sh
# Syntax check every PHP file
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l

# On the server, after the config is in place
php scripts/db-check.php
```

For a change that touches the review flow, exercise it end to end on a local or staging database:

1. Submit a listing at `/submit/` with stage `idea`, then check it appears at `/find/?view=pending` and `/waiting/?slug=…`.
2. Like it from a second account older than the gate; check the reason appears and the count rises.
3. Reach the threshold and check the listing is approved with `approval_source='community'`; run the build and confirm it appears at `/projects/…`.
4. Reject another listing; confirm the owner sees the reason and that further likes do not approve it.
5. Veto a community approval on `/review/`; confirm `project.vetoed` is in the audit log and no strike is counted.
6. Let a listing expire; confirm search and the build exclude it, and the dashboard says so.
7. Edit a live listing, change one word, and confirm it stays live, shows Edited, and appears under Recently edited with Changes / Full text.
8. Accept the edit; the marker goes. Edit again and take it down with a reason; confirm the listing leaves the catalogue, the owner is emailed and a correction returns to initial review.

## Deployment

```sh
./deploy.sh                  # push the repository
./deploy.sh --build          # push, then build on the server
./deploy.sh --delete         # mirror, removing stale files
DEPLOY_DRY_RUN=1 ./deploy.sh # print the rsync command only
```

The current DreamHost vhost serves the repository root; `index.php` serves `public/index.php`, and `.htaccess` denies source paths. The config is `relay.eclecity.net.txt` one level above the web root. Schedule `scripts/expiry-reminders.php` daily and a build soon after, so approvals and expiries do not wait for a button press. See `README.md` for the exact commands.

## Boundaries

- Do not turn Relay into a job board, a marketplace, an escrow service or a paid-placement directory.
- Do not ask for passphrases, production credentials, customer data or payment details.
- Do not add third-party tracking or a cookie banner.
- Do not remove the six-month expiry, the time-limited Edited marker and re-review window, or the rule that a person can reject, veto or take down any listing.
- Do not make the public catalogue depend on JavaScript.
