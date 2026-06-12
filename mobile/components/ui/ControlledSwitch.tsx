// Light-only v1: no theming here — RHF wiring only; visuals delegate to the Geist White-restyled
// Switch (which keeps accepting but ignores the dark/onColor props passed through below).
import { Controller, type Control, type FieldPath, type FieldValues } from 'react-hook-form';

import { Switch } from '@/components/ui/Switch';

interface Props<T extends FieldValues> {
  control: Control<T>;
  name: FieldPath<T>;
  disabled?: boolean;
  dark?: boolean;
  onColor?: string;
}

export function ControlledSwitch<T extends FieldValues>({ control, name, ...rest }: Props<T>) {
  return (
    <Controller
      control={control}
      name={name}
      render={({ field: { value, onChange } }) => (
        <Switch value={!!value} onValueChange={onChange} {...rest} />
      )}
    />
  );
}
