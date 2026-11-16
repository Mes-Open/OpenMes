<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a personal access token (Settings → API Tokens) may do.
 *
 * A full token carries Sanctum's `*` ability and acts as the admin who created
 * it. A machine-gateway token carries only `machine-gateway`: it can fetch a
 * machine connection's gateway config, post readings and heartbeats — and
 * nothing else, so a token pasted into a docker-compose `.env` on the shop
 * floor is not an admin key to the whole API. A read-only token carries only
 * `read`: it sees what its creator sees, and any request other than GET, HEAD
 * or OPTIONS is refused (RejectWritesWithReadOnlyToken) — the scope to give a
 * reporting tool or an AI assistant that should look but not touch.
 */
enum ApiTokenScope: string
{
    case Full = 'full';
    case ReadOnly = 'read-only';
    case MachineGateway = 'machine-gateway';

    /** Sanctum ability that admits a token to the machine-gateway routes. */
    public const GATEWAY_ABILITY = 'machine-gateway';

    /** Sanctum ability of a read-only token: safe HTTP methods only. */
    public const READ_ABILITY = 'read';

    /** @return list<string> */
    public function abilities(): array
    {
        return match ($this) {
            self::Full => ['*'],
            self::ReadOnly => [self::READ_ABILITY],
            self::MachineGateway => [self::GATEWAY_ABILITY],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Full => __('Full API access'),
            self::ReadOnly => __('Read-only'),
            self::MachineGateway => __('Machine gateway (OPC UA)'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Full => __('Reads and changes data with your permissions.'),
            self::ReadOnly => __('Reads data with your permissions; every change is refused.'),
            self::MachineGateway => __('Only for the OPC UA gateway: sends machine readings, nothing else.'),
        };
    }

    /**
     * Scope of an existing token, read back from its abilities.
     *
     * @param  mixed  $abilities  the token's `abilities` cast (an array; anything else counts as none)
     */
    public static function fromAbilities(mixed $abilities): self
    {
        $abilities = is_array($abilities) ? $abilities : [];

        return match (true) {
            in_array('*', $abilities, true) => self::Full,
            in_array(self::GATEWAY_ABILITY, $abilities, true) => self::MachineGateway,
            in_array(self::READ_ABILITY, $abilities, true) => self::ReadOnly,
            default => self::Full,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $scope) => $scope->value, self::cases());
    }
}
