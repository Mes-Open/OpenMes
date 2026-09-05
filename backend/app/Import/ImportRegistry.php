<?php

namespace App\Import;

use App\Import\Contracts\EntityImporter;
use App\Import\Importers\BomImporter;
use App\Import\Importers\MaterialImporter;
use App\Import\Importers\ProductTypeImporter;
use App\Import\Importers\WorkOrderImporter;

/**
 * The entities the unified importer (Admin → Import) knows, in the order the
 * entity picker lists them. Adding an entity = one importer class + one line
 * here; the screen, the queue job, the samples and the routes pick it up.
 */
class ImportRegistry
{
    /** @var list<class-string<EntityImporter>> */
    public const ENTITIES = [
        ProductTypeImporter::class,
        MaterialImporter::class,
        WorkOrderImporter::class,
        BomImporter::class,
    ];

    /**
     * Entities a section may import. Supervisors keep what they had on the old
     * CSV importer — work orders; master data stays behind the Admin tabs.
     */
    private const SECTIONS = [
        'admin' => null, // all
        'supervisor' => ['work_orders'],
    ];

    /** @var array<string, EntityImporter>|null key => instance */
    private ?array $instances = null;

    /** @return array<string, EntityImporter> key => importer */
    public function all(): array
    {
        if ($this->instances === null) {
            $this->instances = [];

            foreach (self::ENTITIES as $class) {
                $importer = app($class);
                $this->instances[$importer->key()] = $importer;
            }
        }

        return $this->instances;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_map(fn ($i) => $i->slug(), array_values(app(self::class)->all()));
    }

    public function get(string $key): ?EntityImporter
    {
        return $this->all()[$key] ?? null;
    }

    public function fromSlug(string $slug): ?EntityImporter
    {
        foreach ($this->all() as $importer) {
            if ($importer->slug() === $slug) {
                return $importer;
            }
        }

        return null;
    }

    /** @return array<string, EntityImporter> */
    public function forSection(string $section): array
    {
        if (! array_key_exists($section, self::SECTIONS)) {
            return [];
        }

        $allowed = self::SECTIONS[$section];

        if ($allowed === null) {
            return $this->all();
        }

        return array_filter($this->all(), fn ($i) => in_array($i->key(), $allowed, true));
    }

    public function allowedIn(string $section, EntityImporter $importer): bool
    {
        return isset($this->forSection($section)[$importer->key()]);
    }
}
