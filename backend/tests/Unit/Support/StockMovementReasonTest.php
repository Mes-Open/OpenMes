<?php

namespace Tests\Unit\Support;

use App\Support\StockMovementReason;
use Tests\TestCase;

class StockMovementReasonTest extends TestCase
{
    public function test_a_written_note_translates_back_through_its_template(): void
    {
        app()->setLocale('pl');

        $stored = StockMovementReason::make('Allocated to batch #:batch (step :step)', ['batch' => 12, 'step' => 3]);

        $this->assertSame('Allocated to batch #12 (step 3)', $stored);
        $this->assertSame(__('Allocated to batch #:batch (step :step)', ['batch' => 12, 'step' => 3]), StockMovementReason::translate($stored));
        $this->assertNotSame($stored, StockMovementReason::translate($stored));
    }

    public function test_every_template_round_trips(): void
    {
        app()->setLocale('pl');

        $ref = new \ReflectionClassConstant(StockMovementReason::class, 'TEMPLATES');
        foreach ($ref->getValue() as $template => $names) {
            $params = array_combine($names, range(41, 40 + count($names)));
            $stored = StockMovementReason::make($template, $params);

            $this->assertSame(__($template, $params), StockMovementReason::translate($stored), $template);
        }
    }

    public function test_a_users_own_note_is_left_alone(): void
    {
        $this->assertSame('Delivery ref 4471 - damaged box', StockMovementReason::translate('Delivery ref 4471 - damaged box'));
        $this->assertNull(StockMovementReason::translate(null));
    }

    public function test_an_unknown_template_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StockMovementReason::make('Something #:batch', ['batch' => 1]);
    }
}
