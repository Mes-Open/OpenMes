<?php

namespace App\Services\Connectivity;

class MqttMessageParser
{
    /**
     * Parse raw MQTT payload according to the declared format.
     *
     * Returns an associative array on success, or ['_raw' => $payload] as fallback.
     */
    public function parse(string $payload, string $format): array
    {
        return match ($format) {
            'json'  => $this->parseJson($payload),
            'plain' => ['value' => $payload],
            'csv'   => $this->parseCsv($payload),
            'hex'   => ['hex' => $payload, 'bytes' => $this->hexToBytes($payload)],
            default => ['_raw' => $payload],
        };
    }

    /**
     * Resolve a field path from parsed data.
     *
     * Supports:
     *  - JSONPath: $.field, $.nested.field, $.arr.0
     *  - Literal (no $): returned as-is
     *  - Empty / null: returns entire parsed data
     */
    public function resolvePath(?string $path, array $data): mixed
    {
        if (empty($path)) {
            return $data;
        }

        if (!str_starts_with($path, '$')) {
            return $path; // literal value
        }

        if ($path === '$') {
            return $data;
        }

        $keys = explode('.', substr($path, 2)); // strip '$.'

        $value = $data;
        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Evaluate a simple condition expression.
     *
     * Supported operators: ==, !=, >, >=, <, <=, contains
     * Format: "<value> <operator> <literal>"
     * Example: "5 > 0"  or  "active == true"
     *
     * The $resolvedValue is the already-resolved field value from payload.
     */
    public function evaluateCondition(?string $expression, mixed $resolvedValue): bool
    {
        if (empty($expression)) {
            return true;
        }

        // Parse "value <op> literal"
        $pattern = '/^value\s*(==|!=|>=|<=|>|<|contains)\s*(.+)$/';
        if (!preg_match($pattern, trim($expression), $matches)) {
            return true; // unparseable — allow
        }

        [, $operator, $literal] = $matches;
        $literal = trim($literal);

        // Coerce literal type
        $literal = match (strtolower($literal)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => is_numeric($literal) ? ($literal + 0) : $literal,
        };

        return match ($operator) {
            '=='       => $resolvedValue == $literal,
            '!='       => $resolvedValue != $literal,
            '>'        => is_numeric($resolvedValue) && $resolvedValue > $literal,
            '>='       => is_numeric($resolvedValue) && $resolvedValue >= $literal,
            '<'        => is_numeric($resolvedValue) && $resolvedValue < $literal,
            '<='       => is_numeric($resolvedValue) && $resolvedValue <= $literal,
            'contains' => is_string($resolvedValue) && str_contains($resolvedValue, (string) $literal),
            default    => true,
        };
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function parseJson(string $payload): array
    {
        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return ['_raw' => $payload, '_error' => 'Invalid JSON'];
        }
        return $decoded;
    }

    private function parseCsv(string $payload): array
    {
        $rows = [];
        foreach (explode("\n", trim($payload)) as $line) {
            if ($line !== '') {
                $rows[] = str_getcsv($line);
            }
        }
        return ['rows' => $rows];
    }

    private function hexToBytes(string $hex): array
    {
        $hex   = preg_replace('/\s+/', '', $hex);
        $bytes = [];
        for ($i = 0; $i < strlen($hex); $i += 2) {
            $bytes[] = hexdec(substr($hex, $i, 2));
        }
        return $bytes;
    }
}
