<?php
declare(strict_types=1);

$layout = (string)file_get_contents(dirname(__DIR__) . '/app/view/layout/main.html');

function flash_message_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'success' => 'successMessage',
    'warning' => 'warningMessage',
    'error' => 'errorMessage',
] as $sessionKey => $templateVariable) {
    flash_message_ui_assert(
        str_contains($layout, '$' . $templateVariable . " = session('" . $sessionKey . "')"),
        $sessionKey . ' flash message must be read into a renderable template variable'
    );
    flash_message_ui_assert(
        str_contains($layout, '{$' . $templateVariable . '}'),
        $sessionKey . ' flash message must render the captured message text'
    );
}

flash_message_ui_assert(
    !str_contains($layout, '$Think.session.'),
    'Think.session flash expressions compile to empty literals and must not be used'
);

echo "qms_flash_message_ui_smoke passed\n";
