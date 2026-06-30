import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { changePassword, logout } from '@/api/auth';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { getRole, useAuthStore } from '@/stores/authStore';
import { useSettingsStore, type ThemePreference } from '@/stores/settingsStore';

export function ProfileTab() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const router = useRouter();
  const qc = useQueryClient();

  const user = useAuthStore((s) => s.user);
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const setActiveLineId = useAuthStore((s) => s.setActiveLineId);
  const clear = useAuthStore((s) => s.clear);
  const role = getRole(user);

  const theme = useSettingsStore((s) => s.theme);
  const setTheme = useSettingsStore((s) => s.setTheme);

  const [currentPw, setCurrentPw] = useState('');
  const [newPw, setNewPw] = useState('');

  const passwordMutation = useMutation({
    mutationFn: () => changePassword(currentPw, newPw),
    onSuccess: () => {
      setCurrentPw('');
      setNewPw('');
      Alert.alert('Password updated');
    },
    onError: (err: Error) => Alert.alert('Could not update password', err.message),
  });

  const logoutMutation = useMutation({
    mutationFn: () => logout().catch(() => undefined),
    onSettled: () => {
      qc.clear();
      clear();
    },
  });

  const activeLine = user?.lines?.find((l) => l.id === activeLineId);
  const hasMultipleLines = (user?.lines?.length ?? 0) > 1;
  const initials = (user?.username ?? '?').slice(0, 2).toUpperCase();

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title="Profile" />
      <ScrollView
        style={{ backgroundColor: palette.background }}
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled">
        {/* Identity */}
        <View style={styles.identity}>
          <View style={[styles.avatar, { backgroundColor: palette.surfaceInverse }]}>
            <Text
              style={[
                styles.avatarText,
                { color: scheme === 'dark' ? '#1A1917' : '#ffffff', fontFamily: MONO },
              ]}>
              {initials}
            </Text>
          </View>
          <Text style={[styles.username, { color: palette.text }]}>{user?.username ?? '—'}</Text>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
            {(role ?? 'OPERATOR').toUpperCase()}
            {activeLine ? ` · ${activeLine.name.toUpperCase()}` : ''}
          </Mono>
        </View>

        {/* Quick stats — synthetic until backend exposes shift KPIs */}
        <View style={styles.statsRow}>
          <Stat label="ON TASK" value="—" />
          <Stat label="SHIFT" value={shiftLabel()} />
          <Stat label="LINES" value={String(user?.lines?.length ?? 0)} accent={palette.success} />
        </View>

        {/* Line switcher */}
        {hasMultipleLines ? (
          <View>
            <SectionLabel>Active line</SectionLabel>
            <Card>
              <View style={styles.row}>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.lineName, { color: palette.text }]}>
                    {activeLine?.name ?? 'No line selected'}
                  </Text>
                  <Mono size={11} color={palette.textFaint}>
                    SWITCH TO PREVIEW DIFFERENT QUEUES
                  </Mono>
                </View>
                <Button
                  title="Switch"
                  variant="outline"
                  size="sm"
                  onPress={() => {
                    setActiveLineId(null);
                    router.replace('/select-line' as never);
                  }}
                />
              </View>
            </Card>
          </View>
        ) : null}

        {/* Appearance */}
        <View>
          <SectionLabel>Appearance</SectionLabel>
          <Card style={{ gap: 10 }}>
            <View style={styles.themeRow}>
              {(['system', 'light', 'dark'] as const).map((opt) => {
                const active = theme === opt;
                return (
                  <Pressable
                    key={opt}
                    onPress={() => setTheme(opt as ThemePreference)}
                    style={({ pressed }) => [
                      styles.themeChip,
                      {
                        backgroundColor: active ? palette.text : palette.surface,
                        borderColor: active ? palette.text : palette.border,
                        opacity: pressed ? 0.85 : 1,
                      },
                    ]}>
                    <FontAwesome
                      name={opt === 'system' ? 'mobile' : opt === 'light' ? 'sun-o' : 'moon-o'}
                      size={14}
                      color={active ? palette.background : palette.textMuted}
                    />
                    <Mono
                      size={11}
                      weight="700"
                      letterSpacing={0.5}
                      color={active ? palette.background : palette.text}>
                      {opt === 'system' ? 'SYSTEM' : opt === 'light' ? 'LIGHT' : 'DARK'}
                    </Mono>
                  </Pressable>
                );
              })}
            </View>
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.5}>
              {theme === 'system'
                ? 'FOLLOWING OS COLOR SCHEME'
                : `${theme.toUpperCase()} MODE — OVERRIDES OS SETTING`}
            </Mono>
          </Card>
        </View>

        {/* Menu */}
        <View>
          <SectionLabel>Account</SectionLabel>
          <Card style={{ padding: 0, overflow: 'hidden' }}>
            <MenuRow icon="calendar" label="My schedule" sub="View shifts" onPress={() => router.push('/(drawer)/schedule' as never)} />
            <Divider />
            <MenuRow icon="cog" label="Preferences" sub="Coming soon" />
          </Card>
        </View>

        {/* Password */}
        <View>
          <SectionLabel>Change password</SectionLabel>
          <Card style={{ gap: 10 }}>
            <Field label="Current password" value={currentPw} onChangeText={setCurrentPw} secureTextEntry />
            <Field
              label="New password"
              value={newPw}
              onChangeText={setNewPw}
              secureTextEntry
              hint="At least 8 characters"
            />
            <Button
              title="Update password"
              onPress={() => passwordMutation.mutate()}
              loading={passwordMutation.isPending}
              disabled={!currentPw || newPw.length < 8}
            />
          </Card>
        </View>

        <Button
          title="Clock out"
          variant="danger"
          onPress={() => logoutMutation.mutate()}
          loading={logoutMutation.isPending}
          leftIcon={<FontAwesome name="sign-out" size={14} color="#fff" />}
        />
      </ScrollView>
    </View>
  );
}

