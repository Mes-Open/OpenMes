/**
 * Settings — account, server, language, appearance and password. Loosely
 * mirrors the web settings/Profile page (read-only account info + note),
 * plus the mobile-only server picker and language switch that used to live on
 * the old Profile tab. Geist White, light-only v1.
 */
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { changePassword, logout } from '@/api/auth';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import i18n from '@/lib/i18n';
import { SUPPORTED_LOCALES, type AppLocale } from '@/lib/i18n';
import { getRole, useAuthStore } from '@/stores/authStore';
import { useSettingsStore, type ThemePreference } from '@/stores/settingsStore';

const LOCALE_LABELS: Record<AppLocale, string> = {
  en: 'English',
  pl: 'Polski',
  de: 'Deutsch',
  tr: 'Türkçe',
};

const THEMES: ThemePreference[] = ['system', 'light', 'dark'];

export function SettingsScreen() {
  const router = useRouter();
  const qc = useQueryClient();
  const { t, i18n: i18nInst } = useTranslation();

  const user = useAuthStore((s) => s.user);
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const setActiveLineId = useAuthStore((s) => s.setActiveLineId);
  const clear = useAuthStore((s) => s.clear);
  const role = getRole(user);

  const serverUrl = useSettingsStore((s) => s.serverUrl);
  const servers = useSettingsStore((s) => s.servers);
  const setServerUrl = useSettingsStore((s) => s.setServerUrl);
  const addServer = useSettingsStore((s) => s.addServer);
  const theme = useSettingsStore((s) => s.theme);
  const setTheme = useSettingsStore((s) => s.setTheme);
  const setLanguage = useSettingsStore((s) => s.setLanguage);

  const currentLng = (i18nInst.resolvedLanguage ?? 'en') as AppLocale;

  const [currentPw, setCurrentPw] = useState('');
  const [newPw, setNewPw] = useState('');
  const [newServer, setNewServer] = useState('');

  const passwordMutation = useMutation({
    mutationFn: () => changePassword(currentPw, newPw),
    onSuccess: () => {
      setCurrentPw('');
      setNewPw('');
      Alert.alert(t('Password updated'));
    },
    onError: (err: Error) => Alert.alert(t('Could not update password'), err.message),
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

  const onSelectLanguage = (lng: AppLocale) => {
    i18n.changeLanguage(lng);
    setLanguage(lng);
  };

  const onAddServer = () => {
    const clean = newServer.trim().replace(/\/+$/, '');
    if (!clean) return;
    addServer(clean);
    setServerUrl(clean);
    setNewServer('');
  };

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Settings')}</Text>
      </View>

      <ScrollView contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
        {/* Identity */}
        <View style={styles.identityRow}>
          <View style={styles.avatar}>
            <Mono size={20} color={colors.ink} weight="700">
              {initials}
            </Mono>
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={styles.username} numberOfLines={1}>
              {user?.name ?? user?.username ?? '—'}
            </Text>
            <Mono size={10} color={colors.faint} letterSpacing={0.6} style={{ marginTop: 2 }}>
              {(role ?? 'OPERATOR').toUpperCase()}
              {activeLine ? ` · ${activeLine.name.toUpperCase()}` : ''}
            </Mono>
          </View>
        </View>

        {/* Account information (read-only) */}
        <Label>{t('Account Information')}</Label>
        <View style={styles.box}>
          <KvRow label={t('Username')} value={user?.username ?? '—'} />
          <KvRow label={t('Role')} value={role ?? 'Operator'} last />
        </View>
        <Mono size={9.5} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 6 }}>
          {t('To change your username or role, contact an administrator.').toUpperCase()}
        </Mono>

        {/* Active line */}
        {hasMultipleLines ? (
          <>
            <Label>{t('Active line')}</Label>
            <View style={styles.box}>
              <View style={styles.rowBetween}>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={styles.rowValue} numberOfLines={1}>
                    {activeLine?.name ?? t('No line selected')}
                  </Text>
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
            </View>
          </>
        ) : null}

        {/* Server */}
        <Label>{t('Server')}</Label>
        <View style={styles.box}>
          {servers.map((s, i) => {
            const active = s.url === serverUrl;
            return (
              <Pressable
                key={s.url}
                onPress={() => setServerUrl(s.url)}
                style={[styles.selectRow, i === servers.length - 1 && styles.lastRow]}>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={styles.rowValue} numberOfLines={1}>
                    {s.label}
                  </Text>
                  <Mono size={9.5} color={colors.faint} letterSpacing={0.3} style={{ marginTop: 2 }}>
                    {s.url.replace(/^https?:\/\//, '').toUpperCase()}
                  </Mono>
                </View>
                {active ? (
                  <Mono size={9} color={colors.accent} weight="700" letterSpacing={0.6}>
                    {t('Active').toUpperCase()}
                  </Mono>
                ) : null}
              </Pressable>
            );
          })}
        </View>
        <View style={styles.addRow}>
          <View style={{ flex: 1 }}>
            <Field
              label="Add server"
              value={newServer}
              onChangeText={setNewServer}
              placeholder="https://mes.example.com"
              autoCapitalize="none"
              keyboardType="url"
              mono
            />
          </View>
        </View>
        <Button title="Add server" variant="outline" size="sm" onPress={onAddServer} disabled={!newServer.trim()} />

        {/* Language */}
        <Label>{t('Language')}</Label>
        <View style={styles.chipRow}>
          {SUPPORTED_LOCALES.map((lng) => {
            const active = lng === currentLng;
            return (
              <Pressable
                key={lng}
                onPress={() => onSelectLanguage(lng)}
                style={[styles.chip, active && styles.chipActive]}>
                <Mono size={11} weight="600" letterSpacing={0.3} color={active ? '#FFFFFF' : colors.muted}>
                  {LOCALE_LABELS[lng]}
                </Mono>
              </Pressable>
            );
          })}
        </View>

        {/* Appearance */}
        <Label>{t('Appearance')}</Label>
        <View style={styles.chipRow}>
          {THEMES.map((opt) => {
            const active = theme === opt;
            return (
              <Pressable
                key={opt}
                onPress={() => setTheme(opt)}
                style={[styles.chip, styles.chipFlex, active && styles.chipActive]}>
                <Mono size={11} weight="600" letterSpacing={0.5} color={active ? '#FFFFFF' : colors.muted}>
                  {opt.toUpperCase()}
                </Mono>
              </Pressable>
            );
          })}
        </View>

        {/* Change password */}
        <Label>{t('Change password')}</Label>
        <View style={{ gap: 12 }}>
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
        </View>

        <View style={{ height: 8 }} />
        <Button
          title="Log out"
          variant="danger"
          onPress={() => logoutMutation.mutate()}
          loading={logoutMutation.isPending}
        />
      </ScrollView>
    </View>
  );
}

function Label({ children }: { children: React.ReactNode }) {
  return (
    <Mono size={9} color={colors.faint} letterSpacing={0.6} style={styles.sectionLabel}>
      {String(children).toUpperCase()}
    </Mono>
  );
}

function KvRow({ label, value, last }: { label: string; value: string; last?: boolean }) {
  return (
    <View style={[styles.kvRow, last && styles.lastRow]}>
      <Mono size={11} color={colors.faint} letterSpacing={0.3}>
        {label.toUpperCase()}
      </Mono>
      <Text style={styles.kvValue} numberOfLines={1}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  body: { padding: 18, paddingBottom: 40, gap: 8, maxWidth: 640, width: '100%', alignSelf: 'center' },

  identityRow: { flexDirection: 'row', alignItems: 'center', gap: 14, paddingBottom: 8 },
  avatar: {
    width: 56,
    height: 56,
    borderRadius: radius.md,
    backgroundColor: colors.chip,
    alignItems: 'center',
    justifyContent: 'center',
  },
  username: { fontSize: 17, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.2 },

  sectionLabel: { marginTop: 14, marginBottom: 6 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, overflow: 'hidden' },

  kvRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line2,
  },
  kvValue: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink, flexShrink: 1, textAlign: 'right' },

  rowBetween: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 14 },
  rowValue: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },

  selectRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line2,
  },
  lastRow: { borderBottomWidth: 0 },
  addRow: { marginTop: 10 },

  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  chipFlex: { flex: 1, alignItems: 'center' },
  chipActive: { backgroundColor: colors.ink, borderColor: colors.ink },
});
