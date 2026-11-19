<?php

namespace Tests\Feature\Schedule\Insights;

use Tests\TestCase;

/**
 * Polish wording of the planning strings: a number in front of a noun takes
 * the Polish plural form for it (three forms), and a person's name stays in
 * the nominative — the strings put it after a colon instead of declining it.
 */
class PlanningInsightPolishTest extends TestCase
{
    private const EMPTY_PLACES = ':lines share people. At that time they need :needed between them and have :available, so :count place stays empty.|:lines share people. At that time they need :needed between them and have :available, so :count places stay empty.';

    public function test_empty_places_take_the_polish_plural_of_their_number(): void
    {
        $say = fn (int $n) => trans_choice(self::EMPTY_PLACES, $n, ['lines' => 'A, B', 'needed' => 9, 'available' => 9 - $n], 'pl');

        $this->assertStringEndsWith('więc 1 miejsce zostaje nieobsadzone.', $say(1));
        $this->assertStringEndsWith('więc 2 miejsca zostają nieobsadzone.', $say(2));
        $this->assertStringEndsWith('więc 4 miejsca zostają nieobsadzone.', $say(4));
        $this->assertStringEndsWith('więc 5 miejsc zostaje nieobsadzonych.', $say(5));
        $this->assertStringEndsWith('więc 12 miejsc zostaje nieobsadzonych.', $say(12));
        $this->assertStringEndsWith('więc 22 miejsca zostają nieobsadzone.', $say(22));
        $this->assertStringEndsWith('so 1 place stays empty.', trans_choice(self::EMPTY_PLACES, 1, ['lines' => 'A, B', 'needed' => 2, 'available' => 1], 'en'));
    }

    public function test_the_apply_flash_takes_the_polish_plural(): void
    {
        $key = ':count work order could not be fully scheduled and kept its placement.|:count work orders could not be fully scheduled and kept their placement.';

        $this->assertStringStartsWith('1 zlecenia nie', trans_choice($key, 1, [], 'pl'));
        $this->assertStringStartsWith('5 zleceń nie', trans_choice($key, 5, [], 'pl'));
        $this->assertSame('1 work order could not be fully scheduled and kept its placement.', trans_choice($key, 1, [], 'en'));
    }

    public function test_names_stay_in_the_nominative(): void
    {
        $this->assertSame('Ściągnij: Adam Nowak', __('Bring in :name', ['name' => 'Adam Nowak'], 'pl'));
        $this->assertSame('Ściągnij: Adam Nowak (z CNC)', __('Bring in :name from :other', ['name' => 'Adam Nowak', 'other' => 'CNC'], 'pl'));
        $this->assertSame('Przypisz też do CNC: Adam Nowak', __('Also assign :name to :line', ['name' => 'Adam Nowak', 'line' => 'CNC'], 'pl'));
        $this->assertStringStartsWith('Przypisać do CNC: Adam Nowak?', __('Assign :name to :line? They stay on their other lines too.', ['name' => 'Adam Nowak', 'line' => 'CNC'], 'pl'));
    }

    public function test_every_plural_key_has_three_polish_forms(): void
    {
        $pl = json_decode(file_get_contents(base_path('lang/pl.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ([self::EMPTY_PLACES, ':count operator assigned|:count operators assigned', ':count order|:count orders'] as $key) {
            $this->assertCount(3, explode('|', $pl[$key]), $key);
        }
    }
}
