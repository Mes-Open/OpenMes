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

import { health, login, me } from '@/api/auth';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { BrandLogo } from '@/components/ui/Brand';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import i18n, { SUPPORTED_LOCALES, type AppLocale } from '@/lib/i18n';
import { useAuthStore } from '@/stores/authStore';
import { useSettingsStore } from '@/stores/settingsStore';

const DARK = Colors.dark;

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
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Rename',
        onPress: () => {
          Alert.prompt?.(
            'Rename server',
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
        text: 'Remove',
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
      style={{ flex: 1, backgroundColor: DARK.background }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        contentContainerStyle={[styles.container, { paddingTop: insets.top + 28, paddingBottom: insets.bottom + 28 }]}
        keyboardShouldPersistTaps="handled">
        <View style={styles.brandRow}>
          <BrandLogo size={20} color={DARK.text} />
          <View style={styles.langRow}>
            {SUPPORTED_LOCALES.map((lng) => {
              const active = currentLng === lng;
              return (
                <Pressable
                  key={lng}
                  onPress={() => onSelectLanguage(lng)}
                  style={[
                    styles.langChip,
                    {
                      backgroundColor: active ? BRAND.amber : 'transparent',
                      borderColor: active ? BRAND.amber : DARK.border,
                    },
                  ]}>
                  <Mono
                    size={10}
                    color={active ? '#1a1208' : DARK.textMuted}
                    weight="700"
                    letterSpacing={0.8}>
                    {lng.toUpperCase()}
                  </Mono>
                </Pressable>
              );
            })}
          </View>
          <Mono size={10} color={DARK.textFaint} letterSpacing={0.8}>
            v1.0 · MOBILE
          </Mono>
        </View>

        <View style={styles.hero}>
          <Mono size={11} color={DARK.textFaint} letterSpacing={0.8}>
            {(activeServer?.label ?? 'PRODUCTION FLOOR').toUpperCase()}
          </Mono>
          <Text style={styles.heroTitle}>
            {t('Sign in to')}{'\n'}{t('the floor.')}
          </Text>
          <Text style={styles.heroSub}>
            {t('Enter your credentials below. Tap your line after sign-in to start the shift.')}
          </Text>
        </View>

        <View style={styles.form}>
          <Field
            label={t('Username')}
            value={username}
            onChangeText={setUsername}
            autoCapitalize="none"
            autoCorrect={false}
            placeholder="admin"
            style={darkInput}
          />
          <Field
            label={t('Password')}
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            placeholder="••••••••"
            style={darkInput}
          />

          {mutation.isError ? (
            <View style={styles.errorBox}>
              <FontAwesome name="exclamation-triangle" size={14} color={DARK.danger} />
              <Text style={[styles.errorText, { color: DARK.danger }]}>
                {(mutation.error as Error)?.message ?? t('Login failed')}
              </Text>
            </View>
          ) : null}

          <Button
            title={t('Sign in')}
            onPress={onSubmit}
            loading={mutation.isPending}
            disabled={!username || !password || !server}
            size="lg"
            rightIcon={<FontAwesome name="arrow-right" size={16} color="#1a1208" />}
          />
        </View>

        {/* Server picker — collapsed by default */}
        <View style={styles.serverBlock}>
          <Pressable onPress={() => setShowServers((v) => !v)} style={styles.serverHeader}>
            <View style={{ flex: 1 }}>
              <Mono size={10} color={DARK.textFaint} letterSpacing={0.8}>SERVER</Mono>
              <Text style={styles.serverActiveLabel} numberOfLines={1}>
                {activeServer?.label ?? '—'}
              </Text>
              <Mono size={11} color={DARK.textFaint} style={{ marginTop: 2 }}>
                {(server ?? '').replace(/^https?:\/\//, '') || 'no server selected'}
              </Mono>
            </View>
            <FontAwesome
              name={showServers ? 'chevron-up' : 'chevron-down'}
              size={12}
              color={DARK.textMuted}
            />
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
                    style={[
                      styles.serverRow,
                      {
                        backgroundColor: active ? '#241a08' : DARK.surface,
                        borderColor: active ? BRAND.amber : DARK.border,
                      },
                    ]}>
                    <View style={{ flex: 1 }}>
                      <Text
                        style={[styles.serverLabel, { color: active ? BRAND.amber : DARK.text }]}
                        numberOfLines={1}>
                        {s.label}
                      </Text>
                      <Mono size={11} color={active ? BRAND.amber : DARK.textFaint}>
                        {s.url.replace(/^https?:\/\//, '')}
                      </Mono>
                    </View>
                    {active ? <FontAwesome name="check" size={12} color={BRAND.amber} /> : null}
                  </Pressable>
                );
              })}
              <Pressable
                onPress={() => setAdding((v) => !v)}
                style={[
                  styles.serverRow,
                  { borderStyle: 'dashed', borderColor: DARK.border, justifyContent: 'center' },
                ]}>
                <Text style={[styles.serverLabel, { color: DARK.textMuted }]}>
                  {adding ? 'Cancel' : '+ Add server'}
                </Text>
              </Pressable>

              {adding ? (
                <View style={styles.addBlock}>
                  <Field
                    label="Label"
                    value={newLabel}
                    onChangeText={setNewLabel}
                    autoCapitalize="words"
                    autoCorrect={false}
                    placeholder="e.g. Factory A, Staging"
                    style={darkInput}
                  />
                  <Field
                    label="Server URL"
                    value={newUrl}
                    onChangeText={setNewUrl}
                    autoCapitalize="none"
                    autoCorrect={false}
                    keyboardType="url"
                    placeholder="https://your-instance.example.com"
                    style={darkInput}
                  />
                  <Button title="Add server" onPress={onAdd} disabled={!newUrl.trim()} variant="outline" />
                </View>
              ) : null}

              <Mono size={10} color={DARK.textFaint} style={{ marginTop: 4 }}>
                LONG-PRESS A SERVER TO RENAME OR REMOVE
              </Mono>
            </View>
          ) : null}
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const darkInput = {
  backgroundColor: DARK.surface,
  borderColor: DARK.border,
  color: DARK.text,
};

const styles = StyleSheet.create({
  container: { flexGrow: 1, padding: 22, gap: 24 },
  brandRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  langRow: { flexDirection: 'row', gap: 6 },
  langChip: {
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 4,
    borderWidth: 1,
  },
  hero: { gap: 10 },
  heroTitle: {
    fontSize: 36,
    fontWeight: '500',
    letterSpacing: -1.2,
    lineHeight: 40,
    color: DARK.text,
  },
  heroSub: { fontSize: 14, color: DARK.textMuted, lineHeight: 20 },
  form: { gap: 14 },
  errorBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 10,
    borderRadius: 10,
    backgroundColor: '#FBEAE6',
    borderWidth: 1,
    borderColor: DARK.danger,
  },
  errorText: { fontSize: 13, fontFamily: MONO, letterSpacing: 0.4, flex: 1 },
  serverBlock: {
    backgroundColor: DARK.surface,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: DARK.border,
    overflow: 'hidden',
  },
  serverHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    gap: 10,
  },
  serverActiveLabel: { fontSize: 14, fontWeight: '700', color: DARK.text, marginTop: 4 },
  serverList: {
    padding: 14,
    paddingTop: 0,
    gap: 8,
    borderTopWidth: 1,
    borderTopColor: DARK.border,
  },
  serverRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  serverLabel: { fontSize: 14, fontWeight: '600' },
  addBlock: { gap: 10, marginTop: 6 },
});
