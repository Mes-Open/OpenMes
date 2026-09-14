<?php

namespace Tests\Unit\Extension;

use App\Extension\HookRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Named points where an installed module may contribute to a page.
 *
 * The case that carries the most weight is the empty one: on an installation
 * with no modules every hook must resolve to nothing, cost nothing, and change
 * no page. Everything else here guards the shape of what crosses into React.
 */
class HookRegistryTest extends TestCase
{
    private function registry(): HookRegistry
    {
        return new HookRegistry;
    }

    public function test_a_hook_nobody_listens_to_renders_nothing(): void
    {
        $registry = $this->registry();

        $this->assertFalse($registry->has('display.admin.lines.form.fields'));
        $this->assertSame([], $registry->render('display.admin.lines.form.fields'));
    }

    public function test_render_many_omits_points_with_no_listeners(): void
    {
        // The community shape: the controller asks for its hooks and gets `{}`,
        // which the <Hook> component renders as nothing.
        $this->assertSame([], $this->registry()->renderMany([
            'display.admin.lines.form.fields',
            'display.admin.lines.table.columns',
        ]));
    }

    public function test_a_contribution_reaches_the_page(): void
    {
        $registry = $this->registry();
        $registry->listen('display.admin.lines.form.fields', [
            'component' => 'ext:Example/Picker',
            'props' => ['choices' => [1, 2]],
        ]);

        $this->assertTrue($registry->has('display.admin.lines.form.fields'));
        $this->assertSame(
            [['component' => 'ext:Example/Picker', 'props' => ['choices' => [1, 2]]]],
            $registry->render('display.admin.lines.form.fields'),
        );
    }

    public function test_a_callable_receives_the_page_context(): void
    {
        $registry = $this->registry();
        $registry->listen('display.admin.lines.form.fields', fn ($context) => [
            'component' => 'ext:Example/Picker',
            'props' => ['lineId' => $context['lineId']],
        ]);

        $rendered = $registry->render('display.admin.lines.form.fields', ['lineId' => 7]);

        $this->assertSame(7, $rendered[0]['props']['lineId']);
    }

    public function test_a_listener_can_decline_per_context(): void
    {
        // How a module opts out for one record without the page knowing why.
        $registry = $this->registry();
        $registry->listen('display.admin.lines.form.fields', fn ($c) => $c['lineId'] === 1 ? ['component' => 'ext:X'] : null);

        $this->assertCount(1, $registry->render('display.admin.lines.form.fields', ['lineId' => 1]));
        $this->assertSame([], $registry->render('display.admin.lines.form.fields', ['lineId' => 2]));
    }

    public function test_contributions_render_in_order(): void
    {
        $registry = $this->registry();
        $registry->listen('h', ['component' => 'last'], order: 90);
        $registry->listen('h', ['component' => 'first'], order: 10);
        $registry->listen('h', ['component' => 'middle'], order: 50);

        $this->assertSame(
            ['first', 'middle', 'last'],
            array_column($registry->render('h'), 'component'),
        );
    }

    public function test_unknown_fields_are_dropped_before_reaching_the_browser(): void
    {
        // The prop must stay lean and predictable; a module cannot smuggle
        // arbitrary keys — let alone markup — into a core page's props.
        $registry = $this->registry();
        $registry->listen('h', [
            'component' => 'ext:X',
            'dangerouslySetInnerHTML' => '<script>alert(1)</script>',
            'whatever' => 'nope',
        ]);

        $this->assertSame(['component' => 'ext:X'], $registry->render('h')[0]);
    }
}
