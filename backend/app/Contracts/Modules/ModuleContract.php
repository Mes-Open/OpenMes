<?php

namespace App\Contracts\Modules;

use Illuminate\Foundation\Application;

interface ModuleContract
{
    /**
     * Get the unique name of the module.
     */
    public function getName(): string;

    /**
     * Get the display name of the module.
     */
    public function getDisplayName(): string;

    /**
     * Get the version of the module.
     */
    public function getVersion(): string;

    /**
     * Bootstrap the module services.
     */
    public function boot(Application $app): void;

    /**
     * Register the module services.
     */
    public function register(Application $app): void;
}
