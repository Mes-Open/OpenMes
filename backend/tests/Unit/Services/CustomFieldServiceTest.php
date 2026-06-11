<?php

namespace Tests\Unit\Services;

use App\Models\CustomFieldDefinition;
use App\Models\Material;
use App\Services\CustomFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CustomFieldService
    {
        return app(CustomFieldService::class);
    }

    private function define(array $attrs): CustomFieldDefinition
    {
        return CustomFieldDefinition::create(array_merge([
            'entity_type' => 'material',
            'key' => 'color',
            'label' => 'Color',
            'type' => 'text',
            'required' => false,
            'position' => 0,
            'is_active' => true,
        ], $attrs));
    }

    public function test_rules_are_built_from_definitions(): void
    {
        $this->define(['key' => 'color', 'type' => 'text', 'required' => true, 'config' => ['max' => 30]]);
        $this->define(['key' => 'weight', 'type' => 'number', 'config' => ['min' => 0]]);
        $this->define(['key' => 'tags', 'type' => 'multiselect', 'config' => ['options' => [
            ['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B'],
        ]]]);

        $rules = $this->service()->rules('material');

        $this->assertSame(['required', 'string', 'max:30'], $rules['custom_fields.color']);
        $this->assertSame(['nullable', 'numeric', 'min:0'], $rules['custom_fields.weight']);
        $this->assertContains('array', $rules['custom_fields.tags']);
        // Multi-value types get a per-item rule constraining each entry.
        $this->assertArrayHasKey('custom_fields.tags.*', $rules);
    }

    public function test_cast_coerces_types_and_prunes_unknown_keys(): void
    {
        $this->define(['key' => 'color', 'type' => 'text']);
        $this->define(['key' => 'qty', 'type' => 'integer']);
        $this->define(['key' => 'fragile', 'type' => 'boolean']);

        $cast = $this->service()->cast([
            'color' => 'red',
            'qty' => '7',            // string -> int
            'fragile' => '1',        // truthy string -> bool
            'rogue' => 'drop me',    // no active definition -> pruned
        ], 'material');

        $this->assertSame('red', $cast['color']);
        $this->assertSame(7, $cast['qty']);
        $this->assertTrue($cast['fragile']);
        $this->assertArrayNotHasKey('rogue', $cast);
    }

    public function test_cast_drops_empty_values(): void
    {
        $this->define(['key' => 'color', 'type' => 'text']);

        $this->assertSame([], $this->service()->cast(['color' => ''], 'material'));
    }

    public function test_inactive_definitions_are_ignored(): void
    {
        $this->define(['key' => 'color', 'type' => 'text', 'is_active' => false]);

        $this->assertSame([], $this->service()->rules('material'));
        $this->assertSame([], $this->service()->cast(['color' => 'red'], 'material'));
    }

    public function test_client_config_shape(): void
    {
        $this->define(['key' => 'color', 'type' => 'select', 'required' => true, 'config' => [
            'options' => [['value' => 'red', 'label' => 'Red']],
        ]]);

        $config = $this->service()->clientConfig('material');

        $this->assertCount(1, $config);
        $this->assertSame([
            'key' => 'color',
            'label' => 'Color',
            'type' => 'select',
            'required' => true,
            'config' => ['options' => [['value' => 'red', 'label' => 'Red']]],
        ], $config[0]);
    }

    public function test_trait_casts_and_reads_custom_fields(): void
    {
        $this->define(['key' => 'color', 'type' => 'text']);

        $material = new Material;
        $this->assertSame('material', $material->customFieldEntityType());

        $material->setCustomField('color', 'blue');
        $this->assertSame('blue', $material->cf('color'));
        $this->assertSame('fallback', $material->cf('missing', 'fallback'));
        $this->assertIsArray($material->custom_fields);
    }
}
