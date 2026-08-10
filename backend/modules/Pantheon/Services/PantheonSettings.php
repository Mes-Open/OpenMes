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

    /**
     * Why this configuration must not be used, or null when it is safe.
     *
     * The credentials travel in the body of every authwithtoken call — nightly for
     * master data, every ten minutes for the document push — so a `http://` base
     * URL hands a Pantheon service account to anyone sniffing the plant VLAN. An
     * on-premise PAWS without a certificate is a real situation, so it stays
     * possible, but only when the operator says so explicitly.
     */
    public function transportProblem(): ?string
    {
        // Lower-cased: parse_url returns the scheme as written, so `HTTPS://…`
        // would match neither branch and a perfectly secure URL would be refused.
        $scheme = strtolower((string) parse_url($this->baseUrl(), PHP_URL_SCHEME));

        if ($scheme === 'https' || $this->baseUrl() === '') {
            return null;
        }

        if ($scheme !== 'http') {
            return __('The PAWS address must be an http:// or https:// URL.');
        }

        return $this->allowsInsecureHttp()
            ? null
            : __('The PAWS address uses plain http://, which would send the Pantheon password unencrypted. Use https://, or set allow_insecure_http to accept the risk.');
    }

    /** Opt-in escape hatch for an on-premise PAWS with no certificate. */
    public function allowsInsecureHttp(): bool
    {
        return (bool) ($this->config['allow_insecure_http'] ?? false);
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
