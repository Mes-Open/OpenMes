<?php

namespace Tests\Unit\Services;

use App\Services\Lot\LotPatternFormatter;
use Carbon\Carbon;
use Tests\TestCase; // the formatter's messages and legend go through the translator

class LotPatternFormatterTest extends TestCase
{
    private LotPatternFormatter $formatter;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new LotPatternFormatter;
        $this->now = Carbon::create(2026, 6, 6, 14, 30, 0);
    }

    public function test_formats_all_tokens(): void
    {
        $lot = $this->formatter->format(
            'test-[date]-[seq]-[hour]',
            42,
            4,
            null,
            $this->now,
        );

        $this->assertSame('test-20260606-0042-14', $lot);
    }

    public function test_formats_date_with_custom_format(): void
    {
        $lot = $this->formatter->format('[date:y-m-d]/[seq]', 7, 3, null, $this->now);

        $this->assertSame('26-06-06/007', $lot);
    }

    public function test_date_aliases_and_day_of_year(): void
    {
        // 2026-06-06 is day 157, ISO week 23, a Saturday (6); calendar and ISO year both 2026.
        $lot = $this->formatter->format('[doy]-[date:y1][date:W][date:N]-[date:o1]-[seq]', 1, 1, null, $this->now);

        $this->assertSame('157-6236-6-1', $lot);
    }

    public function test_named_calendar_tokens(): void
    {
        $lot = $this->formatter->format('[year2]|[year1]|[week]|[weekday]|[doy]-[seq]', 3, 2, null, $this->now);

        $this->assertSame('26|6|23|6|157-03', $lot);
    }

    public function test_describe_lists_every_token_with_todays_value(): void
    {
        $legend = collect($this->formatter->describe($this->now, 4))->keyBy('token');

        $this->assertSame(LotPatternFormatter::TOKENS, $legend->keys()->all());
        $this->assertSame('0001', $legend['seq']['example']);
        $this->assertSame('157', $legend['doy']['example']);
        $this->assertSame('6', $legend['weekday']['example']);
        $this->assertNotSame('', $legend['week']['label']);
    }

    public function test_day_of_year_is_one_based_and_padded(): void
    {
        $lot = $this->formatter->format('[doy]-[seq]', 1, 1, null, Carbon::create(2026, 1, 1));

        $this->assertSame('001-1', $lot);
    }

    public function test_iso_year_alias_follows_the_iso_week(): void
    {
        // 2027-01-01 is a Friday in ISO week 53 of 2026.
        $lot = $this->formatter->format('[date:o1][date:W]-[seq]', 1, 1, null, Carbon::create(2027, 1, 1));

        $this->assertSame('653-1', $lot);
    }

    public function test_formats_year_month_day_tokens(): void
    {
        $lot = $this->formatter->format('[year][month][day]-[seq]', 1, 2, null, $this->now);

        $this->assertSame('20260606-01', $lot);
    }

    public function test_formats_product_token(): void
    {
        $lot = $this->formatter->format('[product]-[seq]', 5, 4, 'FILTER', $this->now);

        $this->assertSame('FILTER-0005', $lot);
    }

    public function test_product_token_empty_without_product(): void
    {
        $lot = $this->formatter->format('[product]-[seq]', 5, 4, null, $this->now);

        $this->assertSame('-0005', $lot);
    }

    public function test_literals_pass_through_unchanged(): void
    {
        $lot = $this->formatter->format('A_B.C [seq] x', 9, 1, null, $this->now);

        $this->assertSame('A_B.C 9 x', $lot);
    }

    public function test_valid_pattern_has_no_errors(): void
    {
        $this->assertSame([], $this->formatter->validate('test-[date]-[seq]-[hour]'));
    }

    public function test_pattern_without_seq_is_invalid(): void
    {
        $errors = $this->formatter->validate('test-[date]');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('[seq]', $errors[0]);
    }

    public function test_pattern_with_two_seq_tokens_is_invalid(): void
    {
        $this->assertNotEmpty($this->formatter->validate('[seq]-[seq]'));
    }

    public function test_unknown_token_is_invalid(): void
    {
        $errors = $this->formatter->validate('[seq]-[banana]');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('banana', $errors[0]);
    }

    public function test_format_argument_only_allowed_on_date(): void
    {
        $this->assertNotEmpty($this->formatter->validate('[seq:Ymd]'));
        $this->assertNotEmpty($this->formatter->validate('[seq]-[hour:H]'));
    }

    public function test_unmatched_brackets_are_invalid(): void
    {
        $this->assertNotEmpty($this->formatter->validate('[seq]-['));
        $this->assertNotEmpty($this->formatter->validate('[seq]-]'));
    }
}
