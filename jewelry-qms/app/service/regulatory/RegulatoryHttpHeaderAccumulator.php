<?php
declare(strict_types=1);

namespace app\service\regulatory;

final class RegulatoryHttpHeaderAccumulator
{
    private array $headers = [];
    private bool $acceptingHeaders = false;

    public function consume(string $line): int
    {
        $length = strlen($line);
        $trimmed = rtrim($line, "\r\n");

        if (preg_match('/^HTTP\/\S+\s+\d{3}(?:\s|$)/i', $trimmed) === 1) {
            $this->headers = [];
            $this->acceptingHeaders = true;
            return $length;
        }

        if ($trimmed === '') {
            $this->acceptingHeaders = false;
            return $length;
        }

        if (!$this->acceptingHeaders || !str_contains($trimmed, ':')) {
            return $length;
        }

        [$name, $value] = explode(':', $trimmed, 2);
        $name = strtolower(trim($name));
        if ($name !== '') {
            $this->headers[$name][] = trim($value);
        }

        return $length;
    }

    public function headers(): array
    {
        return $this->headers;
    }
}
