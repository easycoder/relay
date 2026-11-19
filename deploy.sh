#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
deploy.sh — push Relay to relay.eclecity.net.

Server layout (current DreamHost vhost):
  /home/eclecity/relay.eclecity.net/          repository + web root
  /home/eclecity/relay.eclecity.net/public/   public tree
  /home/eclecity/relay.eclecity.net.txt       config (above both)

The root index.php serves public/index.php, and .htaccess denies source paths.
This script never uploads the config file.

Usage:
  ./deploy.sh                        deploy using deploy.conf
  ./deploy.sh --build                deploy, then run scripts/build.php on the server
  ./deploy.sh user@host:/path        deploy to another repository path
  ./deploy.sh /path/to/repo          copy to a local directory (testing)
  ./deploy.sh --delete               mirror, removing stale files
  -h | --help                        show this help

Environment:
  DEPLOY_DRY_RUN=1   print the rsync command without running it
  DEPLOY_PORT=<n>    ssh port

deploy.conf (gitignored):
  DEPLOY_USER, DEPLOY_HOST, DEPLOY_PATH, optionally DEPLOY_PORT
EOF
}

DELETE=0
BUILD=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        -h|--help|help)
            usage
            exit 0
            ;;
        --delete)
            DELETE=1
            shift
            ;;
        --build)
            BUILD=1
            shift
            ;;
        --)
            shift
            break
            ;;
        -*)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
        *)
            break
            ;;
    esac
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Optional per-machine connection details (never committed).
if [[ -f deploy.conf ]]; then
    # shellcheck disable=SC1091
    source deploy.conf
fi

TARGET="${1:-}"
if [[ $# -gt 1 ]]; then
    echo "Unexpected argument: $2" >&2
    echo "Put --delete before the target." >&2
    exit 2
fi
if [[ -z "$TARGET" && -n "${RELAY_DEPLOY_DIR:-}" ]]; then
    TARGET="$RELAY_DEPLOY_DIR"
fi
if [[ -z "$TARGET" ]]; then
    if [[ -n "${DEPLOY_USER:-}" && -n "${DEPLOY_HOST:-}" && -n "${DEPLOY_PATH:-}" ]]; then
        DEPLOY_PATH="${DEPLOY_PATH%/}"
        TARGET="$DEPLOY_USER@$DEPLOY_HOST:$DEPLOY_PATH"
    else
        echo "No target given and no connection details configured." >&2
        echo "Copy deploy.conf.example to deploy.conf and set DEPLOY_USER, DEPLOY_HOST and DEPLOY_PATH." >&2
        exit 2
    fi
fi

# Never upload secrets, local-only data, or the deploy machinery itself.
EXCLUDES=(
    --exclude='.git/'
    --exclude='.env'
    --exclude='.env.*'
    --exclude='config.php'
    --exclude='config.json'
    --exclude='*.eclecity.net.txt'
    --exclude='deploy.conf'
    --exclude='deploy.sh'
    --exclude='db/seed.sql'
    --exclude='allspeak-en/'
    --exclude='vendor/'
    --exclude='node_modules/'
    --exclude='*.log'

    # Generated on the server by scripts/build.php; never upload or delete
    # them with a code deploy.
    --exclude='public/home.html'
    --exclude='public/projects/'
    --exclude='public/projects.json'
    --exclude='public/sitemap.xml'
    --exclude='public/robots.txt'
    --exclude='public/learn/'
    --exclude='public/about/'
)

RSYNC=(rsync -rztvz --no-perms)
if (( DELETE )); then
    RSYNC+=(--delete)
fi

run_rsync() {
    local dest="$1"

    if [[ -n "${DEPLOY_DRY_RUN:-}" ]]; then
        printf 'Would run:'
        printf ' %q' "${RSYNC[@]}" "${EXCLUDES[@]}" "$SCRIPT_DIR/" "$dest/"
        printf '\n'
        return 0
    fi

    "${RSYNC[@]}" "${EXCLUDES[@]}" "$SCRIPT_DIR/" "$dest/"
}

if [[ "$TARGET" != *:* ]]; then
    if [[ -z "${DEPLOY_DRY_RUN:-}" ]]; then
        mkdir -p "$TARGET"
    fi
    echo "Deploying Relay to $TARGET"
    run_rsync "$TARGET"
    DEST_PATH="$TARGET"
    IS_REMOTE=0
else
    SSH_CMD=(ssh)
    if [[ -n "${DEPLOY_PORT:-}" ]]; then
        SSH_CMD+=(-p "$DEPLOY_PORT")
    fi
    RSYNC+=(-e "${SSH_CMD[*]}")

    echo "Deploying Relay to $TARGET"
    run_rsync "$TARGET"
    DEST_PATH="${TARGET#*:}"
    IS_REMOTE=1
fi

if [[ -n "${DEPLOY_DRY_RUN:-}" ]]; then
    echo
    echo "Dry run complete — nothing was uploaded."
    exit 0
fi

if (( BUILD )); then
    echo
    echo "Building the catalogue..."
    if (( IS_REMOTE )); then
        REMOTE_HOST="${TARGET%%:*}"
        REMOTE_PATH="${TARGET#*:}"
        "${SSH_CMD[@]}" "$REMOTE_HOST" "cd '$REMOTE_PATH' && php scripts/build.php"
    elif command -v php >/dev/null 2>&1; then
        php "$DEST_PATH/scripts/build.php"
    else
        echo "warning: php not found; run php scripts/build.php in $DEST_PATH" >&2
    fi
fi

if (( IS_REMOTE )); then
    cat <<EOF

Done.

Reminders:
- Web root: ${DEST_PATH} (the repository root). The root index.php serves
  public/index.php, and .htaccess denies source paths. The preferred
  ${DEST_PATH}/public Web Directory is not served by the current DreamHost
  vhost, so the root layout is in use.
- Config: /home/eclecity/relay.eclecity.net.txt, one level above the
  repository. This script never uploads it.
- Database check, from the server:
    cd ${DEST_PATH} && php scripts/db-check.php
- Build the catalogue after content or template changes:
    cd ${DEST_PATH} && php scripts/build.php
  or run ./deploy.sh --build next time.
- Apply any new db/migrations/*.sql before --build; the build may depend on
  the new columns.
EOF
else
    echo
    echo "Local copy complete: $DEST_PATH"
fi
