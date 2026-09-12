<?php

namespace App\Extension;

/**
 * Display hooks — named points on a page where a module may contribute something.
 *
 * The UI is React/Inertia, so there is no template to splice into: a contribution
 * is STRUCTURED DATA that travels to the browser as a page prop and is rendered by
 * the generic <Hook> component. This is WidgetRegistry generalised from three
 * dashboard zones to arbitrary named points, and the same safety rule applies —
 * every value is escaped by React, there is no raw HTML anywhere in the path.
 *
 * Registered as a SCOPED binding, not a singleton: under Octane the container
 * outlives the request, and a registry that accumulated across requests would
 * render a module's contribution twice on the second hit.
 *
 * Naming a hook point: `display.<page>.<region>`, e.g.
 *   display.admin.lines.form.fields
 *   display.admin.lines.table.columns
 *   display.admin.users.form.fields
 *
 * Usage in a module ServiceProvider::boot():
 *   app(HookRegistry::class)->listen('display.admin.lines.form.fields', fn ($ctx) => [
 *       'component' => 'ext:Example/LinePicker',   // a page under the module's resources/js
 *       'props'     => ['lineId' => $ctx['lineId'] ?? null],
 *   ]);
 *
 * And in the core controller that owns the page:
 *   'hooks' => app(HookRegistry::class)->renderMany([...], $context)
 *
 * Deliberately NOT shared from HandleInertiaRequests: resolving every hook on
 * every request would run a module's queries on pages that never use them.
 */
class HookRegistry
{
    /** @var array<string, list<array{order: int, payload: callable|array<string, mixed>}>> */
    private array $handlers = [];

    /** Fields a contribution may carry; anything else is dropped. */
    private const ALLOWED = ['component', 'props', 'slot', 'title', 'metric', 'body', 'href', 'external', 'tone'];

    /**
     * Contribute to a hook point.
     *
     * A callable receives the hook's context and returns a contribution array,
     * or null to render nothing — which is how a module opts out per row/record
     * without the page having to know why.
     *
     * @param  callable(array<string, mixed>): ?array<string, mixed>|array<string, mixed>  $payload
     * @param  int  $order  Sort weight — lower renders first
     */
    public function listen(string $hook, callable|array $payload, int $order = 50): void
    {
        $this->handlers[$hook][] = ['order' => $order, 'payload' => $payload];
    }

    /** Whether anything is listening — lets a page skip building an expensive context. */
    public function has(string $hook): bool
    {
        return ! empty($this->handlers[$hook]);
    }

    /**
     * Resolve one hook against a context, in order.
     *
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public function render(string $hook, array $context = []): array
    {
        $handlers = $this->handlers[$hook] ?? [];
        usort($handlers, fn ($a, $b) => $a['order'] <=> $b['order']);

        $out = [];

        foreach ($handlers as $handler) {
            $payload = $handler['payload'];
            $result = is_callable($payload) ? $payload($context) : $payload;

            if (! is_array($result) || $result === []) {
                continue;
            }

            $out[] = array_intersect_key($result, array_flip(self::ALLOWED));
        }

        return $out;
    }

    /**
     * Resolve several hooks against one shared context — the shape a controller
     * hands to its page. Points with no listeners are omitted, so a community
     * install sends `{}` and the <Hook> component renders nothing.
     *
     * @param  list<string>  $hooks
     * @param  array<string, mixed>  $context
     * @return array<string, list<array<string, mixed>>>
     */
    public function renderMany(array $hooks, array $context = []): array
    {
        $out = [];

        foreach ($hooks as $hook) {
            if (! $this->has($hook)) {
                continue;
            }

            $rendered = $this->render($hook, $context);

            if ($rendered !== []) {
                $out[$hook] = $rendered;
            }
        }

        return $out;
    }
}
