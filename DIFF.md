# Publishing from the browser: a Publish now button on /review/

**Action:** deploy from `~/dev/relay`, reload `/review/`. Approve a listing, then
press **Publish now**.

## What changed

- **`/review/` has a Publish button.** It posts to `/publish/`, which rebuilds the
  catalogue and returns to the review page with a count: "Published: 1 listing
  rebuilt, 2 stale pages removed."
- **`/publish/` is guarded**: `admin` or `moderator` only, POST only, CSRF
  checked, and one build at a time through a lock file, because two builds would
  interleave the directory pruning with the page writes. A failed build leaves
  every page as it was, logs the reason to `storage/error.log`, and says so on the
  page instead of showing a stack trace.
- **The build moved into `src/build.php`** as `build_catalogue()`, returning its
  progress lines and counts. `scripts/build.php` is now a thin wrapper around it,
  so the button, cron and `./deploy.sh --build` all run the same code.
- **The post-approval notices changed.** "Run `php scripts/build.php`" is gone:
  the page now says the listing is approved and points at the button, and notes
  that approving does not publish on its own.
- `robots.txt` disallows `/publish/`.

## Notes

- The button writes `public/` as the PHP user, which is the same account the
  deploy uses, so permissions are already right. If `public/` ever becomes
  read-only to PHP, the page says the build failed rather than half-publishing.
- The CLI is unchanged and still the right tool for deployments and cron:
  `php scripts/build.php`, or `./deploy.sh --build`.
- Publishing is still a separate act from reviewing. That is deliberate: a
  reviewer can reject or approve in the queue without touching the live site, and
  publish only when the set of changes is what they want to go out.
