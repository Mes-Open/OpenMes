<?php

namespace App\Import\Importers;

use App\Import\AbstractEntityImporter;
use App\Services\CsvImport\WorkOrderImportService;
use Illuminate\Support\Facades\DB;

class WorkOrderImporter extends AbstractEntityImporter
{
    public function __construct(private WorkOrderImportService $service) {}

    public function key(): string
    {
        return 'work_orders';
    }

    public function label(): string
    {
        return __('Work orders');
    }

    public function description(): string
    {
        return __('Production orders, identified by order number. Any column can also be kept as a custom field on the order.');
    }

    public function fields(): array
    {
        return [
            'order_no' => ['label' => __('Order number'), 'required' => true, 'type' => 'text', 'aliases' => ['order no', 'orderno', 'order_number', 'wo_no', 'wo no', 'work_order', 'work order', 'nr zlecenia', 'numer zlecenia', 'zlecenie']],
            'quantity' => ['label' => __('Quantity'), 'required' => true, 'type' => 'number', 'description' => __('Planned quantity, greater than 0.'), 'aliases' => ['qty', 'planned_qty', 'planned qty', 'amount', 'ilość', 'ilosc']],
            'product_name' => ['label' => __('Product name'), 'required' => false, 'type' => 'text', 'description' => __('Free text, stored on the order.'), 'aliases' => ['product', 'item', 'item name', 'produkt', 'nazwa produktu']],
            'line_code' => ['label' => __('Line code'), 'required' => false, 'type' => 'text', 'description' => __('Must match an existing production line code.'), 'aliases' => ['line', 'linecode', 'production_line', 'production line', 'linia']],
            'product_type_code' => ['label' => __('Product type code'), 'required' => false, 'type' => 'text', 'description' => __('Must match an existing product type code.'), 'aliases' => ['product_type', 'product type', 'type code', 'type', 'kod produktu', 'indeks']],
            'priority' => ['label' => __('Priority'), 'required' => false, 'type' => 'integer', 'aliases' => ['prio', 'priorytet']],
            'due_date' => ['label' => __('Due date'), 'required' => false, 'type' => 'date', 'aliases' => ['due date', 'duedate', 'deadline', 'target date', 'delivery_date', 'termin']],
            'description' => ['label' => __('Description'), 'required' => false, 'type' => 'text', 'aliases' => ['desc', 'notes', 'comment', 'remarks', 'opis', 'uwagi']],
            'customer_order_no' => ['label' => __('Customer order number'), 'required' => false, 'type' => 'text', 'aliases' => ['customer order', 'customer_order', 'po number', 'nr zamówienia', 'zamówienie']],
            'unit_price' => ['label' => __('Unit price'), 'required' => false, 'type' => 'number', 'aliases' => ['price', 'cena']],
        ];
    }

    public function allowsCustomFields(): bool
    {
        return true;
    }

    public function warnings(): array
    {
        return [
            __('Orders whose product type has an active process template get that process attached; the rest are created without one.'),
            __('Orders that are already done or cancelled are never updated.'),
        ];
    }

    public function options(): array
    {
        $period = $this->productionPeriod();

        $options = [
            $this->strategyOption($this->erpStrategyChoices()),
            ['key' => 'target_line_id', 'type' => 'line', 'label' => __('Assign all rows to line'), 'nullable' => true, 'help' => __('Overrides any line code column in the file.')],
        ];

        if ($period === 'weekly') {
            $options[] = ['key' => 'import_week', 'type' => 'number', 'label' => __('Week number'), 'min' => 1, 'max' => 53, 'nullable' => true];
        } elseif ($period === 'monthly') {
            $options[] = ['key' => 'import_month', 'type' => 'number', 'label' => __('Month number'), 'min' => 1, 'max' => 12, 'nullable' => true];
        }

        if ($period !== 'none') {
            $options[] = ['key' => 'production_year', 'type' => 'number', 'label' => __('Year'), 'min' => 2000, 'max' => 2100, 'default' => now()->year];
        }

        return $options;
    }

    public function optionRules(): array
    {
        return [
            'options.strategy' => ['nullable', 'in:update_or_create,skip_existing,error_on_duplicate'],
            'options.target_line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'options.import_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'options.import_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'options.production_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function sample(): array
    {
        return [
            'headers' => ['order_no', 'product_type_code', 'quantity', 'line_code', 'due_date', 'priority', 'description'],
            'rows' => [
                ['WO-2026-0001', 'WIDGET-A', '500', 'ASSEMBLY', '2026-09-30', '3', 'First batch'],
                ['WO-2026-0002', 'BRACKET-S', '1200', 'CNC-1', '2026-10-05', '1', ''],
            ],
        ];
    }

    public function import(array $rows, array $options): array
    {
        return $this->service->importFromFile($rows, $options);
    }

    /** The plant's planning split: none | weekly | monthly (system_settings.production_period). */
    private function productionPeriod(): string
    {
        try {
            $row = DB::table('system_settings')->where('key', 'production_period')->first();

            return json_decode($row->value ?? '"none"', true) ?: 'none';
        } catch (\Throwable) {
            return 'none';
        }
    }
}
