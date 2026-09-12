<?php

namespace Tests\Unit\Extension;

use App\Extension\FilterRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Lets an installed module extend a value the application computed.
 *
 * This is what turns the hardcoded const registries — the tab matrix, the
 * optional-module list, the soft-delete models, the example datasets — into
 * something a module can add to without changing their shape. So the identity
 * case is the important one: with nothing registered, the value must come back
 * byte-for-byte, because that is every installation that has no modules.
 */
class FilterRegistryTest extends TestCase
{
    public function test_an_unfiltered_value_is_returned_untouched(): void
    {
        $registry = new FilterRegistry;
        $tabs = ['orders' => ['label' => 'Orders'], 'quality' => ['label' => 'Quality']];

        $this->assertFalse($registry->has('tabs.registry'));
        $this->assertSame($tabs, $registry->filter('tabs.registry', $tabs));
    }

    public function test_a_filter_can_add_to_the_value(): void
    {
        $registry = new FilterRegistry;
        $registry->addFilter('tabs.registry', fn ($tabs) => $tabs + ['example' => ['label' => 'Example']]);

        $result = $registry->filter('tabs.registry', ['orders' => ['label' => 'Orders']]);

        $this->assertSame(['orders', 'example'], array_keys($result));
    }

    public function test_filters_run_in_order_and_compose(): void
    {
        $registry = new FilterRegistry;
        $registry->addFilter('list', fn ($v) => [...$v, 'second'], order: 60);
        $registry->addFilter('list', fn ($v) => [...$v, 'first'], order: 20);

        $this->assertSame(['base', 'first', 'second'], $registry->filter('list', ['base']));
    }

    public function test_a_filter_receives_the_context(): void
    {
        $registry = new FilterRegistry;
        $registry->addFilter('greeting', fn ($v, $ctx) => "{$v} {$ctx['name']}");

        $this->assertSame('hello world', $registry->filter('greeting', 'hello', ['name' => 'world']));
    }

    public function test_a_filter_returning_null_is_ignored_rather_than_trusted(): void
    {
        // A filter that forgot to return would otherwise wipe the tab matrix and
        // lock every user out of the application. Losing the module's change is
        // recoverable; losing the value is not.
        $registry = new FilterRegistry;
        $registry->addFilter('tabs.registry', fn () => null);

        $tabs = ['orders' => ['label' => 'Orders']];

        $this->assertSame($tabs, $registry->filter('tabs.registry', $tabs));
    }
}
