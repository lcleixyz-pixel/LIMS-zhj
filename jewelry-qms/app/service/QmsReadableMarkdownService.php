<?php
declare(strict_types=1);

namespace app\service;

final class QmsReadableMarkdownService
{
    /**
     * 治理解析稿常把批次、内部状态和哈希放在正文前。业务阅读时将其折叠，
     * 但完整保留在技术信息中，避免丢失追溯证据。
     *
     * @return array{body:string,technical:string}
     */
    public static function separateGovernancePreamble(string $markdown): array
    {
        $normalized = str_replace("\0", '', $markdown);
        $lines = preg_split('/\R/u', $normalized) ?: [];
        $dividerIndex = null;
        foreach ($lines as $index => $line) {
            if (trim((string)$line) === '---') {
                $dividerIndex = $index;
                break;
            }
        }

        if ($dividerIndex === null || $dividerIndex > 60) {
            return ['body' => $normalized, 'technical' => ''];
        }

        $preamble = trim(implode("\n", array_slice($lines, 0, $dividerIndex)));
        $hasGovernanceMetadata = str_contains($preamble, 'SHA-256')
            || str_contains($preamble, '生成批次：')
            || str_contains($preamble, '文件状态：');
        if (!$hasGovernanceMetadata) {
            return ['body' => $normalized, 'technical' => ''];
        }

        $body = trim(implode("\n", array_slice($lines, $dividerIndex + 1)));
        if ($body === '') {
            return ['body' => $normalized, 'technical' => ''];
        }

        return ['body' => $body, 'technical' => $preamble];
    }

    public static function toHtml(string $markdown): string
    {
        $lines = preg_split('/\R/u', str_replace("\0", '', $markdown)) ?: [];
        $html = [];
        $paragraph = [];
        $listType = '';
        $inFence = false;
        $fence = [];
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $line = rtrim((string)$lines[$index]);

            if (preg_match('/^\s*```/', $line) === 1) {
                self::flushParagraph($html, $paragraph);
                self::closeList($html, $listType);
                if ($inFence) {
                    $html[] = '<pre class="qms-readable-code"><code>'
                        . htmlspecialchars(implode("\n", $fence), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '</code></pre>';
                    $fence = [];
                    $inFence = false;
                } else {
                    $inFence = true;
                }
                continue;
            }
            if ($inFence) {
                $fence[] = $line;
                continue;
            }

            if ($line === '') {
                self::flushParagraph($html, $paragraph);
                self::closeList($html, $listType);
                continue;
            }

            if (
                str_contains($line, '|')
                && isset($lines[$index + 1])
                && self::isTableDivider((string)$lines[$index + 1])
            ) {
                self::flushParagraph($html, $paragraph);
                self::closeList($html, $listType);
                $headers = self::tableCells($line);
                $index += 2;
                $rows = [];
                while ($index < $count && trim((string)$lines[$index]) !== '' && str_contains((string)$lines[$index], '|')) {
                    $rows[] = self::tableCells((string)$lines[$index]);
                    $index++;
                }
                $index--;
                $table = ['<div class="table-responsive"><table class="table table-bordered table-sm qms-readable-table"><thead><tr>'];
                foreach ($headers as $header) {
                    $table[] = '<th>' . self::inline($header) . '</th>';
                }
                $table[] = '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    $table[] = '<tr>';
                    foreach ($headers as $cellIndex => $_header) {
                        $table[] = '<td>' . self::inline((string)($row[$cellIndex] ?? '')) . '</td>';
                    }
                    $table[] = '</tr>';
                }
                $table[] = '</tbody></table></div>';
                $html[] = implode('', $table);
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/u', $line, $match) === 1) {
                self::flushParagraph($html, $paragraph);
                self::closeList($html, $listType);
                $level = strlen($match[1]);
                $html[] = '<h' . $level . '>' . self::inline($match[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^\s*>\s?(.*)$/u', $line, $match) === 1) {
                self::flushParagraph($html, $paragraph);
                self::closeList($html, $listType);
                $html[] = '<blockquote>' . self::inline($match[1]) . '</blockquote>';
                continue;
            }

            if (preg_match('/^\s*[-*]\s+(.+)$/u', $line, $match) === 1) {
                self::flushParagraph($html, $paragraph);
                self::openList($html, $listType, 'ul');
                $html[] = '<li>' . self::inline($match[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+(.+)$/u', $line, $match) === 1) {
                self::flushParagraph($html, $paragraph);
                self::openList($html, $listType, 'ol');
                $html[] = '<li>' . self::inline($match[1]) . '</li>';
                continue;
            }

            self::closeList($html, $listType);
            $paragraph[] = $line;
        }

        if ($inFence) {
            $html[] = '<pre class="qms-readable-code"><code>'
                . htmlspecialchars(implode("\n", $fence), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</code></pre>';
        }
        self::flushParagraph($html, $paragraph);
        self::closeList($html, $listType);

        return implode("\n", $html);
    }

    private static function inline(string $text): string
    {
        $escaped = htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;

        return $escaped;
    }

    private static function flushParagraph(array &$html, array &$paragraph): void
    {
        if ($paragraph === []) {
            return;
        }
        $lines = array_map([self::class, 'inline'], $paragraph);
        $html[] = '<p>' . implode('<br>', $lines) . '</p>';
        $paragraph = [];
    }

    private static function openList(array &$html, string &$current, string $wanted): void
    {
        if ($current === $wanted) {
            return;
        }
        self::closeList($html, $current);
        $html[] = '<' . $wanted . '>';
        $current = $wanted;
    }

    private static function closeList(array &$html, string &$current): void
    {
        if ($current === '') {
            return;
        }
        $html[] = '</' . $current . '>';
        $current = '';
    }

    private static function isTableDivider(string $line): bool
    {
        $cells = self::tableCells($line);
        if ($cells === []) {
            return false;
        }
        foreach ($cells as $cell) {
            if (preg_match('/^:?-{3,}:?$/', trim($cell)) !== 1) {
                return false;
            }
        }

        return true;
    }

    private static function tableCells(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');

        return array_map('trim', explode('|', $line));
    }
}
