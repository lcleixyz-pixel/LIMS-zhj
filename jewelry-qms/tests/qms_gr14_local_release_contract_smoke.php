<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$composePath = $root . '/deploy/local-trial/compose.yaml';
$readmePath = $root . '/deploy/local-trial/README.md';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "[PASS] {$message}\n");
}

assertTrue(is_file($composePath), 'LT01 存在本机不可变镜像发布 Compose');
assertTrue(is_file($readmePath), 'LT02 存在本机切换与回退说明');

$compose = (string) file_get_contents($composePath);
$readme = (string) file_get_contents($readmePath);

assertTrue(
    str_contains($compose, 'image: ${QMS_IMAGE:?'),
    'LT03 发布必须显式指定不可变候选镜像'
);
assertTrue(
    !preg_match('/^\s*build\s*:/m', $compose),
    'LT04 8010 切换时禁止临时构建镜像'
);
assertTrue(
    !preg_match('/(?:^|\s)[^#\n]*:\/app(?:\s|$)/m', $compose),
    'LT05 发布 Compose 禁止把工作区源码覆盖到 /app'
);
assertTrue(
    str_contains($compose, '127.0.0.1:${QMS_HOST_PORT:-8010}:8000'),
    'LT06 应用只绑定本机回环端口'
);
assertTrue(
    str_contains($compose, 'name: jewelry-qms_default')
        && str_contains($compose, 'external: true'),
    'LT07 复用现有数据库网络但不重建数据库'
);
assertTrue(
    str_contains($compose, '${QMS_UPLOADS_DIR:?')
        && str_contains($compose, '${QMS_RUNTIME_DIR:?'),
    'LT08 上传和运行目录必须使用显式持久化路径'
);
assertTrue(
    str_contains($compose, 'APP_DEBUG: "false"')
        && str_contains($compose, 'QMS_TRIAL_MODE: "true"'),
    'LT09 服务端固定关闭调试并开启受控试运行模式'
);
assertTrue(
    str_contains($readme, '升级前快照')
        && str_contains($readme, 'current')
        && str_contains($readme, '立即回退')
        && str_contains($readme, '不得部署云端'),
    'LT10 说明覆盖快照、current 切换、立即回退和云端边界'
);

fwrite(STDOUT, "qms_gr14_local_release_contract_smoke passed\n");
