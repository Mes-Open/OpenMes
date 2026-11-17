<?php

namespace Tests\Unit\Enums;

use App\Enums\ApiScope;
use Tests\TestCase;

class ApiScopeTest extends TestCase
{
    public function test_masterdata_read_scope_exists(): void
    {
        $this->assertSame(ApiScope::MasterDataRead, ApiScope::from('erp:masterdata:read'));
        $this->assertContains('erp:masterdata:read', ApiScope::values());
    }

    public function test_every_scope_has_a_label_and_description(): void
    {
        foreach (ApiScope::cases() as $scope) {
            $this->assertNotSame('', $scope->label(), $scope->value);
            $this->assertNotSame('', $scope->description(), $scope->value);
        }
    }

    public function test_options_cover_every_scope(): void
    {
        $options = ApiScope::options();

        $this->assertSame(ApiScope::values(), array_column($options, 'value'));
        foreach ($options as $option) {
            $this->assertSame(['value', 'label', 'description'], array_keys($option));
        }
    }

    public function test_labels_and_descriptions_are_translated_in_english_and_polish(): void
    {
        $en = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
        $pl = json_decode(file_get_contents(lang_path('pl.json')), true, flags: JSON_THROW_ON_ERROR);

        app()->setLocale('en');
        foreach (ApiScope::cases() as $scope) {
            foreach ([$scope->label(), $scope->description()] as $key) {
                $this->assertArrayHasKey($key, $en, "en.json is missing '{$key}'");
                $this->assertArrayHasKey($key, $pl, "pl.json is missing '{$key}'");
            }
        }
    }
}