function Stat({ label, value, accent }: { label: string; value: string; accent?: string }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Card style={[styles.stat]}>
      <Text style={[styles.statValue, { color: accent ?? palette.text, fontFamily: MONO }]}>{value}</Text>
      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>
        {label}
      </Mono>
    </Card>
  );
}

function MenuRow({
  icon,
  label,
  sub,
  onPress,
}: {
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  label: string;
  sub?: string;
  onPress?: () => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.menuRow,
        { backgroundColor: pressed ? palette.surfaceAlt : 'transparent' },
      ]}>
      <View style={[styles.menuIcon, { backgroundColor: palette.surfaceAlt }]}>
        <FontAwesome name={icon} size={14} color={palette.textMuted} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={[styles.menuLabel, { color: palette.text }]}>{label}</Text>
        {sub ? <Mono size={11} color={palette.textFaint}>{sub}</Mono> : null}
      </View>
      <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
    </Pressable>
  );
}

function Divider() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return <View style={{ height: StyleSheet.hairlineWidth, backgroundColor: palette.border, marginHorizontal: 14 }} />;
}

function shiftLabel() {
  const h = new Date().getHours();
  if (h >= 6 && h < 14) return 'A';
  if (h >= 14 && h < 22) return 'B';
  return 'C';
}

const styles = StyleSheet.create({
  container: { padding: 18, gap: 18 },
  identity: { alignItems: 'center', gap: 8, paddingTop: 6 },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { fontSize: 26, fontWeight: '700', letterSpacing: 0.4 },
  username: { fontSize: 22, fontWeight: '600', letterSpacing: -0.3, marginTop: 6 },
  statsRow: { flexDirection: 'row', gap: 8 },
  stat: { flex: 1, padding: 10, alignItems: 'center', gap: 4 },
  statValue: { fontSize: 18, fontWeight: '600', letterSpacing: 0.4 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  lineName: { fontSize: 14, fontWeight: '600', marginBottom: 2 },
  menuRow: { flexDirection: 'row', alignItems: 'center', gap: 14, padding: 14 },
  menuIcon: { width: 34, height: 34, borderRadius: 9, alignItems: 'center', justifyContent: 'center' },
  menuLabel: { fontSize: 14, fontWeight: '600' },
  themeRow: { flexDirection: 'row', gap: 8 },
  themeChip: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
});
