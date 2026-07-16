<?php
declare(strict_types=1);

namespace app\service\regulatory;

interface RegulatorySourceAdapterInterface
{
    public function supports(string $mode): bool;

    /**
     * Parse a body that has already been obtained by the caller.
     * Adapters never perform network requests.
     *
     * @return array{items: array<int, array<string, mixed>>, requires_manual_verification: bool, message: ?string}
     */
    public function parse(string $body, array $source): array;
}
