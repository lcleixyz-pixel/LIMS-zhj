<?php
declare(strict_types=1);

namespace app\service;

use app\model\RecordFormTemplate;
use think\facade\Db;

class RecordFormInstanceTitleService
{
    public static function suggest(RecordFormTemplate $template, int $year): array
    {
        $year = self::normalizeYear($year);
        $sequence = self::nextSequence($template, $year);
        $sequenceLabel = str_pad((string)$sequence, 3, '0', STR_PAD_LEFT);
        $docNumber = trim((string)$template->doc_number);
        $templateName = trim((string)$template->name);

        return [
            'year' => $year,
            'sequence' => $sequence,
            'sequence_label' => $sequenceLabel,
            'record_instance_number' => $docNumber . '-' . $year . '-' . $sequenceLabel,
            'record_title' => self::baseTitle($docNumber, $templateName, $year) . '-' . $sequenceLabel,
        ];
    }

    public static function baseTitle(string $docNumber, string $templateName, int $year): string
    {
        return self::normalizeYear($year) . '运行记录-' . trim($docNumber) . '-' . trim($templateName);
    }

    public static function normalizeYear(int $year): int
    {
        return max(2000, min(2100, $year));
    }

    private static function nextSequence(RecordFormTemplate $template, int $year): int
    {
        $baseTitle = self::baseTitle((string)$template->doc_number, (string)$template->name, $year);
        $rows = Db::name('record_form_instances')
            ->where('template_id', (string)$template->id)
            ->where('status', '<>', 'voided')
            ->whereLike('record_title', $baseTitle . '%')
            ->field('record_title')
            ->select()
            ->toArray();

        $max = 0;
        foreach ($rows as $row) {
            $title = (string)($row['record_title'] ?? '');
            $max = max($max, self::sequenceFromTitle($title, $baseTitle));
        }

        return $max + 1;
    }

    private static function sequenceFromTitle(string $title, string $baseTitle): int
    {
        if ($title === $baseTitle) {
            return 1;
        }

        $pattern = '/^' . preg_quote($baseTitle, '/') . '-(\d{3,})$/u';
        if (preg_match($pattern, $title, $matches) === 1) {
            return max(1, (int)$matches[1]);
        }

        return str_starts_with($title, $baseTitle) ? 1 : 0;
    }
}
