<?php

namespace Tests\Concerns;

/**
 * Marks a test as meaningful only on a bare installation.
 *
 * Some assertions are about what core is on its own: that no route survives an
 * extracted screen, that the module nav prop is empty, that the null workforce
 * provider is what gets bound. Every one of them is false — correctly — the
 * moment a module is installed, so running them that way proves nothing and
 * fails loudly for the wrong reason.
 *
 * Skipping is the honest answer, not relaxing the assertion: on the run that
 * matters, the one with no module, they still have teeth.
 */
trait RequiresNoModules
{
    protected function skipIfAnyModuleIsInstalled(): void
    {
        $modules = array_filter(
            array_keys($this->app->getLoadedProviders()),
            fn (string $provider) => str_starts_with($provider, 'Modules\\'),
        );

        if ($modules !== []) {
            $this->markTestSkipped(
                'Asserts what core is on its own; a module is installed: '.implode(', ', $modules),
            );
        }
    }
}
