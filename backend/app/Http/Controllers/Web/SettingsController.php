<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRoleTabAccessRequest;
use App\Support\TabRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Sanctum\PersonalAccessToken;

class SettingsController extends Controller
{
    /**
     * Show settings page
     */
    public function index()
    {
        $pinSetting = DB::table('system_settings')->where('key', 'pin_login_enabled')->first();
        $pinLoginEnabled = json_decode($pinSetting?->value ?? 'false', true) === true;

        return Inertia::render('settings/Index', [
            'pinLoginEnabled' => $pinLoginEnabled,
            'hasPin' => ! empty(auth()->user()->pin),
            'twoFactorEnabled' => (bool) auth()->user()->two_factor_enabled,
        ]);
    }

    /**
     * Show change password form
     */
    public function showChangePasswordForm()
    {
        return Inertia::render('settings/ChangePassword');
    }

    /**
     * Update user's password
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = auth()->user();

        // Verify current password
        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['password']),
            'force_password_change' => false,
        ]);

        return redirect()->route('settings.index')
            ->with('success', 'Password changed successfully.');
    }

    /**
     * Show profile edit form
     */
    public function showProfileForm()
    {
        return Inertia::render('settings/Profile');
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}\s\.\-\']+$/u'],
            'email' => 'required|string|email|max:255|unique:users,email,'.auth()->id(),
        ], [
            'name.regex' => 'Name may only contain letters, numbers, spaces, dots, hyphens, and apostrophes.',
        ]);

        auth()->user()->update($validated);

        return redirect()->route('settings.index')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Show admin-only system settings page.
     */
    public function showSystemSettings()
    {
        $rows = DB::table('system_settings')->get()->keyBy('key');

        $settings = [
            'production_period' => json_decode($rows['production_period']?->value ?? '"none"', true) ?? 'none',
            'allow_overproduction' => json_decode($rows['allow_overproduction']?->value ?? 'false', true) ?? false,
            'force_sequential_steps' => json_decode($rows['force_sequential_steps']?->value ?? 'true', true) ?? true,
            'workstation_routing_enabled' => json_decode($rows['workstation_routing_enabled']?->value ?? 'false', true) ?? false,
            'workflow_mode' => json_decode($rows['workflow_mode']?->value ?? '"status"', true) ?? 'status',
            'pin_login_enabled' => json_decode($rows['pin_login_enabled']?->value ?? 'false', true) ?? false,
            'language' => json_decode($rows['language']?->value ?? '"en"', true) ?? 'en',
            'schedule_view_mode' => json_decode($rows['schedule_view_mode']?->value ?? '"weekly"', true) ?? 'weekly',
            'schedule_shifts_per_day' => json_decode($rows['schedule_shifts_per_day']?->value ?? '1', true) ?? 1,
            'schedule_horizon_weeks' => json_decode($rows['schedule_horizon_weeks']?->value ?? '6', true) ?? 6,
            'schedule_show_weekends' => json_decode($rows['schedule_show_weekends']?->value ?? 'true', true) ?? true,
            'schedule_slot_duration_hours' => json_decode($rows['schedule_slot_duration_hours']?->value ?? '8', true) ?? 8,
            'realtime_mode' => json_decode($rows['realtime_mode']?->value ?? '"polling"', true) ?? 'polling',
            'production_tracking_mode' => json_decode($rows['production_tracking_mode']?->value ?? '"per_operation"', true) ?? 'per_operation',
            'cors_allowed_origins' => json_decode($rows['cors_allowed_origins']?->value ?? '"*"', true) ?? '*',
            'production_qty_edit_policy' => json_decode($rows['production_qty_edit_policy']?->value ?? '"none"', true) ?? 'none',
            'production_qty_edit_window_minutes' => json_decode($rows['production_qty_edit_window_minutes']?->value ?? '1', true) ?? 1,
            'scanner_mode' => json_decode($rows['scanner_mode']?->value ?? '"hid"', true) ?? 'hid',
            'standard_weekly_hours' => json_decode($rows['standard_weekly_hours']?->value ?? '40', true) ?? 40,
            'default_currency' => json_decode($rows['default_currency']?->value ?? '"PLN"', true) ?? 'PLN',
            'default_pay_type' => json_decode($rows['default_pay_type']?->value ?? '"hourly"', true) ?? 'hourly',
            'default_pay_rate' => json_decode($rows['default_pay_rate']?->value ?? 'null', true),
        ];

        // Same source as the validation rule and the language switcher.
        $availableLocales = config('app.available_locales', ['en' => 'English']);

        // Append CORS fields not in the standard settings map (they may exist in DB)
        $corsRow = DB::table('system_settings')->where('key', 'cors_allowed_methods')->first();
        $settings['cors_allowed_methods'] = json_decode($corsRow?->value ?? '"GET, POST"', true) ?? 'GET, POST';
        $corsMaxRow = DB::table('system_settings')->where('key', 'cors_max_age')->first();
        $settings['cors_max_age'] = json_decode($corsMaxRow?->value ?? '0', true) ?? 0;

        return Inertia::render('settings/System', [
            'settings' => $settings,
            'availableLocales' => $availableLocales,
            'appUrl' => config('app.url'),
            'modules' => \App\Support\ModuleRegistry::forForm(),
        ]);
    }

    /**
     * Show PIN setup form.
     */
    public function showPinForm()
    {
        $pinEnabled = json_decode(
            DB::table('system_settings')->where('key', 'pin_login_enabled')->value('value') ?? 'false',
            true
        );

        if (! $pinEnabled) {
            return redirect()->route('settings.index')
                ->with('error', 'PIN login is not enabled by administrator.');
        }

        $hasPin = ! empty(auth()->user()->pin);

        return Inertia::render('settings/Pin', compact('hasPin'));
    }

    /**
     * Set or update the user's PIN.
     */
    public function updatePin(\App\Http\Requests\UpdatePinRequest $request)
    {
        $pinEnabled = json_decode(
            DB::table('system_settings')->where('key', 'pin_login_enabled')->value('value') ?? 'false',
            true
        );

        if (! $pinEnabled) {
            return redirect()->route('settings.index')
                ->with('error', 'PIN login is not enabled by administrator.');
        }

        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        auth()->user()->update([
            'pin' => Hash::make($validated['pin']),
        ]);

        return redirect()->route('settings.index')
            ->with('success', 'PIN set successfully. You can now use it to log in.');
    }

    /**
     * Remove the user's PIN.
     */
    public function removePin(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
        ]);

        if (! Hash::check($validated['current_password'], auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        auth()->user()->update(['pin' => null]);

        return redirect()->route('settings.index')
            ->with('success', 'PIN removed.');
    }

    /**
     * Show API tokens management page (admin only).
     */
    public function showApiTokens()
    {
        $tokens = PersonalAccessToken::where('tokenable_type', 'App\Models\User')
            ->with('tokenable')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'tokenable_name' => $t->tokenable?->name ?? 'Unknown',
                'created_at_formatted' => $t->created_at->translatedFormat('d M Y, H:i'),
                'last_used_at_human' => $t->last_used_at?->diffForHumans(),
            ]);

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $tokens,
            'newToken' => session('new_token'),
            'newTokenName' => session('new_token_name'),
            'appUrl' => config('app.url'),
        ]);
    }

    /**
     * Create a new API token (admin only).
     */
    public function createApiToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $user = auth()->user();
        $token = $user->createToken($validated['name']);

        return redirect()->route('settings.api-tokens')
            ->with('new_token', $token->plainTextToken)
            ->with('new_token_name', $validated['name']);
    }

    /**
     * Revoke (delete) an API token (admin only).
     */
    public function revokeApiToken(Request $request, PersonalAccessToken $token)
    {
        abort_if(
            $token->tokenable_id !== auth()->id() || $token->tokenable_type !== get_class(auth()->user()),
            403
        );

        $token->delete();

        return redirect()->route('settings.api-tokens')
            ->with('success', 'Token revoked successfully.');
    }

    /**
     * Load sample data (admin only).
     */
    public function loadSampleData()
    {
        Artisan::call('db:seed', ['--class' => 'PrintShopDemoSeeder', '--force' => true]);

        return redirect()->route('settings.system')
            ->with('success', 'Sample data loaded successfully. Lines, work orders, operators and product types have been created.');
    }

    /**
     * Update system settings (admin only).
     */
    public function updateSystemSettings(Request $request)
    {
        $validated = $request->validate([
            'production_period' => 'required|in:none,weekly,monthly',
            'allow_overproduction' => 'nullable|boolean',
            'force_sequential_steps' => 'nullable|boolean',
            'workstation_routing_enabled' => 'nullable|boolean',
            'workflow_mode' => 'required|in:status,board_status',
            'pin_login_enabled' => 'nullable|boolean',
            // Single source of truth — the language switcher's configured locales.
            'language' => ['nullable', Rule::in(array_keys(config('app.available_locales', [])))],
            'schedule_view_mode' => 'required|in:weekly,daily,monthly',
            'schedule_shifts_per_day' => 'required|integer|in:1,2,3,4',
            'schedule_horizon_weeks' => 'required|integer|min:1|max:52',
            'schedule_show_weekends' => 'nullable|boolean',
            'realtime_mode' => 'required|in:polling,off',
            'production_tracking_mode' => 'required|in:per_operation,cumulative,hybrid',
            'cors_allowed_origins' => 'nullable|string|max:1000',
            'cors_allowed_methods' => 'nullable|string|max:200',
            'cors_max_age' => 'nullable|integer|min:0|max:86400',
            'production_qty_edit_policy' => 'required|in:none,timed,full',
            'production_qty_edit_window_minutes' => 'required_if:production_qty_edit_policy,timed|integer|min:1|max:60',
            'scanner_mode' => 'required|in:hid,manual',
            'standard_weekly_hours' => 'nullable|numeric|min:1|max:168',
            'default_currency' => 'nullable|string|size:3',
            'default_pay_type' => 'nullable|in:hourly,weekly,piece_rate',
            'default_pay_rate' => 'nullable|numeric|min:0',
            // Optional feature modules (#144).
            'enabled_modules' => 'nullable|array',
            'enabled_modules.*' => ['string', Rule::in(\App\Support\ModuleRegistry::optionalKeys())],
        ]);

        $shiftsPerDay = (int) $validated['schedule_shifts_per_day'];
        $slotDuration = $shiftsPerDay > 0 ? (int) (24 / $shiftsPerDay) : 8;

        $map = [
            'production_period' => $validated['production_period'],
            'allow_overproduction' => (bool) ($validated['allow_overproduction'] ?? false),
            'force_sequential_steps' => (bool) ($validated['force_sequential_steps'] ?? false),
            'workstation_routing_enabled' => (bool) ($validated['workstation_routing_enabled'] ?? false),
            'workflow_mode' => $validated['workflow_mode'],
            'pin_login_enabled' => (bool) ($validated['pin_login_enabled'] ?? false),
            'language' => $validated['language'] ?? 'en',
            'schedule_view_mode' => $validated['schedule_view_mode'],
            'schedule_shifts_per_day' => $shiftsPerDay,
            'schedule_horizon_weeks' => (int) $validated['schedule_horizon_weeks'],
            'schedule_show_weekends' => (bool) ($validated['schedule_show_weekends'] ?? false),
            'schedule_slot_duration_hours' => $slotDuration,
            'realtime_mode' => $validated['realtime_mode'],
            'production_tracking_mode' => $validated['production_tracking_mode'],
            'cors_allowed_origins' => trim($validated['cors_allowed_origins'] ?? '') ?: '',
            'cors_allowed_methods' => trim($validated['cors_allowed_methods'] ?? 'GET, POST') ?: 'GET, POST',
            'cors_max_age' => max(0, min(86400, (int) ($validated['cors_max_age'] ?? 0))),
            'production_qty_edit_policy' => $validated['production_qty_edit_policy'],
            'production_qty_edit_window_minutes' => (int) ($validated['production_qty_edit_window_minutes'] ?? 1),
            'scanner_mode' => $validated['scanner_mode'],
            'standard_weekly_hours' => (float) ($validated['standard_weekly_hours'] ?? 40),
            'default_currency' => strtoupper($validated['default_currency'] ?? 'PLN'),
            'default_pay_type' => $validated['default_pay_type'] ?? 'hourly',
            'default_pay_rate' => isset($validated['default_pay_rate']) && $validated['default_pay_rate'] !== null
                ? (float) $validated['default_pay_rate']
                : null,
        ];

        $previousLanguage = json_decode(
            DB::table('system_settings')->where('key', 'language')->value('value') ?? 'null',
            true
        );

        foreach ($map as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value)]
            );
        }

        // Optional feature modules (#144) — only when the section was submitted,
        // so saving unrelated settings never resets the module selection.
        if ($request->has('enabled_modules')) {
            \App\Support\ModuleRegistry::save($validated['enabled_modules'] ?? []);
        }

        Cache::forget('cors_allowed_origins');

        // Only realign the session locale when the language actually changed,
        // so saving an unrelated setting does not clobber a per-session
        // language the user picked via the switcher.
        if ($map['language'] !== $previousLanguage) {
            $request->session()->put('locale', $map['language']);
        }

        return redirect()->route('settings.system')
            ->with('success', 'System settings updated.');
    }

    /**
     * Export full system configuration as JSON file
     */
    public function exportSettings()
    {
        $export = [
            'exported_at' => now()->toISOString(),
            'version' => config('version.current'),
            'system_settings' => DB::table('system_settings')->pluck('value', 'key')->toArray(),
        ];

        $tables = [
            'lines', 'workstations', 'product_types', 'process_templates',
            'template_steps', 'material_types', 'materials', 'bom_items',
            'issue_types', 'shifts', 'line_statuses', 'dashboard_widgets',
            'maintenance_schedules', 'sites', 'areas', 'skills',
            'personnel_classes', 'process_segments',
        ];

        foreach ($tables as $table) {
            try {
                $export[$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->toArray();
            } catch (\Exception $e) {
                // table may not exist yet
            }
        }

        // Add optional tables only if they exist
        $optionalTables = ['inspection_plans', 'view_templates', 'label_templates'];
        foreach ($optionalTables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    $export[$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->toArray();
                }
            } catch (\Exception $e) {
                // table may not exist yet
            }
        }

        return response()->json($export, 200, [
            'Content-Disposition' => 'attachment; filename="openmes-config-'.date('Y-m-d').'.json"',
        ]);
    }

    /**
     * Import system configuration from JSON file
     */
    public function importSettings(Request $request)
    {
        $request->validate([
            'settings_file' => 'required|file|mimes:json,txt|max:10240',
        ]);

        try {
            $content = file_get_contents($request->file('settings_file')->getRealPath());
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->with('error', __('Invalid JSON file.'));
            }

            // Backward compat: old format with just 'settings' key
            if (isset($data['settings']) && ! isset($data['system_settings'])) {
                $data['system_settings'] = $data['settings'];
            }

            $allowedTables = [
                'system_settings', 'lines', 'workstations', 'product_types',
                'process_templates', 'template_steps', 'material_types', 'materials',
                'bom_items', 'issue_types', 'shifts', 'line_statuses',
                'dashboard_widgets', 'maintenance_schedules',
                'sites', 'areas', 'skills', 'personnel_classes', 'process_segments',
                'inspection_plans', 'view_templates', 'label_templates',
            ];

            $skipColumns = ['id', 'created_at', 'updated_at', 'tenant_id'];

            // Forbidden system_settings keys
            $forbiddenSettings = [
                'app_key', 'app_debug', 'app_env',
                'db_host', 'db_port', 'db_database', 'db_username', 'db_password', 'db_connection',
                'mail_host', 'mail_port', 'mail_username', 'mail_password',
                'cors_allowed_origins', 'cors_allowed_methods',
                'modules_enabled',
            ];

            $imported = 0;

            DB::beginTransaction();

            foreach ($data as $tableName => $rows) {
                if (! in_array($tableName, $allowedTables, true)) {
                    continue;
                }
                if (! is_array($rows)) {
                    continue;
                }
                if (! Schema::hasTable($tableName)) {
                    continue;
                }

                if ($tableName === 'system_settings') {
                    // Special handling: key-value update, not replace
                    $existingKeys = DB::table('system_settings')->pluck('key')->toArray();

                    foreach ($rows as $key => $value) {
                        if (in_array(strtolower($key), $forbiddenSettings, true)) {
                            continue;
                        }
                        if (! is_string($value) && ! is_numeric($value)) {
                            continue;
                        }
                        if (strlen((string) $value) > 1000) {
                            continue;
                        }
                        if (! in_array($key, $existingKeys, true)) {
                            continue;
                        }

                        DB::table('system_settings')->where('key', $key)->update(['value' => (string) $value]);
                        $imported++;
                    }

                    continue;
                }

                // For all other tables: upsert by unique key (code or name)
                if (empty($rows)) {
                    continue;
                }

                // Determine unique key for upsert
                $uniqueKey = match ($tableName) {
                    'lines', 'workstations', 'product_types', 'material_types',
                    'materials', 'issue_types', 'shifts', 'skills',
                    'personnel_classes', 'process_segments', 'sites', 'areas' => 'code',
                    'line_statuses', 'process_templates', 'maintenance_schedules',
                    'inspection_plans', 'label_templates' => 'name',
                    'dashboard_widgets' => 'widget_id',
                    default => null,
                };

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $originalId = $row['id'] ?? null;

                    // Remove auto-generated columns
                    foreach ($skipColumns as $col) {
                        unset($row[$col]);
                    }
                    // Remove null values for columns that might not accept null
                    $row = array_filter($row, fn ($v) => $v !== null);

                    if (empty($row)) {
                        continue;
                    }

                    try {
                        DB::statement('SAVEPOINT row_insert');
                        if ($uniqueKey && isset($row[$uniqueKey])) {
                            DB::table($tableName)->updateOrInsert(
                                [$uniqueKey => $row[$uniqueKey]],
                                $row
                            );
                        } else {
                            DB::table($tableName)->insert($row);
                        }
                        DB::statement('RELEASE SAVEPOINT row_insert');
                        $imported++;
                    } catch (\Exception $e) {
                        DB::statement('ROLLBACK TO SAVEPOINT row_insert');

                        continue;
                    }
                }
            }

            DB::commit();
            Cache::flush();

            return back()->with('success', __(':count configuration items imported successfully.', ['count' => $imported]));
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', __('Failed to import settings. Please check the file and try again.'));
        }
    }

    /**
     * Show the role × tab access matrix. Rows are tabs, columns are roles;
     * Admin is locked to full access (handled in the UI + Gate::before).
     */
    public function showAccess()
    {
        $roles = \Spatie\Permission\Models\Role::with('permissions')->orderBy('name')->get();

        $matrix = [];
        foreach ($roles as $role) {
            $matrix[$role->name] = $role->permissions
                ->pluck('name')
                ->filter(fn ($p) => str_starts_with($p, 'tab:'))
                ->map(fn ($p) => substr($p, 4))
                ->values()
                ->all();
        }

        return Inertia::render('settings/Access', [
            'tabs' => collect(TabRegistry::labels())
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values(),
            'roles' => $roles->pluck('name')->values(),
            'matrix' => $matrix,
            'lockedRole' => 'Admin',
        ]);
    }

    /**
     * Persist the matrix: for each role (except the always-full Admin) replace
     * its tab:* permissions with the submitted set, preserving non-tab perms.
     */
    public function updateAccess(UpdateRoleTabAccessRequest $request)
    {
        $access = $request->validated()['access'] ?? [];

        foreach ($access as $roleName => $tabKeys) {
            if ($roleName === 'Admin') {
                continue; // Admin always keeps full access
            }

            $role = \Spatie\Permission\Models\Role::with('permissions')
                ->where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            $nonTab = $role->permissions->pluck('name')
                ->reject(fn ($p) => str_starts_with($p, 'tab:'));

            $tabPerms = collect($tabKeys)
                ->filter(fn ($k) => TabRegistry::exists($k))
                ->map(fn ($k) => TabRegistry::permission($k));

            $role->syncPermissions($nonTab->merge($tabPerms)->unique()->all());
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', __('Tab access updated successfully.'));
    }
}
