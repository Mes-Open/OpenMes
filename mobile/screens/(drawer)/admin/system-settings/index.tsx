/**
 * System settings — mirrors the web admin system settings page: boolean plant
 * policies (toggles) and free-form values (inline JSON/string editor). Data via
 * REST useSettings / useUpdateSetting.
 */
import { useMemo, useState } from 'react';
import {
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useSettings, useUpdateSetting } from '@/hooks/queries/useSystem';

interface Setting {
  key: string;
  value: unknown;
  description?: string | null;
}

export function SystemSettingsList() {
  const { t } = useTranslation();
  const query = useSettings();
  const updateMutation = useUpdateSetting();
  const [editing, setEditing] = useState<string | null>(null);
  const [draft, setDraft] = useState<string>('');

  const { policies, values } = useMemo(() => {
    const all: Setting[] = (query.data ?? []) as Setting[];
    return {
      policies: all.filter((s) => typeof s.value === 'boolean'),
      values: all.filter((s) => typeof s.value !== 'boolean'),
    };
  }, [query.data]);

  if (query.isLoading && !query.data) return <LoadingState />;
  if (query.isError && !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const onCommit = (key: string) => {
    let value: unknown;
    try {
      value = JSON.parse(draft);
    } catch {
      // Fall back to the raw string for plain string settings.
      value = draft;
    }
    updateMutation.mutate(
      { key, value },
      {
        onSuccess: () => setEditing(null),
        onError: (e: Error) => Alert.alert(t('Could not update'), e.message),
      },
    );
  };

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('System settings')}</Text>
      </View>
      <ScrollView
        style={styles.screen}
        contentContainerStyle={styles.scroll}
        refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
        {policies.length > 0 ? (
          <View style={{ gap: 8 }}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Production policies').toUpperCase()}</Mono>
            <View style={styles.box}>
              {policies.map((s, i) => (
                <View key={s.key} style={[styles.policyRow, i < policies.length - 1 ? styles.rowBorder : null]}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.title}>{humanize(s.key)}</Text>
                    {s.description ? (
                      <Text style={styles.sub}>{s.description}</Text>
                    ) : (
                      <Mono size={10} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 2 }}>{s.key}</Mono>
                    )}
                  </View>
                  <Switch
                    value={!!s.value}
                    onValueChange={(v) =>
                      updateMutation.mutate(
                        { key: s.key, value: v },
                        { onError: (e: Error) => Alert.alert(t('Failed'), e.message) },
                      )
                    }
                  />
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {values.length > 0 ? (
          <View style={{ gap: 8 }}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Values').toUpperCase()}</Mono>
            <View style={styles.box}>
              {values.map((s, i) => {
                const isEditing = editing === s.key;
                const display = typeof s.value === 'string' ? s.value : JSON.stringify(s.value);
                return (
                  <View key={s.key} style={i < values.length - 1 ? styles.rowBorder : null}>
                    <View style={styles.policyRow}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.title}>{humanize(s.key)}</Text>
                        <Mono size={10} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 2 }}>{s.key}</Mono>
                      </View>
                      {isEditing ? null : (
                        <Pressable
                          onPress={() => {
                            setDraft(display);
                            setEditing(s.key);
                          }}>
                          <Mono size={12.5} color={colors.muted} weight="600">
                            {display.length > 32 ? display.slice(0, 32) + '…' : display}
                          </Mono>
                        </Pressable>
                      )}
                    </View>
                    {isEditing ? (
                      <View style={styles.editor}>
                        <TextInput
                          value={draft}
                          onChangeText={setDraft}
                          style={styles.input}
                          multiline
                          autoCapitalize="none"
                          autoCorrect={false}
                        />
                        <View style={{ flexDirection: 'row', gap: 8 }}>
                          <Pressable onPress={() => setEditing(null)} style={[styles.btn, styles.btnGhost]}>
                            <Mono size={11} color={colors.muted} weight="700" letterSpacing={0.5}>{t('Cancel').toUpperCase()}</Mono>
                          </Pressable>
                          <Pressable onPress={() => onCommit(s.key)} style={[styles.btn, styles.btnPrimary]}>
                            <Mono size={11} color="#FFFFFF" weight="700" letterSpacing={0.5}>{t('Save').toUpperCase()}</Mono>
                          </Pressable>
                        </View>
                      </View>
                    ) : null}
                  </View>
                );
              })}
            </View>
          </View>
        ) : null}
      </ScrollView>
    </View>
  );
}

function humanize(key: string) {
  return key.replace(/[_\-.]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  scroll: { padding: 16, gap: 18, paddingBottom: 32 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, overflow: 'hidden' },
  policyRow: { padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12 },
  rowBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  title: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  sub: { fontSize: 12, color: colors.muted, marginTop: 2, lineHeight: 16, fontFamily: fonts.sans.native.regular },
  editor: { padding: 14, paddingTop: 0, gap: 10 },
  input: {
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.panel,
    padding: 12,
    minHeight: 60,
    textAlignVertical: 'top',
    fontSize: 13,
    color: colors.ink,
    fontFamily: fonts.mono.native.regular,
  },
  btn: { flex: 1, paddingVertical: 12, borderRadius: radius.md, alignItems: 'center' },
  btnGhost: { backgroundColor: colors.chip },
  btnPrimary: { backgroundColor: colors.accent },
});
