<?php

namespace App\Enums;

/**
 * Capability scopes an ERP integration API key can be granted. A key may only
 * call an endpoint whose required scope is present in its `scopes` allowlist,
 * enforced by the EnsureApiScope middleware. Kept deliberately coarse — one
 * scope per ERP-facing capability (import work orders, read production, read
 * quality) so an integration can be given least privilege.
 */
enum ApiScope: string
{
    case OrdersImport = 'erp:orders:import';
    case ProductionRead = 'erp:production:read';
    case QualityRead = 'erp:quality:read';

    /** Products, materials, material lots and recipes (#212). */
    case MasterDataWrite = 'erp:masterdata:write';

    /** Warehouse balances and the stock-document export backlog (#212). */
    case StockRead = 'erp:stock:read';

    /** Warehouse balance sync and acknowledging exported stock documents (#212). */
    case StockWrite = 'erp:stock:write';

    public function label(): string
    {
        return match ($this) {
            self::OrdersImport => __('Import work orders'),
            self::ProductionRead => __('Read production completions'),
            self::QualityRead => __('Read quality & issue reports'),
            self::MasterDataWrite => __('Import products, materials, lots & recipes'),
            self::StockRead => __('Read warehouse stock & documents'),
            self::StockWrite => __('Sync warehouse stock & acknowledge documents'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
