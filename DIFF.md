# Search and facets at /find/

**Action:** deploy from `~/dev/relay`, then press **Publish now** on `/review/` so
the generated pages pick up the new nav link and the catalogue's button. Then open
`/find/`.

## What it does

- Free text over title, pitch, synopsis and brief, with facets for category, what
  the owner is looking for, arrangement, readiness (L0–L4) and effort in hours per
  month (up to 2 / 3–8 / more than 8).
- Every choice is a GET parameter, so a filtered view is a URL that can be shared,
  bookmarked or handed to an agent. The page is plain PHP and works with
  JavaScript off.
- Keywords are offered as chips with counts, taken from `project_keywords`.
- Results are 12 to a page, newest first, with the filters preserved across
  pages.

## How it is built

- `src/search.php` holds the whole query: `search_filters_from()` cleans the
  query string against closed sets (an unknown category or readiness is treated
  as "not asked for"), and `search_where()` returns SQL and its parameters
  together. Parameters are bound, never interpolated, and `%`/`_` in a search term
  are escaped before they reach `LIKE`.
- A listing can only appear if the build would publish it: approved, not draft or
  archived, published, and unexpired. That clause is the first thing `search_where()`
  adds, so the search can never show something the catalogue hides.
- Each facet's counts are computed with its own filter skipped, which is what
  makes a dropdown say "Developer tools (9)" as a destination rather than a
  restatement.
- `/find/?format=json` returns the same rows as data.
- `noindex` on the page and `Disallow: /find/` in `robots.txt`: the generated
  pages remain the indexed catalogue, so facet combinations cannot multiply in
  a search index.

## Notes

- No JavaScript and no client framework. The AllSpeak direction in the proposal
  would be a client over `/find/?format=json`; this change ships the data surface
  and a working view, and the AllSpeak layer can be added without touching the
  query.
- Search is `LIKE`-based rather than `MATCH … AGAINST` despite the existing
  `ft_projects` index. At this catalogue size that is predictable and avoids
  stop-word surprises; switch to the fulltext index when the row count makes
  scanning the brief expensive.
