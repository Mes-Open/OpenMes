<?php

namespace Tests\Unit\Extension;

use App\Services\ModuleManager;
use Tests\TestCase;

/**
 * A module's optional setup hook.
 *
 * Migrations cover tables; the permissions behind a module's tabs, its default
 * settings and its seed rows are not schema, so a module may ship an Installer
 * instead. Most modules do not, and that has to stay free.
 */
class ModuleInstallerTest extends TestCase
{
    public function test_a_module_without_an_installer_is_not_an_error(): void
    {
        // The common case — ExampleShowcase ships no Installer class.
        $this->expectNotToPerformAssertions();

        app(ModuleManager::class)->runInstaller('ExampleShowcase');
    }

    public function test_an_unknown_module_is_not_an_error(): void
    {
        $this->expectNotToPerformAssertions();

        app(ModuleManager::class)->runInstaller('NoSuchModuleAnywhere');
    }

    public function test_a_failing_installer_propagates_rather_than_being_swallowed(): void
    {
        // The caller turns this into "leave the module disabled". Swallowing it
        // would leave a module enabled whose setup never ran, which breaks on
        // every page it contributes to instead of at the moment of installing.
        $installer = new class
        {
            public function install(): void
            {
                throw new \RuntimeException('permissions could not be created');
            }
        };

        app()->instance('Modules\\Fake\\Installer', $installer);
        class_alias($installer::class, 'Modules\\Fake\\Installer');

        $this->expectException(\RuntimeException::class);

        app(ModuleManager::class)->runInstaller('Fake');
    }
}
