#!/usr/bin/env bash
set -euo pipefail

# G4-R12-C2 云端只读上传校验执行脚本草案
#
# 默认不执行任何远端写入。除 help/local-preflight 外，所有会触达 GitHub/服务器写入
# 或服务器状态变化的 mode 均要求设置：
#
#   export G4_R12_C2_APPROVAL="批准 G4-R12-C2"
#
# 本脚本用于降低复制命令出错概率；实际执行仍以用户批准口令和交接记录为准。

WORKTREE="/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.worktrees/g4-r12-pr31-integrated-deploy-prep"
OUT_DIR="/Users/lc.leixyz/Documents/AI工作台/05-Codex输出归档/2026-07-16-QMS云端部署准备/G4R12-PR31集成候选发布包"
BRANCH="codex/g4-r12-pr31-integrated-deploy-prep"
SERVER="root@101.200.41.200"
BASE="/www/server/jewelry-qms-experience"
RELEASE_ID="20260716-g4r12-pr31"
IMAGE="jewelry-qms-experience:amd64-g4r12-pr31"
REMOTE_DIR="/root/qms-upload-20260716-g4r12-pr31"
TREE_HASH="863cfe4e48649155f4b4294004fa976a7fbdb654"
PR_NUMBER="32"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/zhj_qms_g1_ed25519}"

