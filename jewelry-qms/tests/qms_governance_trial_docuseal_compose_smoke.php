<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$compose = (string)file_get_contents($root . '/compose.governance-trial.yaml');

function governance_docuseal_compose_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'docuseal:',
    '127.0.0.1:3101:3000',
    'governance_trial_docuseal_data:',
    'DOCUSEAL_BASE_URL: http://docuseal:3000',
    'DOCUSEAL_PUBLIC_BASE_URL: http://127.0.0.1:3101',
    'DOCUSEAL_SEND_EMAIL: "0"',
    'docker/.env.governance-trial.signing',
    '20260720_docuseal_signing.sql',
    '20260724_document_approval_round.sql',
] as $required) {
    governance_docuseal_compose_assert(
        str_contains($compose, $required),
        'governance trial compose missing: ' . $required
    );
}

governance_docuseal_compose_assert(
    !str_contains($compose, '127.0.0.1:3100:3000'),
    'governance trial must not reuse the existing 3100 DocuSeal port'
);

echo "qms_governance_trial_docuseal_compose_smoke passed\n";

