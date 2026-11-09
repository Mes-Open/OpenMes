<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gateway heartbeat. An empty body means "running and connected"; a gateway
 * that is alive but cannot reach its machine (OPC UA server down, wrong
 * endpoint) sends `status: error` with the reason so the UI can say so.
 */
class MachineGatewayHeartbeatRequest extends FormRequest
{
    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    public function authorize(): bool
    {
        return true; // Gateway route enforces token ability.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:'.self::STATUS_OK.','.self::STATUS_ERROR],
            'message' => ['nullable', 'required_if:status,'.self::STATUS_ERROR, 'string', 'max:500'],
        ];
    }

    public function isError(): bool
    {
        return $this->validated('status', self::STATUS_OK) === self::STATUS_ERROR;
    }
}
