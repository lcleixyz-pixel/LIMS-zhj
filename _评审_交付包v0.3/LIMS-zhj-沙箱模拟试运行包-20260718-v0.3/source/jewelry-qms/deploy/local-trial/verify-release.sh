#!/usr/bin/env bash
set -euo pipefail

: "${QMS_IMAGE_ID:?QMS_IMAGE_ID is required}"
: "${QMS_MIGRATION_SHA256:?QMS_MIGRATION_SHA256 is required}"

actual_image_id="$(docker image inspect "$QMS_IMAGE_ID" --format '{{.Id}}')"
if [[ "$actual_image_id" != "$QMS_IMAGE_ID" ]]; then
  echo "候选镜像 ID 不匹配：expected=$QMS_IMAGE_ID actual=$actual_image_id" >&2
  exit 1
fi

if [[ ! "$QMS_IMAGE_ID" =~ ^sha256:[0-9a-f]{64}$ ]]; then
  echo "QMS_IMAGE_ID 必须是完整 sha256 镜像 ID" >&2
  exit 1
fi

migration="${QMS_MIGRATION_FILE:-database/migrations/20260717_gr14_controlled_trial.sql}"
actual_migration_sha="$(shasum -a 256 "$migration" | awk '{print $1}')"
if [[ "$actual_migration_sha" != "$QMS_MIGRATION_SHA256" ]]; then
  echo "迁移文件 SHA256 不匹配" >&2
  exit 1
fi

echo "本机候选 release 校验通过：$actual_image_id"
