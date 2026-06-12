import { StyleSheet, View } from 'react-native';

import { Mono } from '@/components/ui/Mono';
import type { Role } from '@/types/api';

/**
 * Per-role accent palette — matches the design bundle (om-tablet-frame.jsx).
 * Operator amber, Supervisor blue, Admin red. The same color drives the role
 * badge pill, the footer avatar tile, and any other role-coded chrome.
 */
export const ROLE_COLOR: Record<Role, string> = {
  Operator: '#f5a524',
  Supervisor: '#5b8def',
  Admin: '#ef4444',
};

export function roleColor(role: Role | null | undefined): string {
  if (!role) return ROLE_COLOR.Operator;
  return ROLE_COLOR[role] ?? ROLE_COLOR.Operator;
}

interface Props {
  role: Role | null | undefined;
  /** Tag label override (defaults to the role name uppercase). */
  label?: string;
}

/**
 * Small color-coded pill displayed below the logo on every navigation
 * surface. Visually anchors the user to the role they're currently using.
 */
export function RoleBadge({ role, label }: Props) {
  const color = roleColor(role);
  const text = (label ?? role ?? 'Operator').toUpperCase();
  return (
    <View
      style={[
        styles.badge,
        { backgroundColor: `${color}1c`, borderColor: `${color}40` },
      ]}>
      <Mono size={9} color={color} weight="700" letterSpacing={0.6}>
        {text}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: 'center',
    paddingVertical: 3,
    paddingHorizontal: 6,
    borderRadius: 4,
    borderWidth: 1,
  },
});
