<?php

namespace Modules\Pantheon\Services;

use App\Models\IntegrationConfig;

/**
 * Connection and mapping settings for one Pantheon installation.
 *
 * Stored in the core `integration_configs` row with system_type = 'pantheon'.
 * Its `api_config` column is an `encrypted:array`, so the PAWS password never
 * sits in plaintext and the module needs no table of its own for credentials.
 *
 * Everything here is per customer: which classification codes mean "product"
 * versus "raw material", which Pantheon document types are the material release
 * and the product receipt, and which columns of their views carry which value.
 * That is exactly the knowledge that must not leak into core.
 */
class PantheonSettings
{
    public const SYSTEM_TYPE = 'pantheon';

    public function __construct(private array $config, private bool $active) {}

    public static function load(): self
    {
        $row = IntegrationConfig::where('system_type', self::SYSTEM_TYPE)->first();

        return new self($row?->api_config ?? [], (bool) ($row?->is_active ?? false));
    }

    /** Switched on in Admin → Integrations. Inactive = the syncs no-op. */
    public function isActive(): bool
    {
        return $this->active;
    }

    /** True when the module has enough to talk to PAWS at all. */
    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->username() !== '' && $this->companyDb() !== '';
    }

    public function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    public function username(): string
    {
        return (string) ($this->config['username'] ?? '');
    }

    public function password(): string
    {
        return (string) ($this->config['password'] ?? '');
    }

    /** Pantheon serves several company databases from one PAWS instance. */
    public function companyDb(): string
    {
        return (string) ($this->config['company_db'] ?? '');
    }

    /**
     * Classification codes (acClassif) that mark an item as a finished product.
     * Passed straight to the core importer's `only_categories` filter.
     *
     * @return list<string>
     */
    public function productClassifications(): array
    {
        return array_values(array_filter((array) ($this->config['product_classifications'] ?? [])));
    }

    /** @return list<string> */
    public function materialClassifications(): array
    {
        return array_values(array_filter((array) ($this->config['material_classifications'] ?? [])));
    }

    /**
     * Pantheon document type codes to book our stock documents as. Discover the
     * customer's codes with PAWS `Move/getdoctypes` — they are configurable per
     * installation, so they cannot be assumed.
     */
    public function documentType(string $stockDocumentType): ?string
    {
        $map = (array) ($this->config['document_types'] ?? []);

        return $map[$stockDocumentType] ?? null;
    }

    /**
     * Warehouse code in Pantheon for a given OpenMES warehouse code, when the two
     * do not match. Falls back to the warehouse's own `erp_code`.
     */
    public function warehouseCode(string $openMesCode): ?string
    {
        return ((array) ($this->config['warehouse_map'] ?? []))[$openMesCode] ?? null;
    }

    /** Rows per PAWS request. Their `selecttables` is paged via start/length. */
    public function pageSize(): int
    {
        return max(1, (int) ($this->config['page_size'] ?? 500));
    }
}
