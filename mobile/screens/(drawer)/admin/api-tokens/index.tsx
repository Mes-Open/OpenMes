import { FontAwesome } from '@expo/vector-icons';
import { useState } from 'react';
import { Alert, Pressable, ScrollView, Share, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface MockToken {
  id: number;
  name: string;
  scope: string;
  createdAt: string;
  lastUsed: string;
  stale?: boolean;
}

// TODO(api/admin-tokens): no /api/v1/admin/api-tokens endpoint exists yet.
// Starts empty; flip to a real query once the endpoint ships.

export function ApiTokensScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const [tokens, setTokens] = useState<MockToken[]>([]);
  const [revealed, setRevealed] = useState<{ token: string; name: string } | null>(null);

  const onCreate = () => {
    // Generate a fake token. Real backend would return a JWT/PAT — show once.
    const token =
      Array.from({ length: 40 }, () => Math.floor(Math.random() * 16).toString(16)).join('');
    const next: MockToken = {
      id: Date.now(),
      name: `New-Token-${tokens.length + 1}`,
      scope: 'auth',
      createdAt: new Date().toISOString().slice(0, 10),
      lastUsed: 'never',
    };
    setTokens((prev) => [next, ...prev]);
    setRevealed({ token, name: next.name });
  };

  const onRevoke = (t: MockToken) => {
    Alert.alert('Revoke token', `Permanently revoke "${t.name}"?`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Revoke',
        style: 'destructive',
        onPress: () => setTokens((prev) => prev.filter((x) => x.id !== t.id)),
      },
    ]);
  };

  const onCopy = async () => {
    if (!revealed) return;
    // No clipboard module installed yet — fall back to the OS share sheet,
    // which lets the user paste into Notes / a password manager.
    // TODO(deps): add expo-clipboard for direct copy.
    await Share.share({ message: revealed.token });
  };

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title="API tokens" subtitle={`ERP & INTEGRATIONS · ${tokens.length} ACTIVE`} />
      <ScrollView contentContainerStyle={styles.scroll}>
        {revealed ? (
          <View style={styles.notice}>
            <FontAwesome name="shield" size={16} color="#a8650a" style={{ marginTop: 2 }} />
            <View style={{ flex: 1, gap: 6 }}>
              <Text style={styles.noticeTitle}>{`New token created — ${revealed.name}`}</Text>
              <Pressable onPress={onCopy}>
                <View style={styles.tokenBlock}>
                  <Text style={[styles.tokenText, { fontFamily: MONO }]} numberOfLines={2}>
                    {revealed.token}
                  </Text>
                </View>
              </Pressable>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                <Mono size={10} color="#a8650a" letterSpacing={0.5}>TAP TO COPY · WON'T BE SHOWN AGAIN</Mono>
                <Pressable onPress={() => setRevealed(null)} hitSlop={6}>
                  <Mono size={10} color="#a8650a" weight="700" letterSpacing={0.5}>DISMISS</Mono>
                </Pressable>
              </View>
            </View>
          </View>
        ) : null}

        <SectionLabel
          right={
            <Pressable onPress={onCreate} hitSlop={6}>
              <View style={[styles.addBtn, { backgroundColor: BRAND.amber }]}>
                <FontAwesome name="plus" size={12} color="#1a1208" />
                <Mono size={10.5} color="#1a1208" weight="700" letterSpacing={0.5}>NEW</Mono>
              </View>
            </Pressable>
          }>
          Active tokens
        </SectionLabel>

        <Card style={{ padding: 0, overflow: 'hidden' }}>
          {tokens.map((t, i) => (
            <View key={t.id}>
              <View style={styles.row}>
                <View
                  style={[
                    styles.iconWrap,
                    { backgroundColor: t.stale ? palette.surfaceAlt : '#0e0e10' },
                  ]}>
                  <FontAwesome name="key" size={16} color={t.stale ? palette.textFaint : BRAND.amber} />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                    <Text style={[styles.name, { color: palette.text, fontFamily: MONO }]} numberOfLines={1}>
                      {t.name}
                    </Text>
                    {t.stale ? (
                      <View style={styles.stalePill}>
                        <Mono size={9} color="#a8650a" weight="700" letterSpacing={0.5}>STALE</Mono>
                      </View>
                    ) : null}
                  </View>
                  <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 3 }}>
                    {t.scope}
                  </Mono>
                  <Mono size={10} color={palette.textMuted} letterSpacing={0.4} style={{ marginTop: 3 }}>
                    LAST USED {t.lastUsed.toUpperCase()} · CREATED {t.createdAt}
                  </Mono>
                </View>
                <Pressable
                  onPress={() => onRevoke(t)}
                  hitSlop={6}
                  style={({ pressed }) => [
                    styles.revoke,
                    { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
                  ]}>
                  <Mono size={11} color={palette.danger} weight="600" letterSpacing={0.5}>REVOKE</Mono>
                </Pressable>
              </View>
              {i < tokens.length - 1 ? (
                <View style={[styles.divider, { backgroundColor: palette.border }]} />
              ) : null}
            </View>
          ))}
        </Card>

        {tokens.length === 0 ? (
          <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', marginTop: 18 }}>
            NO TOKENS — TAP NEW TO CREATE ONE
          </Mono>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  notice: {
    padding: 14,
    borderRadius: 12,
    backgroundColor: '#fff8e8',
    borderWidth: 1,
    borderColor: BRAND.amber,
    flexDirection: 'row',
    gap: 10,
  },
  noticeTitle: { fontSize: 13, fontWeight: '700', color: '#a8650a' },
  tokenBlock: {
    backgroundColor: 'rgba(255,255,255,0.6)',
    borderRadius: 6,
    paddingVertical: 6,
    paddingHorizontal: 8,
  },
  tokenText: { color: '#7a4906', fontSize: 11 },
  addBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 8,
  },
  row: {
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  iconWrap: { width: 40, height: 40, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  name: { fontSize: 13, fontWeight: '600' },
  stalePill: {
    paddingVertical: 2,
    paddingHorizontal: 5,
    borderRadius: 3,
    backgroundColor: '#fbe9c8',
  },
  revoke: {
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 6,
    borderWidth: 1,
  },
  divider: { height: StyleSheet.hairlineWidth, marginHorizontal: 14 },
});
