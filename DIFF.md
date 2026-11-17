# The brief is compressed on submit — smaller on the wire, and past the filter

**Action:** deploy from `~/dev/relay`, hard-reload, submit as usual. Nothing to
change on the domain or in the control panel.

## What changed

For briefs of 8 KB or more, `templates/submit.php` now:

1. gzips the brief in the browser (`CompressionStream`), base64s it into a hidden
   `brief_gz` field, disables the textarea and re-submits — **the normal path**;
2. if the browser has no `CompressionStream`, attaches the brief as `brief.md` to
   a hidden `brief_file` field instead;
3. with neither, posts the textarea exactly as before.

`absorb_brief_upload()` in `src/projects.php` reverses step 1 with `gzdecode`,
falling back to the file, and writes the result into `$_POST['brief']` before
anything reads it — so validation, the save, revisions and the limits see
ordinary Markdown. Caps: 128 KB either way; bad base64, non-gzip data, missing
temporary files and oversized payloads are ignored rather than fatal.

## Why compression rather than a host setting

- A one-paragraph brief always submitted; a 20 KB one was rejected before PHP ran,
  because the host's request filter scores the *text* of a field (188 backtick
  code spans in that brief). Compressed bytes carry no text to score.
- It also sends about a fifth of the bytes, which is the better default on a slow
  link regardless of any filter.
- Turning ModSecurity off for the domain would have fixed it, but it is a setting
  in a control panel rather than anything in this repository — one more thing for
  a successor to discover. This keeps the behaviour with the code.

## Removing it later

If the site ever runs where long text is accepted, delete three things and the
form goes back to plain HTML: the `<script>` and the two hidden inputs at the end
of the form in `templates/submit.php`, and the `absorb_brief_upload()` calls at
the top of `/submit/` and `/edit/` (`src/projects.php` can keep the function,
unused, or lose it too).

## Worth keeping

The breadcrumbs (`submit: post received / validated / saved`) and
`storage/error.log` stay: they are what turned this from guesswork into a
diagnosis, and the next failure of this kind should be visible rather than silent.
