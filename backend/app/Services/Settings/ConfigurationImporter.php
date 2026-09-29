<?php

namespace App\Services\Settings;

use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Support\TimezoneRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Imports a configuration file (Settings → System → Import): the shape the
 * export writes, or one prepared by hand for a new plant.
 *
 *  - Tables are processed in dependency order, whatever order the file uses.
 *  - Each row is upserted by its natural key (a code, a name, or a
 *    combination), so importing the same file twice updates, not duplicates.
 *    A live row wins over a deleted one with the same key; a deleted one that
 *    matches is restored.
 *  - A row's `id` is the file's own reference, never the database's: foreign
 *    keys pointing at rows of the same file (a workstation's `line_id`, a
 *    step's `process_template_id` ...) are rewritten to the ids those rows got
 *    here. A reference into a table the file carries but that did not land is
 *    dropped; one into a table the file does not carry (a warehouse, a
 *    division) is kept only when that row exists here. Any other `*_id`
 *    column - users, for one - is never copied: another database's numbering
 *    means nothing here.
 *  - Columns the table does not have are ignored; arrays/objects are stored
 *    as JSON (a step's `config`, a bench's `operator_screens`).
 *  - System settings: known keys only, each checked against the settings
 *    form's own rule (a pattern must compile, a count must be in range);
 *    values are stored the way the form stores them (JSON), so
 *    `"language": "pl"` and the export's `"language": "\"pl\""` both work.
 *    The production flow mode is never taken from a file; the plant timezone
 *    only when it is a valid zone, and it applies at once.
 */
class ConfigurationImporter
{
    /** Table => natural key columns, in dependency order. */
    public const TABLES = [
        'sites' => ['code'],
        'areas' => ['code'],
        'view_templates' => ['name'],
        'warehouses' => ['code'],
        'lines' => ['code'],
        'workstation_types' => ['code'],
        'workstations' => ['code'],
        'material_types' => ['code'],
        'product_types' => ['code'],
        'line_product_type' => ['line_id', 'product_type_id'],
        'skills' => ['code'],
        'personnel_classes' => ['code'],
        'process_segments' => ['code'],
        'label_templates' => ['name'],
        'process_templates' => ['product_type_id', 'name'],
        'materials' => ['code'],
        'template_steps' => ['process_template_id', 'step_number'],
        // A line is a material or a component product type: both are part of its identity.
        'bom_items' => ['process_template_id', 'template_step_id', 'material_id', 'product_type_id'],
        'lot_sequences' => ['product_type_id', 'purpose'],
        'scrap_reasons' => ['code'],
        'issue_types' => ['code'],
        'shifts' => ['code'],
        'line_statuses' => ['name'],
        'dashboard_widgets' => ['widget_id'],
        'maintenance_schedules' => ['name'],
        'inspection_plans' => ['name'],
    ];

    /** Table => [foreign key column => referenced table]. */
    public const REFERENCES = [
        'areas' => ['site_id' => 'sites'],
        'lines' => ['area_id' => 'areas', 'view_template_id' => 'view_templates', 'division_id' => 'divisions', 'warehouse_id' => 'warehouses'],
        'workstations' => ['line_id' => 'lines', 'workstation_type_id' => 'workstation_types'],
        'materials' => ['material_type_id' => 'material_types', 'producing_process_template_id' => 'process_templates'],
        'line_product_type' => ['line_id' => 'lines', 'product_type_id' => 'product_types'],
        'process_segments' => ['workstation_type_id' => 'workstation_types'],
        'process_templates' => ['product_type_id' => 'product_types'],
        'template_steps' => ['process_template_id' => 'process_templates', 'workstation_id' => 'workstations', 'workstation_type_id' => 'workstation_types', 'process_segment_id' => 'process_segments'],
        'bom_items' => ['process_template_id' => 'process_templates', 'template_step_id' => 'template_steps', 'material_id' => 'materials', 'product_type_id' => 'product_types'],
        'lot_sequences' => ['product_type_id' => 'product_types'],
        'shifts' => ['line_id' => 'lines'],
        'line_statuses' => ['line_id' => 'lines'],
        'maintenance_schedules' => ['line_id' => 'lines', 'workstation_id' => 'workstations', 'tool_id' => 'tools', 'cost_source_id' => 'cost_sources'],
        'inspection_plans' => ['material_id' => 'materials', 'material_type_id' => 'material_types', 'root_id' => 'inspection_plans'],
    ];

    /** Settings the import may write (the settings form's own keys) besides those already stored. */
    public const SETTINGS = [
        'production_period', 'allow_overproduction', 'block_negative_stock', 'force_sequential_steps',
        'workstation_routing_enabled', 'backflush_on_pallet_creation', 'pallet_stock_documents', 'hold_on_material_shortage', 'lot_tracking_enabled', 'lot_picking_strategy',
        'workflow_mode', 'pin_login_enabled', 'language', 'app_timezone', 'schedule_view_mode', 'schedule_shifts_per_day',
        'schedule_horizon_weeks', 'schedule_show_weekends', 'realtime_mode', 'production_tracking_mode',
        'production_qty_edit_policy', 'production_qty_edit_window_minutes', 'scanner_mode',
        'unit_identifier_normalize', 'unit_serial_pattern', 'unit_psn_pattern', 'unit_psn_required', 'unit_psn_unique',
        'unit_test_fail_policy', 'unit_test_max_attempts', 'unit_test_attempts_scope', 'standard_weekly_hours', 'default_currency',
        'default_pay_type', 'default_pay_rate', 'enabled_modules',
    ];

    /** Never taken from a file: infrastructure, security, and switches that need their own checks. */
    public const FORBIDDEN_SETTINGS = [
        'app_key', 'app_debug', 'app_env',
        'db_host', 'db_port', 'db_database', 'db_username', 'db_password', 'db_connection',
        'mail_host', 'mail_port', 'mail_username', 'mail_password',
        'cors_allowed_origins', 'cors_allowed_methods',
        'modules_enabled',
        // Switched only on its own form, with its own checks.
        'production_flow_mode',
    ];

    private const SKIP_COLUMNS = ['id', 'created_at', 'updated_at', 'tenant_id', 'deleted_at', 'deleted_by_id'];

    /** @var array<string, array<int|string, int>> table => file id => database id */
    private array $ids = [];

    /** @var array<string, true> tables the file carries rows for */
    private array $fileTables = [];

    /** @var array<string, mixed>|null the settings form's rules, per key */
    private ?array $settingRules = null;

    /**
     * @param  array<string, mixed>  $data  the decoded file
     * @return int rows and settings written
     */
    public function import(array $data): int
    {
        // Backward compat: old files carried only 'settings'.
        if (isset($data['settings']) && ! isset($data['system_settings'])) {
            $data['system_settings'] = $data['settings'];
        }
        $this->ids = [];
        $this->fileTables = [];
        foreach (array_keys(self::TABLES) as $table) {
            if (! empty($data[$table]) && is_array($data[$table])) {
                $this->fileTables[$table] = true;
            }
        }
        $count = 0;

        DB::transaction(function () use ($data, &$count) {
            if (is_array($data['system_settings'] ?? null)) {
                $count += $this->importSettings($data['system_settings']);
            }
            foreach (self::TABLES as $table => $key) {
                if (! isset($this->fileTables[$table]) || ! Schema::hasTable($table)) {
                    continue;
                }
                $columns = Schema::getColumnListing($table);
                foreach ($data[$table] as $row) {
                    if (is_array($row) && $this->importRow($table, $key, $columns, $row)) {
                        $count++;
                    }
                }
            }
        });

        return $count;
    }

    private function importSettings(array $settings): int
    {
        $existing = DB::table('system_settings')->pluck('key')->all();
        $count = 0;
        foreach ($settings as $key => $value) {
            $key = (string) $key;
            if ($key === TimezoneRegistry::SETTING_KEY) {
                // Only a zone the settings form offers, stored the way it stores
                // it - and in force for the rest of this request (a scenario in
                // the same file reads its times in the plant's zone).
                $zone = is_string($value) ? (json_decode($value, true) ?? $value) : null;
                if (is_string($zone) && TimezoneRegistry::isValid($zone)) {
                    TimezoneRegistry::save($zone);
                    TimezoneRegistry::apply();
                    $count++;
                }

                continue;
            }
            if (in_array(strtolower($key), self::FORBIDDEN_SETTINGS, true)
                || (! in_array($key, $existing, true) && ! in_array($key, self::SETTINGS, true))) {
                continue;
            }
            $stored = self::settingValue($value);
            if ($stored === null || strlen($stored) > 1000 || ! $this->settingPasses($key, json_decode($stored, true))) {
                continue;
            }
            DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => $stored]);
            $count++;
        }

        return $count;
    }

    /** The settings form's rule for this key (without "required"), when it has one. */
    private function settingPasses(string $key, mixed $value): bool
    {
        $this->settingRules ??= (new UpdateSystemSettingsRequest)->rules();
        if (! array_key_exists($key, $this->settingRules)) {
            return true; // a stored key the form does not own is kept as before
        }
        $clean = fn (mixed $rule) => collect(is_array($rule) ? $rule : explode('|', (string) $rule))
            ->reject(fn ($r) => is_string($r) && ($r === 'sometimes' || str_starts_with($r, 'required')))
            ->prepend('nullable')->values()->all();
        $rules = [$key => $clean($this->settingRules[$key])];
        if (array_key_exists($key.'.*', $this->settingRules)) {
            $rules[$key.'.*'] = $clean($this->settingRules[$key.'.*']);
        }

        return Validator::make([$key => $value], $rules)->passes();
    }

    /** A setting as the form stores it: JSON text. An exported value already is. */
    private static function settingValue(mixed $value): ?string
    {
        if (is_string($value)) {
            json_decode($value);

            return json_last_error() === JSON_ERROR_NONE ? $value : json_encode($value);
        }
        if (is_bool($value) || is_int($value) || is_float($value) || is_array($value) || $value === null) {
            return json_encode($value);
        }

        return null;
    }

    private function importRow(string $table, array $key, array $columns, array $row): bool
    {
        // Soft-deleted rows in an export stay out.
        if (! empty($row['deleted_at'])) {
            return false;
        }
        $fileId = $row['id'] ?? null;
        $references = self::REFERENCES[$table] ?? [];

        foreach ($references as $column => $refTable) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = $this->resolve($refTable, $row[$column]);
            }
        }
        // Files from before sequences had a purpose: every sequence was a LOT one.
        if ($table === 'lot_sequences' && empty($row['purpose'])) {
            $row['purpose'] = 'lot';
        }
        if ($table === 'template_steps' && isset($row['config'])) {
            $row['config'] = $this->resolveStepConfig($row['config']);
        }

        $values = [];
        foreach ($row as $column => $value) {
            if (! in_array($column, $columns, true) || in_array($column, self::SKIP_COLUMNS, true) || $value === null) {
                continue;
            }
            // Another database's ids (a user, a record we do not import) mean nothing here.
            if (str_ends_with($column, '_id') && ! isset($references[$column]) && ! in_array($column, $key, true)) {
                continue;
            }
            $values[$column] = is_array($value) ? json_encode($value) : $value;
        }
        foreach ($key as $column) {
            // A key column the row leaves empty still has to match that empty value.
            if (! array_key_exists($column, $values) && in_array($column, $columns, true)) {
                $values[$column] = null;
            }
        }
        if ($values === [] || collect($key)->every(fn ($c) => ($values[$c] ?? null) === null)) {
            return false;
        }

        if ($table === 'warehouses' && ! empty($values['is_default'])) {
            $values = $this->keepOneDefaultWarehouse($values);
        }

        $match = array_intersect_key($values, array_flip($key));
        $softDeletes = in_array('deleted_at', $columns, true);
        if ($softDeletes) {
            // A match that was deleted comes back whole - not still marked as deleted by someone.
            $values += ['deleted_at' => null] + (in_array('deleted_by_id', $columns, true) ? ['deleted_by_id' => null] : []);
        }
        $now = now();
        $withTimes = fn (array $v, bool $insert) => $v
            + (in_array('updated_at', $columns, true) ? ['updated_at' => $now] : [])
            + ($insert && in_array('created_at', $columns, true) ? ['created_at' => $now] : []);
        $matching = function () use ($table, $match) {
            $query = DB::table($table);
            foreach ($match as $column => $value) {
                $value === null ? $query->whereNull($column) : $query->where($column, $value);
            }

            return $query;
        };

        try {
            DB::statement('SAVEPOINT config_row');
            $query = $matching();
            if ($softDeletes) {
                // The live row first: a deleted twin with the same code must not be the one updated.
                $query->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END');
            }
            $existing = in_array('id', $columns, true) ? $query->orderBy('id')->first() : $query->first();
            if ($existing) {
                $update = $withTimes(array_diff_key($values, $match), false);
                if ($update !== []) {
                    // By id where there is one: only the chosen row changes, not its deleted twin.
                    (isset($existing->id) ? DB::table($table)->where('id', $existing->id) : $matching())->update($update);
                }
                $newId = $existing->id ?? null;
            } else {
                $insert = $withTimes($values, true);
                if (in_array('id', $columns, true)) {
                    $newId = DB::table($table)->insertGetId($insert);
                } else {
                    DB::table($table)->insert($insert); // a pivot: nothing refers to it by id
                    $newId = null;
                }
            }
            DB::statement('RELEASE SAVEPOINT config_row');
        } catch (\Throwable) {
            DB::statement('ROLLBACK TO SAVEPOINT config_row');

            return false;
        }

        if ($fileId !== null && $newId !== null) {
            $this->ids[$table][$fileId] = (int) $newId;
        }

        return true;
    }

    /**
     * One default warehouse per kind: when the plant already has another, the
     * imported one comes in as a regular warehouse instead of failing (and
     * taking every line that points at it down with it).
     */
    private function keepOneDefaultWarehouse(array $values): array
    {
        $taken = DB::table('warehouses')->whereNull('deleted_at')->where('is_default', true)
            ->where('kind', $values['kind'] ?? null)->where('code', '!=', $values['code'] ?? '')->exists();
        if ($taken) {
            $values['is_default'] = false;
        }

        return $values;
    }

    /**
     * A file id to this database's id. A table the file carries answers only
     * through its own rows; one it does not carry, by an existing row.
     */
    private function resolve(string $table, mixed $fileId): ?int
    {
        if (isset($this->ids[$table][$fileId])) {
            return $this->ids[$table][$fileId];
        }
        if (isset($this->fileTables[$table])) {
            return null;
        }
        if (Schema::hasTable($table) && DB::table($table)->where('id', $fileId)->exists()) {
            return (int) $fileId;
        }

        return null;
    }

    /** A packing step's label template is referenced inside its config. */
    private function resolveStepConfig(mixed $config): mixed
    {
        $decoded = is_string($config) ? json_decode($config, true) : $config;
        if (! is_array($decoded)) {
            return $config;
        }
        if (isset($decoded['label_template_id'])) {
            $id = $this->resolve('label_templates', $decoded['label_template_id']);
            if ($id === null) {
                unset($decoded['label_template_id']);
            } else {
                $decoded['label_template_id'] = $id;
            }
        }

        return $decoded;
    }
}
