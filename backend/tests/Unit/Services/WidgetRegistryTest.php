<?php

namespace Tests\Unit\Services;

use App\Services\WidgetRegistry;
use PHPUnit\Framework\TestCase;

class WidgetRegistryTest extends TestCase
{
    public function test_a_callable_card_is_resolved_on_every_read(): void
    {
        $registry = new WidgetRegistry;
        $count = 1;
        $registry->register('kpi', function () use (&$count) {
            return ['title' => 'Open entries', 'metric' => (string) $count, 'secret' => 'dropped'];
        });

        $this->assertSame([['title' => 'Open entries', 'metric' => '1', 'order' => 50]], $registry->getWidgets('kpi'));

        $count = 3;
        $this->assertSame('3', $registry->all()['kpi'][0]['metric']);
    }

    public function test_a_callable_returning_nothing_shows_no_card(): void
    {
        $registry = new WidgetRegistry;
        $registry->register('kpi', fn () => null);
        $registry->register('kpi', fn () => []);

        $this->assertSame([], $registry->getWidgets('kpi'));
    }

    public function test_static_and_callable_cards_sort_together_by_order(): void
    {
        $registry = new WidgetRegistry;
        $registry->register('main', ['title' => 'Static'], order: 20);
        $registry->register('main', fn () => ['title' => 'Live'], order: 10);

        $this->assertSame(['Live', 'Static'], array_column($registry->getWidgets('main'), 'title'));
    }
}
