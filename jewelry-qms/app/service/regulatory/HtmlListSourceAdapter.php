<?php
declare(strict_types=1);

namespace app\service\regulatory;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

final class HtmlListSourceAdapter implements RegulatorySourceAdapterInterface
{
    public function supports(string $mode): bool
    {
        return $mode === 'html_list';
    }

    public function parse(string $body, array $source): array
    {
        if (!$this->supports((string)($source['mode'] ?? ''))) {
            throw new RuntimeException('来源模式与 HTML 列表适配器不匹配');
        }
        if ($body === '') {
            throw new RuntimeException('HTML 正文不能为空');
        }

        $itemXPath = trim((string)($source['item_xpath'] ?? ''));
        if ($itemXPath === '') {
            throw new RuntimeException('HTML 来源缺少条目 XPath 配置');
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($loaded !== true) {
            throw new RuntimeException('HTML 正文无法解析');
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query($itemXPath);
        if ($nodes === false) {
            throw new RuntimeException('HTML 来源条目 XPath 无效');
        }
        if ($nodes->length === 0) {
            throw new RuntimeException('HTML 来源列表结构未命中，可能发生页面结构漂移');
        }

        $items = [];
        $seenUrls = [];
        foreach ($nodes as $node) {
            $item = $this->parseItem($xpath, $node, $source);
            if ($item !== null && !isset($seenUrls[$item['canonical_url']])) {
                $seenUrls[$item['canonical_url']] = true;
                $items[] = $item;
            }
        }
        if ($items === []) {
            throw new RuntimeException('HTML 来源列表存在但未解析出有效条目');
        }

        return [
            'items' => $items,
            'requires_manual_verification' => false,
            'message' => null,
        ];
    }

    private function parseItem(DOMXPath $xpath, DOMNode $node, array $source): ?array
    {
        $link = $xpath->query('(.//a[@href])[1]', $node)?->item(0);
        if (!$link instanceof DOMElement) {
            return null;
        }

        $title = $this->normalizedText($link->getAttribute('title'));
        if ($title === '') {
            $title = $this->normalizedText($link->textContent);
        }
        $href = trim($link->getAttribute('href'));
        if ($title === '' || $href === '') {
            return null;
        }

        $canonicalUrl = RegulatoryUrlNormalizer::normalize(
            $href,
            (array)($source['allowed_hosts'] ?? []),
            (string)$source['entry_url']
        );

        $dateNode = $xpath->query(
            '(.//time[@datetime] | .//*[contains(concat(" ", normalize-space(@class), " "), " date ")] | .//*[contains(concat(" ", normalize-space(@class), " "), " time ")])[1]',
            $node
        )?->item(0);
        $publishedDate = null;
        if ($dateNode instanceof DOMElement && $dateNode->hasAttribute('datetime')) {
            $publishedDate = $this->normalizeDate($dateNode->getAttribute('datetime'));
        } elseif ($dateNode instanceof DOMNode) {
            $publishedDate = $this->normalizeDate($dateNode->textContent);
        }

        $numberNode = $xpath->query(
            '(.//*[contains(concat(" ", normalize-space(@class), " "), " announcement-number ")])[1]',
            $node
        )?->item(0);
        $announcementNumber = $numberNode instanceof DOMNode
            ? $this->normalizedText($numberNode->textContent)
            : '';

        $summaryNode = $xpath->query(
            '(.//*[contains(concat(" ", normalize-space(@class), " "), " summary ")])[1]',
            $node
        )?->item(0);
        $summary = $summaryNode instanceof DOMNode
            ? $this->normalizedText($summaryNode->textContent)
            : $this->normalizedText($node->textContent);
        $rawText = $this->normalizedText($node->textContent);

        return [
            'title' => $title,
            'canonical_url' => $canonicalUrl,
            'published_date' => $publishedDate,
            'announcement_number' => $announcementNumber !== '' ? $announcementNumber : null,
            'summary' => $summary,
            'evidence' => [
                'source_key' => (string)($source['key'] ?? ''),
                'entry_url' => (string)$source['entry_url'],
                'raw_text' => $rawText,
            ],
        ];
    }

    private function normalizeDate(string $value): ?string
    {
        if (preg_match('/(?<!\d)(\d{4})[-\/.年](\d{1,2})[-\/.月](\d{1,2})(?:日)?(?!\d)/u', $value, $matches) !== 1) {
            return null;
        }

        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function normalizedText(string $value): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', $value));
    }
}
