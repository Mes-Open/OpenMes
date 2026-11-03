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
 * floor is not an admin key to the whole API.
 */
enum ApiTokenScope: string
{
    case Full = 'full';
    case MachineGateway = 'machine-gateway';

    /** Sanctum ability that admits a token to the machine-gateway routes. */
    public const GATEWAY_ABILITY = 'machine-gateway';

    /** @return list<string> */
    public function abilities(): array
    {
        return match ($this) {
            self::Full => ['*'],
            self::MachineGateway => [self::GATEWAY_ABILITY],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Full => __('Full API access'),
            self::MachineGateway => __('Machine gateway (OPC UA)'),
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

        return ! in_array('*', $abilities, true) && in_array(self::GATEWAY_ABILITY, $abilities, true)
            ? self::MachineGateway
            : self::Full;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $scope) => $scope->value, self::cases());
    }
}
