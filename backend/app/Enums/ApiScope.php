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

    /** Lines, warehouses and product types — read-only dictionaries the ERP maps its own codes to. */
    case MasterDataRead = 'erp:masterdata:read';

    /** Warehouse balances and the stock-document export backlog (#212). */
    case StockRead = 'erp:stock:read';

    /** Warehouse balance sync and acknowledging exported stock documents (#212). */
    case StockWrite = 'erp:stock:write';

    /** Send shipments (loads) with their expected pallets / cartons (#400). */
    case ShipmentsWrite = 'erp:shipments:write';

    /** Read shipment load results — loaded, missing, unexpected (#400). */
    case ShipmentsRead = 'erp:shipments:read';

    /** Register and manage the key's own outgoing webhooks (T2a). */
    case WebhooksManage = 'erp:webhooks:manage';

    public function label(): string
    {
        return match ($this) {
            self::OrdersImport => __('Import work orders'),
            self::ProductionRead => __('Read production completions'),
            self::QualityRead => __('Read quality & issue reports'),
            self::MasterDataWrite => __('Import products, materials, lots & recipes'),
            self::MasterDataRead => __('Read lines, warehouses & product types'),
            self::StockRead => __('Read warehouse stock & documents'),
            self::StockWrite => __('Sync warehouse stock & acknowledge documents'),
            self::ShipmentsWrite => __('Send shipments to load'),
            self::ShipmentsRead => __('Read shipment load results'),
            self::WebhooksManage => __('Manage own webhooks'),
        };
    }

    /** One sentence telling an admin what granting the scope lets the integration do. */
    public function description(): string
    {
        return match ($this) {
            self::OrdersImport => __('Create and update work orders from the ERP.'),
            self::ProductionRead => __('Export produced quantities and completion times of work orders.'),
            self::QualityRead => __('Export issues and non-conforming quantities.'),
            self::MasterDataWrite => __('Create and update products, materials, material lots and recipes.'),
            self::MasterDataRead => __('List line, warehouse and product type codes so the ERP can map its own records.'),
            self::StockRead => __('Export warehouse balances and stock documents to book.'),
            self::StockWrite => __('Replace warehouse balances and acknowledge booked stock documents.'),
            self::ShipmentsWrite => __('Create and replace shipments with the pallets and cartons each load must carry.'),
            self::ShipmentsRead => __('Export what each shipment loaded, what is missing and what was scanned unexpectedly.'),
            self::WebhooksManage => __('Register, change and remove webhooks that send OpenMES events to the integration.'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * Shape for a scope picker in the UI.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'description' => $c->description(),
        ], self::cases());
    }
}
