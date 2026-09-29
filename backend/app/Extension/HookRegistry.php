<?php

namespace App\Extension;

use Illuminate\Support\Facades\Log;

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
 * ⚠️ `component` works only for a module that was present when the core frontend
 * was built. The page glob below is expanded by Vite at build time, and the
 * release ZIP ships a prebuilt frontend — so a module INSTALLED FROM A ZIP can
 * never deliver a working component, and naming one produces an empty region and
 * a console warning. Such a module must contribute the data fields instead and
 * let core render them. This is the same trap that produced a blank
 * /admin/plant-reports; do not walk into it again.
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
 *
 * The same registry also carries `persist.<page>` points, resolved with
 * dispatch() rather than render(): those are called for their effect, inside the
 * controller's transaction, so a module can store what it contributed.
 */
class HookRegistry
{
    /** @var array<string, list<array{order: int, payload: callable|array<string, mixed>}>> */
    private array $handlers = [];

    /**
     * Fields a contribution may carry; anything else is dropped.
     *
     * Two groups. The card fields (title/metric/body/href/external/tone) are what
     * WidgetRegistry has always carried. The field fields (name/type/label/…)
     * describe one form input, which is what a module needs to add a field to a
     * core form — see ModuleFields.jsx for what renders them.
     *
     * `required` is cosmetic, an asterisk and nothing more: whether the value is
     * actually demanded is decided by the module's validation rule, contributed
     * separately through FilterRegistry. A contribution cannot make the backend
     * demand anything.
     */
    private const ALLOWED = [
        'component', 'props', 'slot',
        'title', 'metric', 'body', 'href', 'external', 'tone',
        'name', 'type', 'label', 'value', 'options', 'placeholder', 'help', 'required',
    ];

    /**
     * Input types a contributed field may ask for.
     *
     * Kept short on purpose. Every entry is a control core already renders, and a
     * contribution naming anything else is dropped rather than guessed at — a
     * page that renders an unknown control is how a typo becomes a broken form.
     */
    private const FIELD_TYPES = ['text', 'select', 'checkbox'];

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

            $contribution = array_intersect_key($result, array_flip(self::ALLOWED));

            if (! $this->fieldTypeIsRenderable($contribution, $hook)) {
                continue;
            }

            $out[] = $contribution;
        }

        return $out;
    }

    /**
     * A contributed field naming a type core cannot draw is dropped, with a line
     * in the log — the same degradation the browser side applies to a component
     * this build does not contain.
     *
     * @param  array<string, mixed>  $contribution
     */
    private function fieldTypeIsRenderable(array $contribution, string $hook): bool
    {
        if (! isset($contribution['type']) || in_array($contribution['type'], self::FIELD_TYPES, true)) {
            return true;
        }

        Log::warning('A module contributed a field of a type this application cannot render.', [
            'hook' => $hook,
            'type' => $contribution['type'],
            'field' => $contribution['name'] ?? null,
            'renderable' => self::FIELD_TYPES,
        ]);

        return false;
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

    /**
     * Tell the listeners that a record was saved, so a module can store what it
     * contributed to the form.
     *
     * Unlike render(), this returns nothing and is called for its effect. Call it
     * INSIDE the controller's transaction: a module's write belongs to the same
     * unit of work as the record it hangs off, or a failure there leaves the
     * account saved and its module field silently lost.
     *
     * A listener that throws therefore rolls the whole save back, which is the
     * intended behaviour and not an accident — see the transaction test.
     *
     * The context carries the validated input and the saved model; a module reads
     * only its own prefixed keys from it.
     *
     * @param  array<string, mixed>  $context
     */
    public function dispatch(string $hook, array $context = []): void
    {
        $handlers = $this->handlers[$hook] ?? [];
        usort($handlers, fn ($a, $b) => $a['order'] <=> $b['order']);

        foreach ($handlers as $handler) {
            $payload = $handler['payload'];

            if (is_callable($payload)) {
                $payload($context);
            }
        }
    }
}
