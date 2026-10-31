<?php

namespace Tests\Unit\Support;

use App\Support\CurrencyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrencyRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrencyRegistry::forget();
    }

    public function test_validation_is_exact_iso_codes_only(): void
    {
        $this->assertTrue(CurrencyRegistry::isValid('PLN'));
        $this->assertTrue(CurrencyRegistry::isValid('EUR'));
        $this->assertTrue(CurrencyRegistry::isValid('USD'));
        $this->assertFalse(CurrencyRegistry::isValid('pln'));
        $this->assertFalse(CurrencyRegistry::isValid('XYZ'));
        $this->assertFalse(CurrencyRegistry::isValid('EURO'));
    }

    public function test_every_listed_code_is_three_uppercase_letters(): void
    {
        foreach (CurrencyRegistry::codes() as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $code);
        }
    }

    public function test_normalise_trims_and_uppercases(): void
    {
        $this->assertSame('EUR', CurrencyRegistry::normalise(' eur '));
    }

    public function test_options_are_code_name_pairs(): void
    {
        $options = CurrencyRegistry::options();

        $this->assertSame(['code' => 'PLN', 'name' => 'Polish Złoty'], $options[0]);
        $this->assertCount(count(CurrencyRegistry::codes()), $options);
    }

    public function test_save_refuses_unsupported_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CurrencyRegistry::save('XYZ');
    }

    public function test_save_persists_and_current_reads_it(): void
    {
        CurrencyRegistry::save('CHF');

        $this->assertSame('CHF', CurrencyRegistry::current());
        $this->assertSame('"CHF"', DB::table('system_settings')->where('key', 'default_currency')->value('value'));
    }

    public function test_falls_back_to_config_then_pln_when_nothing_valid_is_stored(): void
    {
        DB::table('system_settings')->where('key', 'default_currency')->delete();

        config(['openmmes.default_currency' => 'gbp']);
        CurrencyRegistry::forget();
        $this->assertSame('GBP', CurrencyRegistry::current());

        config(['openmmes.default_currency' => 'NOPE']);
        CurrencyRegistry::forget();
        $this->assertSame('PLN', CurrencyRegistry::current());
    }
}
