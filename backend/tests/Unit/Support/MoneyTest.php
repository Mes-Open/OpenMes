<?php

namespace Tests\Unit\Support;

use App\Support\CurrencyRegistry;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrencyRegistry::forget();
    }

    public function test_formats_in_the_installation_currency_by_default(): void
    {
        $this->assertSame('1 234.50 PLN', Money::format(1234.5));
        $this->assertSame('0.00 PLN', Money::format(0));
        $this->assertSame('40.00 PLN', Money::format('40.0000'));
    }

    public function test_an_explicit_currency_wins(): void
    {
        $this->assertSame('1 234.50 EUR', Money::format(1234.5, 'EUR'));
    }

    public function test_decimals_are_configurable(): void
    {
        $this->assertSame('2.5000 PLN', Money::format(2.5, null, 4));
        $this->assertSame('1 234 568 PLN', Money::format(1234567.6, null, 0));
    }

    public function test_negative_amounts_keep_their_sign(): void
    {
        $this->assertSame('-12.30 PLN', Money::format(-12.3));
    }

    public function test_missing_amounts_render_empty(): void
    {
        $this->assertSame('', Money::format(null));
        $this->assertSame('', Money::format(''));
        $this->assertSame('', Money::format('abc'));
    }

    public function test_default_follows_the_setting(): void
    {
        CurrencyRegistry::save('EUR');

        $this->assertSame('5.00 EUR', Money::format(5));

        DB::table('system_settings')->where('key', 'default_currency')->update(['value' => json_encode('USD')]);
        CurrencyRegistry::forget();

        $this->assertSame('5.00 USD', Money::format(5));
    }
}
