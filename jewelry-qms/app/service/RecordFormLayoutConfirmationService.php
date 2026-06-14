<?php
declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;

class RecordFormLayoutConfirmationService
{
    public const STATUSES = ['pending', 'accepted', 'needs_adjustment'];

    public static function all(int $year = 2025): array
    {
        $path = self::path($year);
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        return is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
    }

    public static function get(int $year, string $instanceId): array
    {
        $items = self::all($year);

        return is_array($items[$instanceId] ?? null) ? $items[$instanceId] : [];
    }

    public static function set(int $year, string $instanceId, string $status, string $note = '', string $user = ''): array
    {
        $instanceId = trim($instanceId);
        if ($instanceId === '') {
            throw new InvalidArgumentException('记录实例不能为空');
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('不支持的版式确认状态：' . $status);
        }

        $items = self::all($year);
        $items[$instanceId] = [
            'status' => $status,
            'note' => trim($note),
            'updated_by' => trim($user),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        self::write($year, $items);

        return $items[$instanceId];
    }

    public static function delete(int $year, string $instanceId): void
    {
        $items = self::all($year);
        unset($items[$instanceId]);
        self::write($year, $items);
    }

    private static function write(int $year, array $items): void
    {
        $path = self::path($year);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $payload = [
            'year' => $year,
            'updated_at' => date('Y-m-d H:i:s'),
            'items' => $items,
        ];
        $tmp = $path . '.tmp';
        file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        rename($tmp, $path);
    }

    private static function path(int $year): string
    {
        return root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR
            . (string)$year . DIRECTORY_SEPARATOR . 'layout-confirmations.json';
    }
}
