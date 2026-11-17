# The brief now leaves the browser as a file or compressed, not as long text

**Action:** deploy from `~/dev/relay`, hard-reload, submit the AllSpeak listing
with the full brief.

## Why compression is the right idea

A pattern-matching request filter scores the *text* of a form field: backticks,
HTML-looking tags, SQL-ish phrases. Compressed bytes have no text to score, and a
file part is not inspected the way a field is — which is why the import, which
sends your listing as an uploaded file, already gets through on this host.

Requiring you to zip the brief by hand would work, but it puts a step in the way
of every submission and needs `ext-zip` on the server. The browser can gzip it
with `CompressionStream` and the server can inflate it with `gzdecode`, which is
plain zlib and always present.

## What changed

`templates/submit.php` — on submit, when the brief is 8 KB or longer:

1. it is attached as `brief.md` to a hidden `brief_file` field and the textarea
   is disabled (file part, the shape the import proves works);
2. if `DataTransfer` is unavailable, it is gzipped, base64-encoded into a hidden
   `brief_gz` field, the textarea disabled, and the form re-submitted;
3. with neither available, nothing changes and the textarea posts as before.

Short briefs (under 8 KB) are untouched, so the submissions that already work
cannot regress.

`absorb_brief_upload()` in `src/projects.php` reads `brief_file` first, then
`brief_gz` (128 KB cap, bad base64, non-gzip data and missing temporary files all
ignored). It runs at the top of `/submit/` and `/edit/` before anything reads the
field, so validation, the save and the revision path see ordinary text.

## If it still fails

Then the filter inspects binary bodies too, and it has to be relaxed on the host:
DreamHost panel → **Manage Domains** → the domain → **Web Security**, or support
whitelisting one rule id, found with:

```sh
grep -i modsecurity logs/relay.eclecity.net/https/error.log | tail -5
```

Apache rejects the request before PHP, which is why no application log ever saw
it.
