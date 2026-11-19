# Deploy to eclecity@eclecity.net

**Action:** confirm your SSH key is accepted on `eclecity@eclecity.net`, then run
`./deploy.sh` (or `./deploy.sh --build`). Both the local `deploy.conf` and the
example now name `eclecity.net` as the host.

## What changed

- `DEPLOY_HOST` is `eclecity.net` in `deploy.conf` and `deploy.conf.example`.
  The user and path are unchanged: `eclecity` and
  `/home/eclecity/relay.eclecity.net`.
- The README's post-deploy `ssh` check uses `eclecity@eclecity.net`.

## Notes

- `deploy.conf` is machine-local and gitignored; the repository change is the
  example file and the README. The public site is still
  `relay.eclecity.net`.
