<?php

namespace App\Http\Requests\Shipments;

use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Support\ShipmentListParser;
use Illuminate\Validation\Rule;

/**
 * The shipment contract (#400) shared by the web form, the REST API and the ERP
 * endpoint, so a load accepted by one is accepted by all three.
 */
trait ShipmentRules
{
    /** @return array<string, array<int, mixed>> */
    protected function headerRules(?Shipment $ignore = null, bool $numberRequired = true): array
    {
        $tenantId = $this->user()?->tenant_id ?? app(\App\Support\TenantContext::class)->id();

        $unique = Rule::unique('shipments', 'shipment_no')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q->whereNull('tenant_id'));
        if ($ignore) {
            $unique->ignore($ignore->id);
        }

        return [
            'shipment_no' => [$numberRequired ? 'required' : 'sometimes', 'string', 'max:'.Shipment::IDENTIFIER_MAX, 'regex:'.Shipment::IDENTIFIER_REGEX, $unique],
            'erp_reference' => ['nullable', 'string', 'max:100', 'regex:'.Shipment::IDENTIFIER_REGEX],
            'carrier' => ['nullable', 'string', 'max:255'],
            'vehicle_no' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]*$/'],
            'destination' => ['nullable', 'string', 'max:255'],
            'planned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    protected function itemRules(bool $required = true): array
    {
        return [
            'items' => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:'.Shipment::MAX_ITEMS],
            'items.*' => ['array'],
            'items.*.unit_type' => ['nullable', 'string', Rule::in(ShipmentItem::TYPES)],
            'items.*.identifier' => ['required', 'string', 'max:'.Shipment::IDENTIFIER_MAX, 'regex:'.Shipment::IDENTIFIER_REGEX],
            // Web form only: the raw paste the list came from, and the type a line without one gets.
            'items_text' => ['nullable', 'string', 'max:100000'],
            'default_unit_type' => ['nullable', 'string', Rule::in(array_merge(['auto'], ShipmentItem::TYPES))],
        ];
    }

    /** A pasted / uploaded list arrives as text; turn it into items[] before the rules run. */
    protected function expandItemsText(): void
    {
        if ($this->has('items') || ! is_string($this->input('items_text'))) {
            return;
        }
        $text = $this->input('items_text');
        $default = $this->input('default_unit_type');
        $this->merge(['items' => trim($text) === '' ? [] : ShipmentListParser::parse($text, is_string($default) ? $default : null)]);
    }

    /** @return array<string, mixed> */
    public function shipmentAttributes(): array
    {
        return collect($this->validated())
            ->only(['shipment_no', 'erp_reference', 'carrier', 'vehicle_no', 'destination', 'planned_at', 'notes'])
            ->all();
    }

    /** @return list<array{unit_type: string|null, identifier: string}>|null */
    public function expectedItems(): ?array
    {
        $items = $this->validated()['items'] ?? null;

        return $items === null ? null : array_values(array_map(fn ($i) => [
            'unit_type' => $i['unit_type'] ?? null,
            'identifier' => $i['identifier'],
        ], $items));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'shipment_no.regex' => __('Use letters, digits and - _ / . only.'),
            'items.*.identifier.regex' => __('Line :position: use letters, digits and - _ / . only.'),
            'items.required' => __('List at least one pallet or carton.'),
            'items.min' => __('List at least one pallet or carton.'),
        ];
    }
}
