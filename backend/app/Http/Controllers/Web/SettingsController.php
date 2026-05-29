<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class SettingsController extends Controller
{
    /**
     * Show settings page
     */
    public function index()
    {
        return view('settings.index');
    }

    /**
     * Show change password form
     */
    public function showChangePasswordForm()
    {
        return view('settings.change-password');
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
        return view('settings.profile');
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
            'production_period' => json_decode($rows['production_period']->value ?? '"none"', true) ?? 'none',
            'allow_overproduction' => json_decode($rows['allow_overproduction']->value ?? 'false', true) ?? false,
            'force_sequential_steps' => json_decode($rows['force_sequential_steps']->value ?? 'true', true) ?? true,
            'workstation_routing_enabled' => json_decode($rows['workstation_routing_enabled']->value ?? 'false', true) ?? false,
            'workflow_mode' => json_decode($rows['workflow_mode']->value ?? '"status"', true) ?? 'status',
            'pin_login_enabled' => json_decode($rows['pin_login_enabled']->value ?? 'false', true) ?? false,
            'language' => json_decode($rows['language']->value ?? '"en"', true) ?? 'en',
            'schedule_view_mode' => json_decode($rows['schedule_view_mode']->value ?? '"weekly"', true) ?? 'weekly',
            'schedule_shifts_per_day' => json_decode($rows['schedule_shifts_per_day']->value ?? '1', true) ?? 1,
            'schedule_horizon_weeks' => json_decode($rows['schedule_horizon_weeks']->value ?? '6', true) ?? 6,
            'schedule_show_weekends' => json_decode($rows['schedule_show_weekends']->value ?? 'true', true) ?? true,
            'schedule_slot_duration_hours' => json_decode($rows['schedule_slot_duration_hours']->value ?? '8', true) ?? 8,
            'realtime_mode' => json_decode($rows['realtime_mode']->value ?? '"polling"', true) ?? 'polling',
            'production_tracking_mode' => json_decode($rows['production_tracking_mode']->value ?? '"per_operation"', true) ?? 'per_operation',
            'cors_allowed_origins' => json_decode($rows['cors_allowed_origins']->value ?? '"*"', true) ?? '*',
            'production_qty_edit_policy' => json_decode($rows['production_qty_edit_policy']->value ?? '"none"', true) ?? 'none',
            'production_qty_edit_window_minutes' => json_decode($rows['production_qty_edit_window_minutes']->value ?? '1', true) ?? 1,
            'scanner_mode' => json_decode($rows['scanner_mode']->value ?? '"hid"', true) ?? 'hid',
        ];

        return view('settings.system', compact('settings'));
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

        return view('settings.pin', compact('hasPin'));
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
            ->get();

        return view('settings.api-tokens', compact('tokens'));
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
            'language' => 'nullable|in:en,pl,tr',
            'schedule_view_mode' => 'required|in:weekly,daily,monthly',
            'schedule_shifts_per_day' => 'required|integer|in:1,2,3,4',
            'schedule_horizon_weeks' => 'required|integer|min:1|max:52',
            'schedule_show_weekends' => 'nullable|boolean',
            'realtime_mode' => 'required|in:polling,websocket',
            'production_tracking_mode' => 'required|in:per_operation,cumulative,hybrid',
            'cors_allowed_origins' => 'nullable|string|max:1000',
            'cors_allowed_methods' => 'nullable|string|max:200',
            'cors_max_age' => 'nullable|integer|min:0|max:86400',
            'production_qty_edit_policy' => 'required|in:none,timed,full',
            'production_qty_edit_window_minutes' => 'required_if:production_qty_edit_policy,timed|integer|min:1|max:60',
            'scanner_mode' => 'required|in:hid,manual',
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
        ];

        foreach ($map as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value)]
            );
        }

        Cache::forget('cors_allowed_origins');

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
                $export[$table] = DB::table($table)->get()->map(fn($r) => (array) $r)->toArray();
            } catch (\Exception $e) {
                // table may not exist yet
            }
        }

        // Add optional tables only if they exist
        $optionalTables = ['inspection_plans', 'view_templates', 'label_templates'];
        foreach ($optionalTables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    $export[$table] = DB::table($table)->get()->map(fn($r) => (array) $r)->toArray();
                }
            } catch (\Exception $e) {
                // table may not exist yet
            }
        }

        return response()->json($export, 200, [
            'Content-Disposition' => 'attachment; filename="openmes-config-' . date('Y-m-d') . '.json"',
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
            if (isset($data['settings']) && !isset($data['system_settings'])) {
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
                'reverb_app_id', 'reverb_app_key', 'reverb_app_secret',
                'modules_enabled',
            ];

            $imported = 0;

            DB::beginTransaction();

            foreach ($data as $tableName => $rows) {
                if (!in_array($tableName, $allowedTables, true)) continue;
                if (!is_array($rows)) continue;
                if (!Schema::hasTable($tableName)) continue;

                if ($tableName === 'system_settings') {
                    // Special handling: key-value update, not replace
                    $existingKeys = DB::table('system_settings')->pluck('key')->toArray();

                    foreach ($rows as $key => $value) {
                        if (in_array(strtolower($key), $forbiddenSettings, true)) continue;
                        if (!is_string($value) && !is_numeric($value)) continue;
                        if (strlen((string) $value) > 1000) continue;
                        if (!in_array($key, $existingKeys, true)) continue;

                        DB::table('system_settings')->where('key', $key)->update(['value' => (string) $value]);
                        $imported++;
                    }
                    continue;
                }

                // For all other tables: upsert by unique key (code or name)
                if (empty($rows)) continue;

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
                    if (!is_array($row)) continue;

                    $originalId = $row['id'] ?? null;

                    // Remove auto-generated columns
                    foreach ($skipColumns as $col) {
                        unset($row[$col]);
                    }
                    // Remove null values for columns that might not accept null
                    $row = array_filter($row, fn($v) => $v !== null);

                    if (empty($row)) continue;

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
}
