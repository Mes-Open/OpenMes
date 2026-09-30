<?php

namespace Tests\Feature;

use App\Services\ModuleManager;
use Tests\TestCase;

/**
 * Tylko katalog nazwany jak moduł jest modułem.
 *
 * Przypadek z produkcji: updater modułu Enterprise odkładał poprzednią wersję
 * obok, jako `Enterprise.poprzednia-20260930195044`. Katalog nosił komplet
 * plików razem z module.json, więc discover() pokazywał go jako drugą
 * instalację tego samego modułu — i jako WŁĄCZONĄ, bo włączenie dopasowuje się
 * po nazwie z manifestu, a kopia ma tę samą. Administrator widział dwie karty
 * „OpenMES Enterprise", obie z tym samym providerem, bez sposobu odróżnienia
 * której dotyczy „Uninstall".
 */
class ModuleDiscoveryTest extends TestCase
{
    private string $modulesPath;

    /** @var array<int, string> */
    private array $doPosprzatania = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = base_path('modules');
    }

    protected function tearDown(): void
    {
        foreach ($this->doPosprzatania as $katalog) {
            if (is_dir($katalog)) {
                @unlink("{$katalog}/module.json");
                @rmdir($katalog);
            }
        }

        parent::tearDown();
    }

    private function zrobKatalogModulu(string $katalog, string $nazwaWManifescie): void
    {
        $sciezka = "{$this->modulesPath}/{$katalog}";
        @mkdir($sciezka, 0755, true);

        file_put_contents("{$sciezka}/module.json", json_encode([
            'name' => $nazwaWManifescie,
            'display_name' => $nazwaWManifescie,
            'version' => '1.0.0',
            'provider' => "Modules\\{$nazwaWManifescie}\\Providers\\Provider",
        ]));

        $this->doPosprzatania[] = $sciezka;
    }

    public function test_katalog_nazwany_jak_modul_jest_wykrywany(): void
    {
        $this->zrobKatalogModulu('DiscoveryProbe', 'DiscoveryProbe');

        $nazwy = app(ModuleManager::class)->discover()->pluck('name')->all();

        $this->assertContains('DiscoveryProbe', $nazwy);
    }

    public function test_kopia_obok_modulu_nie_jest_drugim_modulem(): void
    {
        $this->zrobKatalogModulu('DiscoveryProbe', 'DiscoveryProbe');
        $this->zrobKatalogModulu('DiscoveryProbe.poprzednia-20260930195044', 'DiscoveryProbe');

        $moduly = app(ModuleManager::class)->discover()
            ->where('name', 'DiscoveryProbe');

        $this->assertCount(1, $moduly, 'kopia poprzedniej wersji pokazała się jako osobny moduł');
        $this->assertSame('DiscoveryProbe', $moduly->first()['directory']);
    }

    public function test_katalog_deklarujacy_obca_nazwe_jest_pomijany(): void
    {
        $this->zrobKatalogModulu('DiscoveryProbe', 'ZupelnieCosInnego');

        $nazwy = app(ModuleManager::class)->discover()->pluck('name')->all();

        $this->assertNotContains('ZupelnieCosInnego', $nazwy);
    }
}
