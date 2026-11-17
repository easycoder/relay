# Accounts are created when the link is used, not when it is requested

**Action:** apply the migration, then deploy and reload.

```sh
mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
  < db/migrations/0005_add_magic_link_display_name.sql
```

## What changed

- **Requesting a link creates nothing.** `public/login/index.php` no longer
  calls `create_user()`; a POST for any address writes one `magic_links` row and
  sends the mail. An address nobody can read therefore leaves no `users` row
  behind, which removes the row-flooding route.
- **The name travels with the link.** New column `magic_links.display_name`
  (`0005`). `/login/verify/` calls `find_or_create_user($email, $displayName)`
  and creates the account at that point, with the chosen name; an existing
  address keeps its stored name and just has `verified_at` set.
- `create_magic_link()` takes an optional display name; `consume_magic_link()`
  returns it. `php scripts/login-link.php <email> "Name"` now records the name
  on the link instead of creating the user itself — it still asks for a name
  when the address is new.
- The login form's hint says the account is created when the link is used.

## Notes

- Existing unverified rows are untouched; `0005` prints their count so you can
  see whether any accumulated:
  `SELECT COUNT(*) FROM users WHERE verified_at IS NULL;`
- Nothing else reads `users` before a session exists, so the rest of the flow
  (submission caps, strikes, resubmission limit) is unchanged.
- `db/schema.sql` and `db/README.md` carry the new column and migration.
