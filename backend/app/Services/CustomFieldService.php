<?php

namespace App\Services;

use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use App\Services\Media\ImageSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for turning custom-field *definitions* into: validation
 * rules, the stored value map (including uploaded files), and the frontend render
 * config.
 *
 * Storage model:
 *  - Scalar values live directly in the entity's custom_fields JSON.
 *  - File/Image values are uploaded under `custom_field_files.{key}`, stored on
 *    the PRIVATE disk with a server-generated random name (images re-encoded via
 *    ImageSanitizer), and referenced in custom_fields as
 *    { path, name, mime, size }. Served only through the authenticated download
 *    route — never a public URL.
 */
class CustomFieldService
{
    private const FILE_DIR = 'custom-field-files';

    /** @var array<string, Collection<int, CustomFieldDefinition>> */
    private array $cache = [];

    /** The configured entity registry (config/custom_fields.php). */
    public function entities(): array
    {
        return config('custom_fields.entities', []);
    }

    public function isSupported(string $entityType): bool
    {
        return array_key_exists($entityType, $this->entities());
    }

    /** Active definitions for an entity-type, ordered for display. */
    public function definitionsFor(string $entityType): Collection
    {
        return $this->cache[$entityType] ??= CustomFieldDefinition::query()
            ->forEntity($entityType)
            ->active()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Validation rules. Scalar fields land under `custom_fields.{key}`, uploaded
     * File/Image fields under `custom_field_files.{key}`. Merge into a
     * controller's rules before $request->validate().
     */
    public function rules(string $entityType): array
    {
        $rules = [];

        foreach ($this->definitionsFor($entityType) as $def) {
            $type = $this->typeOf($def);
            $config = $def->config ?? [];

            if ($type->isFile()) {
                // Required is enforced on create only (an existing stored file
                // satisfies it on edit), so validation stays nullable here.
                $rules["custom_field_files.{$def->key}"] = $type->fileRules($config);

                continue;
            }

            $field = $def->required ? ['required'] : ['nullable'];
            $rules["custom_fields.{$def->key}"] = array_merge($field, $type->rules($config));

            if ($type->isMultiValue()) {
                $item = ['string'];
                if ($opts = $type->optionValues($config)) {
                    $item[] = Rule::in($opts);
                }
                $rules["custom_fields.{$def->key}.*"] = $item;
            }
        }

        return $rules;
    }

    /**
     * Build the stored custom_fields map from a request: coerce scalar inputs,
     * store new uploads, preserve or remove existing files. Pass the host model's
     * current custom_fields as $existing on update so untouched files survive.
     */
    public function fromRequest(Request $request, string $entityType, ?array $existing = null): array
    {
        $existing = $existing ?? [];
        $input = (array) $request->input('custom_fields', []);
        $removeFiles = (array) $request->input('custom_field_files_remove', []);
        $out = [];

        foreach ($this->definitionsFor($entityType) as $def) {
            $type = $this->typeOf($def);
            $key = $def->key;

            if ($type->isFile()) {
                $uploaded = $request->file("custom_field_files.{$key}");

                if ($uploaded instanceof UploadedFile) {
                    $this->deleteStoredFile($existing[$key] ?? null);
                    try {
                        $out[$key] = $this->storeFile($uploaded, $type);
                    } catch (\InvalidArgumentException $e) {
                        throw ValidationException::withMessages(["custom_field_files.{$key}" => $e->getMessage()]);
                    }
                } elseif (in_array($key, $removeFiles, true)) {
                    $this->deleteStoredFile($existing[$key] ?? null);
                } elseif (isset($existing[$key])) {
                    $out[$key] = $existing[$key];
                }

                continue;
            }

            if (array_key_exists($key, $input)) {
                $cast = $type->cast($input[$key]);
                if ($cast !== null) {
                    $out[$key] = $cast;
                }
            }
        }

        return $out;
    }

    /**
     * Coerce scalar submitted values (no file handling). Kept for callers/tests
     * that only deal with scalar fields.
     */
    public function cast(array $values, string $entityType): array
    {
        $out = [];

        foreach ($this->definitionsFor($entityType) as $def) {
            if (! array_key_exists($def->key, $values)) {
                continue;
            }

            $cast = $this->typeOf($def)->cast($values[$def->key]);
            if ($cast !== null) {
                $out[$def->key] = $cast;
            }
        }

        return $out;
    }

    /** The field list the frontend renders. */
    public function clientConfig(string $entityType): array
    {
        return $this->definitionsFor($entityType)->map(fn (CustomFieldDefinition $d) => [
            'key' => $d->key,
            'label' => $d->label,
            'type' => $this->typeOf($d)->value,
            'required' => (bool) $d->required,
            'config' => $d->config ?? [],
        ])->all();
    }

    /**
     * Validation attribute names so errors read as the field label, for both the
     * scalar and the file request keys.
     */
    public function attributeNames(string $entityType): array
    {
        $names = [];
        foreach ($this->definitionsFor($entityType) as $def) {
            $names["custom_fields.{$def->key}"] = $def->label;
            $names["custom_field_files.{$def->key}"] = $def->label;
        }

        return $names;
    }

    /**
     * Did the request carry any custom-field data? Guards the persist step.
     * Necessary because a file upload makes the request multipart, and an empty
     * `custom_fields: {}` object is dropped by FormData — so checking only
     * `has('custom_fields')` would miss a file-only submission.
     */
    public function touched(Request $request): bool
    {
        return $request->has('custom_fields')
            || $request->hasFile('custom_field_files')
            || $request->has('custom_field_files_remove');
    }

    private function storeFile(UploadedFile $file, CustomFieldType $type): array
    {
        if ($type === CustomFieldType::Image) {
            $clean = app(ImageSanitizer::class)->sanitize($file->getRealPath());
            $path = self::FILE_DIR.'/'.Str::random(40).'.'.$clean['extension'];
            Storage::put($path, $clean['bytes']);

            return [
                'path' => $path,
                'name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime' => $clean['mime'],
                'size' => strlen($clean['bytes']),
            ];
        }

        // Generic file: server-generated random name, sanitized extension.
        $ext = preg_replace('/[^a-z0-9]/', '', strtolower($file->getClientOriginalExtension())) ?: 'bin';
        $name = Str::random(40).'.'.$ext;
        Storage::putFileAs(self::FILE_DIR, $file, $name);

        return [
            'path' => self::FILE_DIR.'/'.$name,
            'name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    /** Delete a previously stored file, guarding the path to our directory. */
    private function deleteStoredFile(?array $meta): void
    {
        $path = $meta['path'] ?? null;
        if (is_string($path) && str_starts_with($path, self::FILE_DIR.'/') && Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    /** Tolerate either an enum (model cast) or a raw string type. */
    private function typeOf(CustomFieldDefinition $def): CustomFieldType
    {
        return $def->type instanceof CustomFieldType
            ? $def->type
            : CustomFieldType::from($def->type);
    }
}
