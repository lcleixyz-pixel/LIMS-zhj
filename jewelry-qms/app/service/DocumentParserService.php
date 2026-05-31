<?php
declare(strict_types=1);

namespace app\service;

use ZipArchive;

class DocumentParserService
{
    public static function extractText(string $filePath): string
    {
        if (!is_file($filePath)) {
            throw new \InvalidArgumentException('文件不存在：' . $filePath);
        }

        $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
        return match ($ext) {
            'docx' => self::extractDocxText($filePath),
            'txt', 'md' => trim((string)file_get_contents($filePath)),
            'doc' => self::extractLegacyDocText($filePath),
            default => throw new \InvalidArgumentException('暂不支持该文件格式：' . $ext),
        };
    }

    public static function extractTables(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [];
        }

        $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext !== 'docx') {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        $xmlString = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xmlString === false) {
            return [];
        }

        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return [];
        }

        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $tables = [];
        foreach ($xml->xpath('//w:tbl') ?: [] as $tableNode) {
            $rows = [];
            foreach ($tableNode->xpath('.//w:tr') ?: [] as $rowNode) {
                $cells = [];
                foreach ($rowNode->xpath('.//w:tc') ?: [] as $cellNode) {
                    $parts = [];
                    foreach ($cellNode->xpath('.//w:t') ?: [] as $textNode) {
                        $parts[] = (string)$textNode;
                    }
                    $cells[] = trim(implode('', $parts));
                }
                if ($cells !== []) {
                    $rows[] = $cells;
                }
            }
            if ($rows !== []) {
                $tables[] = $rows;
            }
        }

        return $tables;
    }

    public static function summarizeForAi(string $filePath): string
    {
        $text = self::extractText($filePath);
        $tables = self::extractTables($filePath);
        $parts = [
            '文件名：' . basename($filePath),
            '',
            '正文：',
            self::truncate($text, 12000),
        ];

        if ($tables !== []) {
            $parts[] = '';
            $parts[] = '表格：';
            foreach ($tables as $index => $table) {
                $parts[] = '表' . ($index + 1) . ':';
                foreach ($table as $row) {
                    $parts[] = implode(' | ', $row);
                }
            }
        }

        return implode("\n", $parts);
    }

    private static function extractDocxText(string $filePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('无法打开 docx 文件');
        }

        $xmlString = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xmlString === false) {
            return '';
        }

        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return '';
        }

        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $parts = [];
        foreach ($xml->xpath('//w:t') ?: [] as $node) {
            $value = trim((string)$node);
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $parts)) ?: '');
    }

    private static function extractLegacyDocText(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false || $content === '') {
            return '';
        }

        $chunks = [];
        if (preg_match_all('/[\x09\x0A\x0D\x20-\x7E\x{4e00}-\x{9fff}]{6,}/u', $content, $matches)) {
            foreach ($matches[0] as $chunk) {
                $chunk = trim($chunk);
                if ($chunk !== '' && !str_starts_with($chunk, 'Microsoft')) {
                    $chunks[] = $chunk;
                }
            }
        }

        return self::truncate(implode("\n", array_unique($chunks)), 12000);
    }

    private static function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . "\n...(内容已截断)";
    }
}
