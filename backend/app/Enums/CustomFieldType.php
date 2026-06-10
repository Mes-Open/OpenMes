<?php

namespace App\Enums;

use Illuminate\Validation\Rule;

/**
 * Custom-field types. Scalar types know how to validate/coerce their own values;
 * File/Image are handled out-of-band by CustomFieldService (uploaded as files,
 * stored on the private disk, referenced by metadata in the custom_fields JSON).
 */
enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Date = 'date';
    case Datetime = 'datetime';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case File = 'file';
    case Image = 'image';

    /** [{ value, label }] for populating a type dropdown in the admin UI. */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => __('Text'),
            self::Textarea => __('Text area'),
            self::Number => __('Number'),
            self::Integer => __('Whole number'),
            self::Boolean => __('Yes / No'),
            self::Date => __('Date'),
            self::Datetime => __('Date & time'),
            self::Select => __('Dropdown'),
            self::Multiselect => __('Multi-select'),
            self::File => __('File'),
            self::Image => __('Image'),
        };
    }

    /** Does this type read its choices from config.options? */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Multiselect], true);
    }

    /** Does this type hold an array of values rather than a scalar? */
    public function isMultiValue(): bool
    {
        return $this === self::Multiselect;
    }

    /** Is this an uploaded-file type (value is stored-file metadata, not a scalar)? */
    public function isFile(): bool
    {
        return in_array($this, [self::File, self::Image], true);
    }

    /**
     * Validation rules for a scalar value of this type. File/Image return [] —
     * their uploads are validated separately via fileRules() under a different
     * request key. The required/nullable rule is prepended by CustomFieldService.
     */
    public function rules(array $config = []): array
    {
        if ($this->isFile()) {
            return [];
        }

        $rules = match ($this) {
            self::Text, self::Textarea, self::Select => ['string'],
            self::Number => ['numeric'],
            self::Integer => ['integer'],
            self::Boolean => ['boolean'],
            self::Date, self::Datetime => ['date'],
            self::Multiselect => ['array'],
            default => [],
        };

        if (in_array($this, [self::Text, self::Textarea], true) && isset($config['max'])) {
            $rules[] = 'max:'.(int) $config['max'];
        }

        if (in_array($this, [self::Number, self::Integer], true)) {
            if (isset($config['min'])) {
                $rules[] = 'min:'.$config['min'];
            }
            if (isset($config['max'])) {
                $rules[] = 'max:'.$config['max'];
            }
        }

        if ($this === self::Select && ($opts = $this->optionValues($config))) {
            $rules[] = Rule::in($opts);
        }

        return $rules;
    }

    /** Validation rules for an uploaded File/Image (under custom_field_files.{key}). */
    public function fileRules(array $config = []): array
    {
        $maxKb = (int) ($config['max_kb'] ?? 10240); // 10 MB default

        return ['nullable', $this === self::Image ? 'image' : 'file', 'max:'.$maxKb];
    }

    /** The allowed scalar values for option-based types. */
    public function optionValues(array $config): array
    {
        return collect($config['options'] ?? [])
            ->map(fn ($o) => is_array($o) ? ($o['value'] ?? null) : $o)
            ->reject(fn ($v) => $v === null)
            ->values()
            ->all();
    }

    /**
     * Coerce a submitted (typically string) value into its stored shape.
     * Returns null for empty values so they don't clutter the JSON column.
     * File/Image are never cast here — the service stores their uploads.
     */
    public function cast(mixed $value): mixed
    {
        if ($this->isFile() || $value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($this) {
            self::Number => (float) $value,
            self::Integer => (int) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Multiselect => array_values(array_filter(
                (array) $value,
                fn ($v) => $v !== null && $v !== '',
            )),
            default => (string) $value,
        };
    }
}
