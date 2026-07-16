#!/usr/bin/env bash
set -euo pipefail

# G4-R12-C3 切换验证与回退执行辅助脚本
#
# 默认只允许 help/local-preflight/remote-preflight-readonly。
# 任何会切换 current、修改 shared .env、启动容器或回退的 mode 均要求：
#
#   export G4_R12_C3_APPROVAL="批准 G4-R12-C3"
#
# 本脚本只用于降低复制命令出错概率；实际执行仍以用户批准口令和交接记录为准。

WORKTREE="/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.worktrees/g4-r12-pr31-integrated-deploy-prep"
BRANCH="codex/g4-r12-pr31-integrated-deploy-prep"
SERVER="root@101.200.41.200"
BASE="/www/server/jewelry-qms-experience"
RELEASE_ID="20260716-g4r12-pr31"
IMAGE="jewelry-qms-experience:amd64-g4r12-pr31"
TREE_HASH="863cfe4e48649155f4b4294004fa976a7fbdb654"
PR_NUMBER="32"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/zhj_qms_g1_ed25519}"

SSH_OPTS=(-o StrictHostKeyChecking=yes)
if [[ -f "$SSH_KEY" ]]; then
  SSH_OPTS=(-i "$SSH_KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes)
fi

usage() {
  cat <<'USAGE'
G4-R12-C3 runbook helper

Usage:
  scripts/g4-r12-c3-runbook.sh help
  scripts/g4-r12-c3-runbook.sh local-preflight
  scripts/g4-r12-c3-runbook.sh remote-preflight-readonly
  scripts/g4-r12-c3-runbook.sh switch-and-verify
  scripts/g4-r12-c3-runbook.sh post-verify-readonly
  scripts/g4-r12-c3-runbook.sh rollback

Safety:
  - help/local-preflight/remote-preflight-readonly do not change server state.
  - switch-and-verify and rollback require:
      export G4_R12_C3_APPROVAL="批准 G4-R12-C3"
  - SSH defaults to ~/.ssh/zhj_qms_g1_ed25519 when present; override with SSH_KEY=...
  - This script never deletes volumes, snapshots, releases, or upload packages.
  - This script never enables REGULATORY_MONITOR_ENABLED and never imports data.
USAGE
}

require_approval() {
  if [[ "${G4_R12_C3_APPROVAL:-}" != "批准 G4-R12-C3" ]]; then
    echo "Refusing to run '$1': missing approval env G4_R12_C3_APPROVAL=\"批准 G4-R12-C3\"" >&2
    exit 10
  fi
}

local_preflight() {
  cd "$WORKTREE"
  if [[ "$(git branch --show-current)" != "$BRANCH" ]]; then
    echo "Wrong branch: expected $BRANCH, got $(git branch --show-current)" >&2
    exit 21
  fi
  if [[ -n "$(git status --porcelain)" ]]; then
    echo "Worktree is not clean; commit or discard local changes before C3." >&2
    git status --short >&2
    exit 22
  fi
  if [[ "$(git rev-parse HEAD:jewelry-qms)" != "$TREE_HASH" ]]; then
    echo "Unexpected jewelry-qms tree hash: $(git rev-parse HEAD:jewelry-qms)" >&2
    exit 23
  fi
  if [[ "$(git rev-parse HEAD)" != "$(git rev-parse "origin/$BRANCH")" ]]; then
    echo "Local HEAD differs from origin/$BRANCH. Push approved handoff commits before C3." >&2
    echo "local:  $(git rev-parse HEAD)" >&2
    echo "origin: $(git rev-parse "origin/$BRANCH")" >&2
    exit 20
  fi

  gh pr view "$PR_NUMBER" --json state,isDraft,baseRefName,headRefName,mergeStateStatus --jq '
    select(.state=="OPEN" and .isDraft==true and .baseRefName=="main" and .headRefName=="codex/g4-r12-pr31-integrated-deploy-prep" and .mergeStateStatus=="CLEAN")
  ' >/dev/null

  echo "C3 local preflight OK"
}

remote_preflight_readonly() {
  ssh "${SSH_OPTS[@]}" "$SERVER" "BASE='$BASE' RELEASE_ID='$RELEASE_ID' IMAGE='$IMAGE' bash -se" <<'REMOTE'
set -euo pipefail

CURRENT="$(readlink -f "$BASE/current")"
echo "current=$CURRENT"
echo "target=$BASE/releases/$RELEASE_ID"
test "$CURRENT" != "$BASE/releases/$RELEASE_ID"
test -d "$BASE/releases/$RELEASE_ID"
test -f "$BASE/releases/$RELEASE_ID/jewelry-qms/deploy/experience/compose.yaml"
test -x "$BASE/releases/$RELEASE_ID/jewelry-qms/deploy/experience/verify.sh"
test -x "$BASE/releases/$RELEASE_ID/jewelry-qms/deploy/experience/db-init/02-apply-migrations.sh"

docker image inspect "$IMAGE" --format 'image={{.Architecture}}/{{.Os}} {{.Id}}'

SNAPSHOT="$(find "$BASE/shared/snapshots" -maxdepth 1 -mindepth 1 -type d -name '*before-20260716-g4r12-pr31-complete' -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)"
test -n "$SNAPSHOT"
echo "snapshot=$SNAPSHOT"
sha256sum -c "$SNAPSHOT/SHA256SUMS"

test ! -e "$BASE/shared/.env.check-$RELEASE_ID"
test ! -e "$BASE/shared/.env.check-$RELEASE_ID.bak"

cd "$BASE/current/jewelry-qms"
bash deploy/experience/verify.sh "$BASE/shared/.env"

echo "C3 remote read-only preflight OK"
REMOTE
}

switch_and_verify() {
  require_approval "switch-and-verify"
  ssh "${SSH_OPTS[@]}" "$SERVER" "BASE='$BASE' RELEASE_ID='$RELEASE_ID' IMAGE='$IMAGE' bash -se" <<'REMOTE'
set -euo pipefail

PREVIOUS_FILE="$BASE/shared/previous-release-before-$RELEASE_ID.txt"
ENV_BACKUP="$BASE/shared/.env.before-$RELEASE_ID"
CURRENT="$(readlink -f "$BASE/current")"
TARGET="$BASE/releases/$RELEASE_ID"

test "$CURRENT" != "$TARGET"
test -d "$CURRENT"
test -d "$TARGET"
test -f "$TARGET/jewelry-qms/deploy/experience/compose.yaml"
test -x "$TARGET/jewelry-qms/deploy/experience/verify.sh"
test -x "$TARGET/jewelry-qms/deploy/experience/db-init/02-apply-migrations.sh"
docker image inspect "$IMAGE" --format 'image={{.Architecture}}/{{.Os}} {{.Id}}'

if [ -e "$ENV_BACKUP" ]; then
  echo "Refusing to overwrite existing env backup: $ENV_BACKUP" >&2
  exit 12
fi

echo "$CURRENT" > "$PREVIOUS_FILE"
chmod 600 "$PREVIOUS_FILE"
cp "$BASE/shared/.env" "$ENV_BACKUP"
chmod 600 "$ENV_BACKUP"

sed -i 's/^QMS_IMAGE_TAG=.*/QMS_IMAGE_TAG=amd64-g4r12-pr31/' "$BASE/shared/.env"
grep '^QMS_IMAGE_TAG=amd64-g4r12-pr31$' "$BASE/shared/.env"
if grep -q '^REGULATORY_MONITOR_ENABLED=' "$BASE/shared/.env"; then
  grep '^REGULATORY_MONITOR_ENABLED=0$' "$BASE/shared/.env"
fi

ln -sfn "$TARGET" "$BASE/current"
cd "$BASE/current/jewelry-qms"
docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml up -d --pull never
bash deploy/experience/verify.sh "$BASE/shared/.env"

echo "previous=$(cat "$PREVIOUS_FILE")"
echo "current=$(readlink -f "$BASE/current")"
docker ps --format '{{.Names}} {{.Image}}'
REMOTE
}

post_verify_readonly() {
  ssh "${SSH_OPTS[@]}" "$SERVER" "BASE='$BASE' RELEASE_ID='$RELEASE_ID' IMAGE='$IMAGE' bash -se" <<'REMOTE'
set -euo pipefail
echo "current=$(readlink -f "$BASE/current")"
grep '^QMS_IMAGE_TAG=' "$BASE/shared/.env"
cd "$BASE/current/jewelry-qms"
bash deploy/experience/verify.sh "$BASE/shared/.env"
docker ps --format '{{.Names}} {{.Image}}'
curl -sS -o /dev/null -w 'login_http=%{http_code}\n' http://127.0.0.1:18010/login/index
REMOTE
}

rollback() {
  require_approval "rollback"
  ssh "${SSH_OPTS[@]}" "$SERVER" "BASE='$BASE' RELEASE_ID='$RELEASE_ID' bash -se" <<'REMOTE'
set -euo pipefail

PREVIOUS_FILE="$BASE/shared/previous-release-before-$RELEASE_ID.txt"
ENV_BACKUP="$BASE/shared/.env.before-$RELEASE_ID"
test -f "$PREVIOUS_FILE"
PREVIOUS_RELEASE="$(cat "$PREVIOUS_FILE")"
test -d "$PREVIOUS_RELEASE"

ln -sfn "$PREVIOUS_RELEASE" "$BASE/current"

if [ -f "$ENV_BACKUP" ]; then
  cp "$ENV_BACKUP" "$BASE/shared/.env"
  chmod 600 "$BASE/shared/.env"
fi

cd "$BASE/current/jewelry-qms"
docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml up -d --pull never
bash deploy/experience/verify.sh "$BASE/shared/.env"

echo "rolled_back_to=$(readlink -f "$BASE/current")"
docker ps --format '{{.Names}} {{.Image}}'
REMOTE
}

mode="${1:-help}"
case "$mode" in
  help|-h|--help) usage ;;
  local-preflight) local_preflight ;;
  remote-preflight-readonly) remote_preflight_readonly ;;
  switch-and-verify) switch_and_verify ;;
  post-verify-readonly) post_verify_readonly ;;
  rollback) rollback ;;
  *)
    echo "Unknown mode: $mode" >&2
    usage >&2
    exit 2
    ;;
esac
