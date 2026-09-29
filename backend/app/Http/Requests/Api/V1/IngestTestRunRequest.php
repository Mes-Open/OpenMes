<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A test run posted by a tester. The run itself is the body: either the
 * tester's own JSONL (`application/x-ndjson` or `text/plain`, the lines the
 * station writes to its log file), a JSON object in OpenMES's native layout,
 * or `{"records": [...]}` with the decoded JSONL rows. The parser picks the
 * format (config/traceability.php); the fields validated here are the
 * attribution hints sent beside it.
 */
class IngestTestRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth:sanctum.
    }

    public function rules(): array
    {
        return [
            'records' => ['nullable', 'array', 'min:1'],
            'records.*' => ['array'],
            'work_order' => ['nullable', 'string', 'max:100', 'exists:work_orders,order_no'],
            'workstation' => ['nullable', 'string', 'max:50', 'exists:workstations,code'],
            'source' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** The decoded records, whichever way the run was sent. */
    public function records(): array
    {
        if ($this->isJson()) {
            $body = $this->json()->all();
            if (isset($body['records']) && is_array($body['records'])) {
                return array_values(array_filter($body['records'], 'is_array'));
            }

            return array_is_list($body) ? array_values(array_filter($body, 'is_array')) : [$body];
        }

        $records = [];
        foreach (preg_split('/\r?\n/', (string) $this->getContent()) as $line) {
            $row = json_decode(trim($line), true);
            if (is_array($row)) {
                $records[] = $row;
            }
        }

        return $records;
    }
}
