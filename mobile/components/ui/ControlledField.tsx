// Light-only v1: no theming here — RHF wiring only; visuals delegate to the Geist White-restyled Field.
import { Controller, type Control, type FieldPath, type FieldValues } from 'react-hook-form';
import type { TextInputProps } from 'react-native';

import { Field } from '@/components/ui/Field';

interface Props<T extends FieldValues> extends Omit<TextInputProps, 'value' | 'onChangeText' | 'onBlur'> {
  control: Control<T>;
  name: FieldPath<T>;
  label: string;
  hint?: string;
  labelHint?: string;
  required?: boolean;
  mono?: boolean;
  suffix?: React.ReactNode;
}

export function ControlledField<T extends FieldValues>({
  control,
  name,
  label,
  hint,
  labelHint,
  required,
  mono,
  suffix,
  ...rest
}: Props<T>) {
  return (
    <Controller
      control={control}
      name={name}
      render={({ field: { value, onChange, onBlur }, fieldState: { error } }) => (
        <Field
          label={label}
          hint={hint}
          labelHint={labelHint}
          required={required}
          mono={mono}
          suffix={suffix}
          value={value == null ? '' : String(value)}
          onChangeText={onChange}
          onBlur={onBlur}
          error={error?.message}
          {...rest}
        />
      )}
    />
  );
}
