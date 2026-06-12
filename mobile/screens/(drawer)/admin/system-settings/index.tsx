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

import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useSettings, useUpdateSetting } from '@/hooks/queries/useSystem';

interface Setting {
  key: string;
  value: unknown;
  description?: string | null;
}

export function SystemSettingsList() {
  const scheme = useColorScheme();
  const palette = Colors[scheme];

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

  if (query.isLoading) return <LoadingState />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

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
        onError: (e: Error) => Alert.alert('Could not update', e.message),
      },
    );
  };

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title="System settings"
        subtitle={`PLANT POLICIES · ${policies.length + values.length} KEYS`}
      />
      <ScrollView
        style={{ flex: 1, backgroundColor: palette.background }}
        contentContainerStyle={styles.scroll}
        refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} />}>
      {policies.length > 0 ? (
        <View>
          <SectionLabel>Production policies</SectionLabel>
          <Card style={{ padding: 0, overflow: 'hidden' }}>
            {policies.map((s, i) => (
              <View key={s.key}>
                <View style={styles.policyRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.policyTitle, { color: palette.text }]}>
                      {humanize(s.key)}
                    </Text>
                    {s.description ? (
                      <Text style={[styles.policySub, { color: palette.textMuted }]}>
                        {s.description}
                      </Text>
                    ) : (
                      <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4}>
                        {s.key}
                      </Mono>
                    )}
                  </View>
                  <Switch
                    value={!!s.value}
                    onValueChange={(v) =>
                      updateMutation.mutate(
                        { key: s.key, value: v },
                        { onError: (e: Error) => Alert.alert('Failed', e.message) },
                      )
                    }
                  />
                </View>
                {i < policies.length - 1 ? (
                  <View style={[styles.divider, { backgroundColor: palette.border }]} />
                ) : null}
              </View>
            ))}
          </Card>
        </View>
      ) : null}

      {values.length > 0 ? (
        <View>
          <SectionLabel>Values</SectionLabel>
          <Card style={{ padding: 0, overflow: 'hidden' }}>
            {values.map((s, i) => {
              const isEditing = editing === s.key;
              const display =
                typeof s.value === 'string' ? s.value : JSON.stringify(s.value);
              return (
                <View key={s.key}>
                  <View style={styles.valueRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.policyTitle, { color: palette.text }]}>
                        {humanize(s.key)}
                      </Text>
                      <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4}>
                        {s.key}
                      </Mono>
                    </View>
                    {isEditing ? null : (
                      <Pressable
                        onPress={() => {
                          setDraft(display);
                          setEditing(s.key);
                        }}>
                        <Mono
                          size={12.5}
                          color={palette.textMuted}
                          weight="600">
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
                        style={[
                          styles.input,
                          {
                            color: palette.text,
                            borderColor: palette.border,
                            backgroundColor: palette.surfaceAlt,
                            fontFamily: MONO,
                          },
                        ]}
                        multiline
                        autoCapitalize="none"
                        autoCorrect={false}
                      />
                      <View style={{ flexDirection: 'row', gap: 8 }}>
                        <Pressable
                          onPress={() => setEditing(null)}
                          style={[styles.btn, { backgroundColor: palette.surfaceAlt }]}>
                          <Mono size={12} color={palette.text} weight="700" letterSpacing={0.5}>
                            CANCEL
                          </Mono>
                        </Pressable>
                        <Pressable
                          onPress={() => onCommit(s.key)}
                          style={[styles.btn, { backgroundColor: BRAND.amber }]}>
                          <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.5}>
                            SAVE
                          </Mono>
                        </Pressable>
                      </View>
                    </View>
                  ) : null}
                  {i < values.length - 1 ? (
                    <View style={[styles.divider, { backgroundColor: palette.border }]} />
                  ) : null}
                </View>
              );
            })}
          </Card>
        </View>
      ) : null}

      <Mono
        size={10.5}
        color={palette.textFaint}
        letterSpacing={0.4}
        style={{ textAlign: 'center', paddingVertical: 8 }}>
        OPENMES · MOBILE v1.0
      </Mono>
      </ScrollView>
    </View>
  );
}

function humanize(key: string) {
  return key
    .replace(/[_\-.]+/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

const styles = StyleSheet.create({
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  policyRow: {
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  policyTitle: { fontSize: 13, fontWeight: '600' },
  policySub: { fontSize: 12, marginTop: 2, lineHeight: 16 },
  valueRow: {
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  divider: { height: StyleSheet.hairlineWidth, marginHorizontal: 14 },
  editor: { padding: 14, paddingTop: 0, gap: 10 },
  input: {
    borderWidth: 1,
    borderRadius: 10,
    padding: 12,
    minHeight: 60,
    textAlignVertical: 'top',
    fontSize: 13,
  },
  btn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    alignItems: 'center',
  },
});
