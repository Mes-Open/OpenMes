/**
 * Login — a centered card on the warm canvas, mirroring the web AuthLayout +
 * auth/Login (logo + caption above a hairline white card with the form, footer
 * below). Constrained to ~440px so the inputs read as a card, not full-width
 * fields, on tablet/web. Keeps the mobile-only server picker + language switch.
 */
import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { health, login, me } from '@/api/auth';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { BrandLogo } from '@/components/ui/Brand';
import { Mono } from '@/components/ui/Mono';
import i18n, { SUPPORTED_LOCALES, type AppLocale } from '@/lib/i18n';
import { useAuthStore } from '@/stores/authStore';
import { useSettingsStore } from '@/stores/settingsStore';

export function LoginScreen() {
  const insets = useSafeAreaInsets();

  const serverUrl = useSettingsStore((s) => s.serverUrl);
  const servers = useSettingsStore((s) => s.servers);
  const setServerUrl = useSettingsStore((s) => s.setServerUrl);
  const addServer = useSettingsStore((s) => s.addServer);
  const removeServer = useSettingsStore((s) => s.removeServer);
  const renameServer = useSettingsStore((s) => s.renameServer);
  const setLanguage = useSettingsStore((s) => s.setLanguage);
  const setSession = useAuthStore((s) => s.setSession);
  const setUser = useAuthStore((s) => s.setUser);

  const { t, i18n: i18nInst } = useTranslation();
  const currentLng = (i18nInst.resolvedLanguage ?? 'en') as AppLocale;

  const onSelectLanguage = (lng: AppLocale) => {
    i18n.changeLanguage(lng);
    setLanguage(lng);
  };

  const [server, setServer] = useState(serverUrl);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [adding, setAdding] = useState(false);
  const [newUrl, setNewUrl] = useState('');
  const [newLabel, setNewLabel] = useState('');
  const [showServers, setShowServers] = useState(false);

  const mutation = useMutation({
    mutationFn: async () => {
      const url = server.trim().replace(/\/+$/, '');
      await health(url);
      setServerUrl(url);
      const result = await login(username.trim(), password, url);
      setSession({ token: result.token, user: result.user });
      try {
        const fullUser = await me();
        setUser(fullUser);
      } catch {
        // Non-fatal
      }
    },
  });

  const onSubmit = () => {
    if (!username || !password || !server) return;
    mutation.mutate();
  };

  const onAdd = () => {
    const cleanUrl = newUrl.trim().replace(/\/+$/, '');
    if (!cleanUrl) return;
    addServer(cleanUrl, newLabel.trim() || undefined);
    setServer(cleanUrl);
    setNewUrl('');
    setNewLabel('');
    setAdding(false);
  };

  const onLongPress = (url: string, currentLabel: string) => {
    Alert.alert(currentLabel, url, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Rename'),
        onPress: () => {
          Alert.prompt?.(
            t('Rename server'),
            url,
            (text) => {
              if (text != null) renameServer(url, text);
            },
            'plain-text',
            currentLabel,
          );
        },
      },
      {
        text: t('Remove'),
        style: 'destructive',
        onPress: () => {
          removeServer(url);
          if (server === url) {
            const next = servers.find((s) => s.url !== url)?.url ?? '';
            setServer(next);
          }
        },
      },
    ]);
  };

  const activeServer = servers.find((s) => s.url === server);

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.bg }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        contentContainerStyle={[styles.scroll, { paddingTop: insets.top + 32, paddingBottom: insets.bottom + 32 }]}
        keyboardShouldPersistTaps="handled">
        <View style={styles.col}>
          {/* Logo + caption */}
          <View style={styles.brand}>
            <BrandLogo size={26} />
            <Mono size={10} color={colors.faint} letterSpacing={1.2} style={{ marginTop: 10 }}>
              {t('Manufacturing Execution System').toUpperCase()}
            </Mono>
          </View>

          {/* Auth card */}
          <View style={styles.card}>
            <Text style={styles.cardTitle}>{t('Sign in')}</Text>

            <Field
              label={t('Username')}
              value={username}
              onChangeText={setUsername}
              autoCapitalize="none"
              autoCorrect={false}
              placeholder="admin"
            />
            <Field
              label={t('Password')}
              value={password}
              onChangeText={setPassword}
              secureTextEntry
              placeholder="••••••••"
            />

            {mutation.isError ? (
              <View style={styles.errorBox}>
                <FontAwesome name="exclamation-triangle" size={13} color={colors.blocked} />
                <Text style={styles.errorText}>{(mutation.error as Error)?.message ?? t('Login failed')}</Text>
              </View>
            ) : null}

            <Button
              title={t('Sign in')}
              variant="accent"
              onPress={onSubmit}
              loading={mutation.isPending}
              disabled={!username || !password || !server}
            />
          </View>

          {/* Language */}
          <View style={styles.langRow}>
            {SUPPORTED_LOCALES.map((lng) => {
              const active = currentLng === lng;
              return (
                <Pressable
                  key={lng}
                  onPress={() => onSelectLanguage(lng)}
                  style={[styles.langChip, active ? styles.langChipActive : null]}>
                  <Mono size={10} color={active ? '#FFFFFF' : colors.muted} weight="700" letterSpacing={0.8}>
                    {lng.toUpperCase()}
                  </Mono>
                </Pressable>
              );
            })}
          </View>

          {/* Server picker — collapsed by default */}
          <View style={styles.serverBlock}>
            <Pressable onPress={() => setShowServers((v) => !v)} style={styles.serverHeader}>
              <View style={{ flex: 1 }}>
                <Mono size={9.5} color={colors.faint} letterSpacing={0.8}>{t('SERVER')}</Mono>
                <Text style={styles.serverActiveLabel} numberOfLines={1}>{activeServer?.label ?? '—'}</Text>
                <Mono size={11} color={colors.faint} style={{ marginTop: 2 }}>
                  {(server ?? '').replace(/^https?:\/\//, '') || t('no server selected')}
                </Mono>
              </View>
              <FontAwesome name={showServers ? 'chevron-up' : 'chevron-down'} size={12} color={colors.muted} />
            </Pressable>

            {showServers ? (
              <View style={styles.serverList}>
                {servers.map((s) => {
                  const active = s.url === server;
                  return (
                    <Pressable
                      key={s.url}
                      onPress={() => setServer(s.url)}
                      onLongPress={() => onLongPress(s.url, s.label)}
                      style={[styles.serverRow, active ? styles.serverRowActive : null]}>
                      <View style={{ flex: 1 }}>
                        <Text style={[styles.serverLabel, { color: active ? colors.accent : colors.ink }]} numberOfLines={1}>
                          {s.label}
                        </Text>
                        <Mono size={11} color={active ? colors.accent : colors.faint}>
                          {s.url.replace(/^https?:\/\//, '')}
                        </Mono>
                      </View>
                      {active ? <FontAwesome name="check" size={12} color={colors.accent} /> : null}
                    </Pressable>
                  );
                })}
                <Pressable
                  onPress={() => setAdding((v) => !v)}
                  style={[styles.serverRow, { borderStyle: 'dashed', justifyContent: 'center' }]}>
                  <Text style={[styles.serverLabel, { color: colors.muted }]}>{adding ? t('Cancel') : '+ ' + t('Add server')}</Text>
                </Pressable>

                {adding ? (
                  <View style={styles.addBlock}>
                    <Field label="Label" value={newLabel} onChangeText={setNewLabel} autoCapitalize="words" autoCorrect={false} placeholder="e.g. Factory A, Staging" />
                    <Field label="Server URL" value={newUrl} onChangeText={setNewUrl} autoCapitalize="none" autoCorrect={false} keyboardType="url" placeholder="https://your-instance.example.com" />
                    <Button title="Add server" onPress={onAdd} disabled={!newUrl.trim()} variant="outline" />
                  </View>
                ) : null}

                <Mono size={9.5} color={colors.faint} style={{ marginTop: 4 }}>
                  {t('LONG-PRESS A SERVER TO RENAME OR REMOVE')}
                </Mono>
              </View>
            ) : null}
          </View>

          <Mono size={10} color={colors.faint} letterSpacing={0.6} style={{ textAlign: 'center' }}>
            {`© ${new Date().getFullYear()} · v1.0 · MOBILE`}
          </Mono>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  scroll: { flexGrow: 1, justifyContent: 'center', padding: 22 },
  col: { width: '100%', maxWidth: 440, alignSelf: 'center', gap: 18 },
  brand: { alignItems: 'center' },
  card: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 24,
    gap: 14,
    shadowColor: '#000000',
    shadowOpacity: 0.1,
    shadowRadius: 28,
    shadowOffset: { width: 0, height: 16 },
    elevation: 6,
  },
  cardTitle: {
    fontSize: 19,
    fontFamily: fonts.sans.native.semibold,
    color: colors.ink,
    letterSpacing: -0.3,
    textAlign: 'center',
    marginBottom: 4,
  },
  errorBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 10,
    borderRadius: radius.sm,
    backgroundColor: colors.blockedBg,
    borderWidth: 1,
    borderColor: `${colors.blocked}40`,
  },
  errorText: { fontSize: 12.5, fontFamily: fonts.mono.native.medium, letterSpacing: 0.3, color: colors.blocked, flex: 1 },
  langRow: { flexDirection: 'row', justifyContent: 'center', gap: 8 },
  langChip: {
    paddingVertical: 5,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  langChipActive: { backgroundColor: colors.ink, borderColor: colors.ink },
  serverBlock: {
    backgroundColor: colors.card,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    overflow: 'hidden',
  },
  serverHeader: { flexDirection: 'row', alignItems: 'center', padding: 14, gap: 10 },
  serverActiveLabel: { fontSize: 14, fontFamily: fonts.sans.native.semibold, color: colors.ink, marginTop: 4 },
  serverList: { padding: 14, paddingTop: 0, gap: 8, borderTopWidth: 1, borderTopColor: colors.line },
  serverRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.bg,
  },
  serverRowActive: { backgroundColor: colors.card, borderColor: colors.accent },
  serverLabel: { fontSize: 14, fontFamily: fonts.sans.native.semibold },
  addBlock: { gap: 10, marginTop: 6 },
});
