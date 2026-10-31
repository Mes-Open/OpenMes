<?php

namespace App\Support;

/**
 * The two inbound-inspection permissions, and the one place their names live.
 *
 *   inspections.perform      "Quality inspector": start an inspection, record
 *                            its results and complete it.
 *   inspections.disposition  "Quality disposition": decide what happens to the
 *                            inspected lot (accept, quarantine, scrap, …). The
 *                            decision is what puts material into stock, so it
 *                            is the higher right and includes the first one.
 *
 * Both can be granted to a role (Settings → Access) or to one person
 * (Admin → Users → Edit). InspectionPolicy is the only reader; the API and the
 * operator screen both go through it.
 */
class InspectionPermissions
{
    public const PERFORM = 'inspections.perform';

    public const DISPOSITION = 'inspections.disposition';

    public const ALL = [self::PERFORM, self::DISPOSITION];

    /**
     * Permission name => English label (the frontend translates it).
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PERFORM => 'Quality inspector',
            self::DISPOSITION => 'Quality disposition',
        ];
    }

    /**
     * Permission name => English one-line description of what it allows.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            self::PERFORM => 'Can start inspections, record results and complete them.',
            self::DISPOSITION => 'Can release, quarantine or reject an inspected lot. Includes the inspector right.',
        ];
    }

    /**
     * The grantable permissions as a list the forms render.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function options(): array
    {
        $descriptions = self::descriptions();

        return array_map(
            fn (string $key, string $label) => ['key' => $key, 'label' => $label, 'description' => $descriptions[$key]],
            array_keys(self::labels()),
            self::labels(),
        );
    }
}
