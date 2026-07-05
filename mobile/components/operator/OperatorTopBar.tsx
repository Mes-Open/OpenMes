/**
 * Operator top bar — the native twin of the web OperatorLayout header
 * (backend/resources/js/layouts/OperatorLayout.jsx). Operators do NOT get the
 * admin sidebar: this slim top-bar chrome is their whole navigation.
 *
 * Layout mirrors the web 1:1: logo | active line name + ONLINE dot | right:
 * Queue / Workstation nav pills, "Switch Line" button, user avatar + name and a
 * logout icon. Rendered by every root-level operator screen (queue/workstation)
 * so the sidebar-less chrome stays consistent.
 */
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { usePathname, useRouter } from 'expo-router';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { OnlineDot, colors, fonts, radius } from '@openmes/ui';

import { logout } from '@/api/auth';
import { BrandLogo } from '@/components/ui/Brand';
import { HeroIcon } from '@/components/ui/HeroIcon';
import { Mono } from '@/components/ui/Mono';
import { roleColor } from '@/components/ui/RoleBadge';
import { useWorkstations } from '@/hooks/queries/useLines';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { getRole, useAuthStore } from '@/stores/authStore';

export function OperatorTopBar({ variant = 'full' }: { variant?: 'full' | 'minimal' }) {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const pathname = usePathname();
  const qc = useQueryClient();
  const { t } = useTranslation();

  const user = useAuthStore((s) => s.user);
  const role = getRole(user);
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const activeWorkstationId = useAuthStore((s) => s.activeWorkstationId);
  const clear = useAuthStore((s) => s.clear);

  const line = (user?.lines ?? []).find((l) => l.id === activeLineId) ?? null;
  const workstationsQuery = useWorkstations(activeWorkstationId != null ? activeLineId ?? undefined : undefined);
  const workstation =
    activeWorkstationId != null
      ? (workstationsQuery.data ?? []).find((w) => w.id === activeWorkstationId)?.name
      : undefined;

  const logoutMutation = useMutation({
    mutationFn: () => logout().catch(() => undefined),
    onSettled: () => {
      qc.clear();
      clear();
    },
  });

  const confirmLogout = () =>
    Alert.alert(t('Sign out'), t('Sign out {{name}}?', { name: user?.username ?? '' }), [
      { text: t('Cancel'), style: 'cancel' },
      { text: t('Sign out'), style: 'destructive', onPress: () => logoutMutation.mutate() },
    ]);

  const initials = (() => {
    const name = user?.name ?? user?.username ?? '';
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return (name.slice(0, 2) || '?').toUpperCase();
  })();

  const isQueue = pathname.startsWith('/operator/queue') || pathname.startsWith('/operator/work-order');
  const isWorkstation = pathname.startsWith('/operator/workstation');
  // Phones move the Queue/Workstation pills + Switch Line into the operator
  // bottom navigation — the top bar keeps logo, line, online dot and user.
  const { useTabletLayout: isTablet } = useDeviceClass();
  const showNav = variant === 'full' && line != null && isTablet;
  const showLine = variant === 'full' && line != null;

  return (
    <View style={[styles.bar, { paddingTop: insets.top + 8, backgroundColor: colors.card }]}>
      <Pressable onPress={() => router.push('/select-line' as never)} hitSlop={6}>
        <BrandLogo size={16} />
      </Pressable>

      {showLine ? (
        <View style={styles.lineBlock}>
          <Text style={styles.lineName} numberOfLines={1}>
            {line?.name}
          </Text>
          {workstation ? (
            <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8}>
              {workstation}
            </Mono>
          ) : null}
        </View>
      ) : null}

      <OnlineDot label={t('ONLINE')} pulse style={styles.dot} />

      <View style={styles.right}>
        {showNav ? (
          <View style={styles.pills}>
            <TopPill label={t('Queue')} active={isQueue} onPress={() => router.replace('/operator/queue' as never)} />
            <TopPill
              label={t('Workstation')}
              active={isWorkstation}
              onPress={() => router.replace('/operator/workstation' as never)}
            />
            <Pressable
              onPress={() => router.push('/select-line' as never)}
              style={({ pressed }) => [styles.switchBtn, pressed && styles.pressed]}>
              <Text style={styles.switchText}>{t('Switch Line')}</Text>
            </Pressable>
          </View>
        ) : null}

        <Pressable
          onPress={confirmLogout}
          style={styles.userRow}
          accessibilityRole="button"
          accessibilityLabel={t('Sign out')}>
          <View style={[styles.avatar, { backgroundColor: roleColor(role) }]}>
            <Text style={styles.avatarText}>{initials}</Text>
          </View>
          <Text style={styles.userName} numberOfLines={1}>
            {user?.name ?? user?.username ?? t('User')}
          </Text>
          <HeroIcon name="logout" size={16} color={colors.faint} />
        </Pressable>
      </View>
    </View>
  );
}

function TopPill({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected: active }}
      style={({ pressed }) => [
        styles.pill,
        active && styles.pillActive,
        pressed && !active && styles.pressed,
      ]}>
      <Text style={[styles.pillText, active && styles.pillTextActive]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 14,
    paddingBottom: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line,
  },
  lineBlock: {
    minWidth: 0,
    flexShrink: 1,
    borderLeftWidth: 1,
    borderLeftColor: colors.line,
    paddingLeft: 12,
  },
  lineName: { fontSize: 15, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  dot: { flexShrink: 0, alignSelf: 'center' },
  right: { marginLeft: 'auto', flexDirection: 'row', alignItems: 'center', gap: 10 },
  pills: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  pill: { paddingVertical: 9, paddingHorizontal: 14, borderRadius: radius.sm },
  pillActive: { backgroundColor: colors.ink },
  pillText: { fontSize: 13, fontFamily: fonts.sans.native.semibold, color: colors.muted },
  pillTextActive: { color: colors.onInk },
  switchBtn: {
    paddingVertical: 9,
    paddingHorizontal: 12,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
  },
  switchText: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.muted },
  userRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  avatar: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: '#fff', fontFamily: fonts.mono.native.semibold, fontSize: 12 },
  userName: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink, maxWidth: 120 },
  pressed: { opacity: 0.7 },
});