SSH_OPTS=(-o StrictHostKeyChecking=yes)
SCP_OPTS=(-o StrictHostKeyChecking=yes)
if [[ -f "$SSH_KEY" ]]; then
  SSH_OPTS=(-i "$SSH_KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes)
  SCP_OPTS=(-i "$SSH_KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes)
fi

usage() {
  cat <<'USAGE'
G4-R12-C2 runbook helper

Usage:
  scripts/g4-r12-c2-runbook.sh help
  scripts/g4-r12-c2-runbook.sh local-preflight
  scripts/g4-r12-c2-runbook.sh push-docs
  scripts/g4-r12-c2-runbook.sh remote-readonly
  scripts/g4-r12-c2-runbook.sh snapshot
  scripts/g4-r12-c2-runbook.sh upload
  scripts/g4-r12-c2-runbook.sh verify-load
  scripts/g4-r12-c2-runbook.sh unpack-release
  scripts/g4-r12-c2-runbook.sh compose-check

Safety:
  - help/local-preflight do not write to GitHub or server.
  - All other modes require:
      export G4_R12_C2_APPROVAL="批准 G4-R12-C2"
  - SSH defaults to ~/.ssh/zhj_qms_g1_ed25519 when present; override with SSH_KEY=...
  - This script never switches current, never starts containers, and never edits shared .env.
  - No single "do everything" mode is provided; execute stepwise and record evidence.
USAGE
}

require_approval() {
  if [[ "${G4_R12_C2_APPROVAL:-}" != "批准 G4-R12-C2" ]]; then
    echo "Refusing to run '$1': missing approval env G4_R12_C2_APPROVAL=\"批准 G4-R12-C2\"" >&2
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
    echo "Worktree is not clean; commit or discard local changes before C2." >&2
    git status --short >&2
    exit 22
  fi
  if [[ "$(git rev-parse HEAD:jewelry-qms)" != "$TREE_HASH" ]]; then
    echo "Unexpected jewelry-qms tree hash: $(git rev-parse HEAD:jewelry-qms)" >&2
    exit 23
  fi

  gh pr view "$PR_NUMBER" --json state,isDraft,baseRefName,headRefName --jq '
    select(.state=="OPEN" and .isDraft==true and .baseRefName=="main" and .headRefName=="codex/g4-r12-pr31-integrated-deploy-prep")
  ' >/dev/null

  if [[ "$(git rev-parse HEAD)" != "$(git rev-parse "origin/$BRANCH")" ]]; then
    echo "Local HEAD differs from origin/$BRANCH. Push approved documentation commits before C2 server work." >&2
    echo "local:  $(git rev-parse HEAD)" >&2
    echo "origin: $(git rev-parse "origin/$BRANCH")" >&2
    exit 20
  fi

  cd "$OUT_DIR"
  test -f LIMS-zhj-experience-"$RELEASE_ID".tar.gz
  test -f LIMS-zhj-experience-app-image-"$RELEASE_ID".tar.gz
  test -f LIMS-zhj-experience-"$RELEASE_ID"-SHA256SUMS.txt
  test -f release-manifest-"$RELEASE_ID".json
  test -f release-manifest-"$RELEASE_ID".SHA256.txt

  shasum -a 256 -c LIMS-zhj-experience-"$RELEASE_ID"-SHA256SUMS.txt
  shasum -a 256 -c release-manifest-"$RELEASE_ID".SHA256.txt

  echo "C2 local preflight OK"
}

push_docs() {
  require_approval "push-docs"
  cd "$WORKTREE"
  git push
}

remote_readonly() {
  require_approval "remote-readonly"
  ssh "${SSH_OPTS[@]}" "$SERVER" "BASE='$BASE' bash -se" <<'REMOTE'
set -euo pipefail
hostname
date
uname -a
df -h
free -h
docker --version
docker compose version

ls -la "$BASE" || true
readlink -f "$BASE/current" || true
find "$BASE/releases" -maxdepth 1 -mindepth 1 -type d -printf '%f\n' 2>/dev/null | sort || true
find "$BASE/shared/snapshots" -maxdepth 1 -mindepth 1 -type d -printf '%f\n' 2>/dev/null | sort | tail -10 || true

if [ -d "$BASE/current/jewelry-qms" ]; then
  cd "$BASE/current/jewelry-qms"
  docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml ps
  bash deploy/experience/verify.sh "$BASE/shared/.env"
fi
REMOTE
}

snapshot() {
  require_approval "snapshot"
  ssh "${SSH_OPTS[@]}" "$SERVER" "BASE='$BASE' RELEASE_ID='$RELEASE_ID' bash -se" <<'REMOTE'
set -euo pipefail
STAMP="$(date +%Y%m%d-%H%M%S)-before-$RELEASE_ID"
SNAPSHOT="$BASE/shared/snapshots/$STAMP"
install -d -m 750 "$SNAPSHOT"
cd "$BASE/current/jewelry-qms"

docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml \
  exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' \
  < /dev/null \
  > "$SNAPSHOT/database.sql"

docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml \
  exec -T app sh -lc 'tar czf - -C /app/public uploads' \
  < /dev/null \
  > "$SNAPSHOT/uploads.tar.gz"

test -s "$SNAPSHOT/database.sql"
test -f "$SNAPSHOT/uploads.tar.gz"
sha256sum "$SNAPSHOT/database.sql" "$SNAPSHOT/uploads.tar.gz" > "$SNAPSHOT/SHA256SUMS"
chmod 600 "$SNAPSHOT/database.sql" "$SNAPSHOT/uploads.tar.gz" "$SNAPSHOT/SHA256SUMS"
sha256sum -c "$SNAPSHOT/SHA256SUMS"
echo "$SNAPSHOT"
REMOTE
}

upload() {
  require_approval "upload"
  ssh "${SSH_OPTS[@]}" "$SERVER" "install -d -m 700 '$REMOTE_DIR'"
  scp "${SCP_OPTS[@]}" "$OUT_DIR/LIMS-zhj-experience-$RELEASE_ID.tar.gz" "$SERVER:$REMOTE_DIR/"
  scp "${SCP_OPTS[@]}" "$OUT_DIR/LIMS-zhj-experience-app-image-$RELEASE_ID.tar.gz" "$SERVER:$REMOTE_DIR/"
  scp "${SCP_OPTS[@]}" "$OUT_DIR/LIMS-zhj-experience-$RELEASE_ID-SHA256SUMS.txt" "$SERVER:$REMOTE_DIR/"
  scp "${SCP_OPTS[@]}" "$OUT_DIR/release-manifest-$RELEASE_ID.json" "$SERVER:$REMOTE_DIR/"
  scp "${SCP_OPTS[@]}" "$OUT_DIR/release-manifest-$RELEASE_ID.SHA256.txt" "$SERVER:$REMOTE_DIR/"
}

verify_load() {
  require_approval "verify-load"
  ssh "${SSH_OPTS[@]}" "$SERVER" "REMOTE_DIR='$REMOTE_DIR' IMAGE='$IMAGE' bash -se" <<'REMOTE'
set -euo pipefail
cd "$REMOTE_DIR"
sha256sum -c LIMS-zhj-experience-20260716-g4r12-pr31-SHA256SUMS.txt
sha256sum -c release-manifest-20260716-g4r12-pr31.SHA256.txt
gzip -dc LIMS-zhj-experience-app-image-20260716-g4r12-pr31.tar.gz | docker load
docker image inspect "$IMAGE" --format '{{.RepoTags}} architecture={{.Architecture}} os={{.Os}} id={{.Id}}'
REMOTE
}

unpack_release() {
  require_approval "unpack-release"
  ssh "${SSH_OPTS[@]}" "$SERVER" "BASE='$BASE' RELEASE_ID='$RELEASE_ID' REMOTE_DIR='$REMOTE_DIR' bash -se" <<'REMOTE'
set -euo pipefail
if [ -e "$BASE/releases/$RELEASE_ID" ]; then
  echo "release already exists: $BASE/releases/$RELEASE_ID" >&2
  exit 12
fi

install -d -m 750 "$BASE/releases/$RELEASE_ID"
tar -xzf "$REMOTE_DIR/LIMS-zhj-experience-$RELEASE_ID.tar.gz" -C "$BASE/releases/$RELEASE_ID"
test -f "$BASE/releases/$RELEASE_ID/jewelry-qms/deploy/experience/compose.yaml"
test -x "$BASE/releases/$RELEASE_ID/jewelry-qms/deploy/experience/db-init/02-apply-migrations.sh"
install -m 640 "$REMOTE_DIR/release-manifest-$RELEASE_ID.json" "$BASE/releases/$RELEASE_ID/release-manifest-$RELEASE_ID.json"
install -m 640 "$REMOTE_DIR/release-manifest-$RELEASE_ID.SHA256.txt" "$BASE/releases/$RELEASE_ID/release-manifest-$RELEASE_ID.SHA256.txt"
cd "$BASE/releases/$RELEASE_ID"
sha256sum -c "release-manifest-$RELEASE_ID.SHA256.txt"
REMOTE
}

compose_check() {
  require_approval "compose-check"
  ssh "${SSH_OPTS[@]}" "$SERVER" "BASE='$BASE' RELEASE_ID='$RELEASE_ID' bash -se" <<'REMOTE'
set -euo pipefail
CHECK_ENV="$BASE/shared/.env.check-$RELEASE_ID"
cp "$BASE/shared/.env" "$CHECK_ENV"
chmod 600 "$CHECK_ENV"
sed -i.bak 's/^QMS_IMAGE_TAG=.*/QMS_IMAGE_TAG=amd64-g4r12-pr31/' "$CHECK_ENV"

cd "$BASE/releases/$RELEASE_ID/jewelry-qms"
docker compose --env-file "$CHECK_ENV" -f deploy/experience/compose.yaml config >/tmp/qms-compose-$RELEASE_ID.yaml
rm -f "$CHECK_ENV" "$CHECK_ENV.bak"
echo "/tmp/qms-compose-$RELEASE_ID.yaml"
REMOTE
}

mode="${1:-help}"
case "$mode" in
  help|-h|--help) usage ;;
  local-preflight) local_preflight ;;
  push-docs) push_docs ;;
  remote-readonly) remote_readonly ;;
  snapshot) snapshot ;;
  upload) upload ;;
  verify-load) verify_load ;;
  unpack-release) unpack_release ;;
  compose-check) compose_check ;;
  *)
    echo "Unknown mode: $mode" >&2
    usage >&2
    exit 2
    ;;
esac
