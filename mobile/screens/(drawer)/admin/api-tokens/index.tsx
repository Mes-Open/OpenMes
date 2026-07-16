/**
 * API Tokens — mirrors the web settings/ApiTokens page: a one-time reveal of a
 * freshly-created token, a "Generate New Token" form (name + button) and the
 * active-tokens list with per-row Revoke. No REST endpoint ships yet, so the
 * list is backed by local state; swap in a real query once the API lands.
 */
import { useState } from 'react';
import { Alert, Pressable, ScrollView, Share, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';

interface MockToken {
  id: number;
  name: string;
  createdAt: string;
  lastUsed: string;
}

// TODO(api/admin-tokens): no /api/v1/admin/api-tokens endpoint exists yet.
// Starts empty; flip to a real query once the endpoint ships.

export function ApiTokensScreen() {
  const { t } = useTranslation();
  const [name, setName] = useState('');
  const [tokens, setTokens] = useState<MockToken[]>([]);
  const [revealed, setRevealed] = useState<{ token: string; name: string } | null>(null);

  const onCreate = () => {
    const label = name.trim() || `Token-${tokens.length + 1}`;
    const token =
      Array.from({ length: 40 }, () => Math.floor(Math.random() * 16).toString(16)).join('');
    const next: MockToken = {
      id: Date.now(),
      name: label,
      createdAt: new Date().toISOString().slice(0, 10),
      lastUsed: 'never',
    };
    setTokens((prev) => [next, ...prev]);
    setRevealed({ token, name: label });
    setName('');
  };

  const onRevoke = (tok: MockToken) => {
    Alert.alert(t('Revoke token'), t("Revoke token ':name'? This cannot be undone.", { name: tok.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Revoke'),
        style: 'destructive',
        onPress: () => setTokens((prev) => prev.filter((x) => x.id !== tok.id)),
      },
    ]);
  };

  const onCopy = async () => {
    if (!revealed) return;
    // No clipboard module installed yet — fall back to the OS share sheet.
    // TODO(deps): add expo-clipboard for direct copy.
    await Share.share({ message: revealed.token });
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('API Tokens')}</Text>
      <Text style={styles.subtitle}>{t('Manage personal access tokens for external integrations')}</Text>

      {revealed ? (
        <View style={styles.reveal}>
          <Text style={styles.revealTitle}>{t('Token created: :name', { name: revealed.name })}</Text>
          <Text style={styles.revealHint}>{t('Copy this token now — it will not be shown again.')}</Text>
          <Pressable onPress={onCopy} style={styles.tokenBlock}>
            <Mono size={12} color={colors.ink}>{revealed.token}</Mono>
          </Pressable>
          <View style={styles.revealActions}>
            <Button title={t('Copy')} size="sm" variant="secondary" onPress={onCopy} />
            <View style={{ flex: 1 }} />
            <Pressable onPress={() => setRevealed(null)} hitSlop={6}>
              <Mono size={10} color={colors.muted} letterSpacing={0.6}>{t('Dismiss').toUpperCase()}</Mono>
            </Pressable>
          </View>
        </View>
      ) : null}

      <View style={styles.box}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Generate New Token').toUpperCase()}</Mono>
        <Field
          label="Token Name"
          value={name}
          onChangeText={setName}
          placeholder="e.g. PrestaShop Integration"
        />
        <Button title={t('Generate Token')} onPress={onCreate} disabled={!name.trim()} />
      </View>

      <View style={styles.box}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Active Tokens').toUpperCase()}</Mono>
        {tokens.length === 0 ? (
          <Text style={styles.empty}>{t('No tokens generated yet.')}</Text>
        ) : (
          tokens.map((tok, i) => (
            <View key={tok.id} style={[styles.tokenRow, i > 0 && styles.tokenRowBorder]}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text numberOfLines={1} style={styles.tokenName}>{tok.name}</Text>
                <Mono size={10} color={colors.muted} letterSpacing={0.4} style={{ marginTop: 3 }}>
                  {`${t('Created')} ${tok.createdAt} · ${t('Last used')} ${tok.lastUsed.toUpperCase()}`}
                </Mono>
              </View>
              <Pressable onPress={() => onRevoke(tok)} hitSlop={6}>
                <Text style={styles.revoke}>{t('Revoke')}</Text>
              </Pressable>
            </View>
          ))
        )}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  subtitle: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: -8 },
  box: {
    gap: 10,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 14,
  },
  reveal: {
    gap: 8,
    backgroundColor: colors.downtimeBg,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 14,
  },
  revealTitle: { fontSize: 13, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  revealHint: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  tokenBlock: { backgroundColor: colors.chip, borderRadius: radius.sm, paddingVertical: 8, paddingHorizontal: 10 },
  revealActions: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  tokenRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10 },
  tokenRowBorder: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2 },
  tokenName: { fontSize: 13.5, fontFamily: fonts.sans.native.medium, color: colors.ink },
  revoke: { fontSize: 12.5, fontFamily: fonts.sans.native.medium, color: colors.blocked },
  empty: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular, paddingVertical: 4, textAlign: 'center' },
});
