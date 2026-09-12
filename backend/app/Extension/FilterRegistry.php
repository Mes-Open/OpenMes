<?php

namespace App\Extension;

/**
 * Filter hooks — let a module extend a value the application computed.
 *
 * Several registries in this application are hardcoded `const` arrays: the tab
 * matrix, the optional-module list, the soft-delete model list, the example
 * datasets. They are constants on purpose — they are contracts the migrations,
 * the seeders and the access matrix all read. A filter keeps them constants and
 * still lets an installed module add to them, without changing their shape.
 *
 * Registered as a SCOPED binding for the same reason as HookRegistry: under
 * Octane a singleton registry would accumulate the same filter across requests
 * and apply it repeatedly.
 *
 * Usage in a module ServiceProvider::boot():
 *   app(FilterRegistry::class)->addFilter('tabs.registry', fn ($tabs) => $tabs + [
 *       'example' => ['label' => 'Example', 'prefixes' => ['/admin/example']],
 *   ]);
 *
 * And at the definition site:
 *   public static function all(): array
 *   {
 *       return app(FilterRegistry::class)->filter('tabs.registry', self::TABS);
 *   }
 */
class FilterRegistry
{
    /** @var array<string, list<array{order: int, fn: callable}>> */
    private array $filters = [];

    /**
     * Transform a named value whenever the application computes it.
     *
     * The callback receives the current value and the context, and returns the
     * new value. A callback that returns null is ignored rather than trusted —
     * a filter that accidentally drops the whole tab matrix would lock every
     * user out of the application.
     *
     * @param  callable(mixed, array<string, mixed>): mixed  $fn
     * @param  int  $order  Sort weight — lower runs first
     */
    public function addFilter(string $name, callable $fn, int $order = 50): void
    {
        $this->filters[$name][] = ['order' => $order, 'fn' => $fn];
    }

    public function has(string $name): bool
    {
        return ! empty($this->filters[$name]);
    }

    /**
     * Run a value through its filters. With none registered the value is
     * returned untouched — that is the community path, and it must stay free.
     *
     * @param  array<string, mixed>  $context
     */
    public function filter(string $name, mixed $value, array $context = []): mixed
    {
        if (! $this->has($name)) {
            return $value;
        }

        $filters = $this->filters[$name];
        usort($filters, fn ($a, $b) => $a['order'] <=> $b['order']);

        foreach ($filters as $filter) {
            $result = ($filter['fn'])($value, $context);

            if ($result !== null) {
                $value = $result;
            }
        }

        return $value;
    }
}
