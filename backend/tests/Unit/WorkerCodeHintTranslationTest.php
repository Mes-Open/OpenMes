<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The helper text under the worker "Code" field (worker form and the worker
 * profile section of the account form) must be translated in en.json and
 * pl.json, and the forms must use exactly those keys — a typo in either place
 * silently falls back to untranslated English.
 */
class WorkerCodeHintTranslationTest extends TestCase
{
    private const WORKER_FORM_HINT = 'Unique identifier of this worker across the whole system, e.g. W-201. No two workers can share a code.';

    private const ACCOUNT_FORM_HINT = 'Unique identifier of the worker profile linked to this account, e.g. W-201. Saving creates the profile with this code (or updates the linked one); no two workers can share a code.';

    public static function hints(): array
    {
        return [
            'worker form' => [self::WORKER_FORM_HINT, 'resources/js/Pages/admin/workers/WorkerForm.jsx'],
            'account form' => [self::ACCOUNT_FORM_HINT, 'resources/js/Pages/admin/users/UserForm.jsx'],
        ];
    }

    private function lang(string $locale): array
    {
        return json_decode(
            file_get_contents(dirname(__DIR__, 2)."/lang/{$locale}.json"),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    #[DataProvider('hints')]
    public function test_hint_is_translated_in_english_and_polish(string $key): void
    {
        $en = $this->lang('en');
        $pl = $this->lang('pl');

        $this->assertSame($key, $en[$key] ?? null, 'en.json value must equal its key');
        $this->assertArrayHasKey($key, $pl);
        $this->assertNotSame('', trim($pl[$key]));
        $this->assertNotSame($key, $pl[$key], 'pl.json must carry a Polish translation');
        $this->assertStringContainsString('W-201', $pl[$key]);
    }

    #[DataProvider('hints')]
    public function test_form_renders_the_translated_hint(string $key, string $component): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$component);

        $this->assertStringContainsString("__('{$key}')", $source);
    }
}
