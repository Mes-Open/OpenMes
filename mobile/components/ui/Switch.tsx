// Light-only v1: thin wrapper over the shared @openmes/ui Switch. Keeps this
// app's value/onValueChange API (and the deprecated dark/onColor props, which
// are ignored) while delegating the 42×24 Geist White geometry + animation to
// the design-system twin — single source of truth for the control itself.
import { type StyleProp, type ViewStyle } from 'react-native';

import { Switch as UISwitch } from '@openmes/ui';

interface Props {
  value: boolean;
  onValueChange?: (next: boolean) => void;
  disabled?: boolean;
  /** @deprecated kept for API compatibility — ignored in light-only v1. */
  dark?: boolean;
  /** @deprecated kept for API compatibility — the "on" track is always accent. */
  onColor?: string;
  style?: StyleProp<ViewStyle>;
}

export function Switch({ value, onValueChange, disabled, style }: Props) {
  return <UISwitch checked={value} onChange={onValueChange} disabled={disabled} style={style} />;
}
