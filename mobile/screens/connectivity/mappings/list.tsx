/**
 * Topic mappings list — admin-only. Tap a row to edit, long-press to delete.
 * "New mapping" opens the create form; the picker inside that form lets the
 * admin choose which topic the mapping attaches to.
 */
import { useRouter } from 'expo-router';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteMapping, useMappings } from '@/hooks/queries/useConnectivity';
import type { TopicMapping } from '@/api/connectivity';

const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function MappingsListScreen() {
  const router = useRouter();
  const { t } = useTranslation();

  const q = useMappings({ include_inactive: true });
  const del = useDeleteMapping();
  const rows = q.data ?? [];

  const onDelete = (m: TopicMapping) => {
    Alert.alert(t('Delete mapping'), m.description || `#${m.id}`, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          del.mutate(m.id, {
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          }),
      },
    ]);
  };

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Topic mappings')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('New mapping')} size="sm" onPress={() => router.push('/connectivity/mappings/new' as never)} />
      </View>

      {q.isLoading && !q.data ? (
        <LoadingState />
      ) : q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
          <View style={[styles.row, styles.headerRow]}>
            <HCell flex={1.5}>{t('Mapping')}</HCell>
            <HCell w={96}>{t('Action')}</HCell>
            <HCell w={48}>{t('Prio')}</HCell>
            <HCell w={56}>{t('Active')}</HCell>
          </View>
          {rows.map((m) => (
            <MappingRow
              key={m.id}
              mapping={m}
              onPress={() => router.push(`/connectivity/mappings/${m.id}/edit` as never)}
              onLongPress={() => onDelete(m)}
            />
          ))}
          {rows.length === 0 ? <Text style={styles.empty}>{t('No mappings yet.')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function MappingRow({
  mapping,
  onPress,
  onLongPress,
}: {
  mapping: TopicMapping;
  onPress: () => void;
  onLongPress: () => void;
}) {
  const { t } = useTranslation();
  return (
    <Pressable onPress={onPress} onLongPress={onLongPress} style={({ pressed }) => [styles.row, styles.dataRow, { opacity: pressed ? 0.6 : 1 }]}>
      <View style={{ flex: 1.5, minWidth: 0 }}>
        <Text numberOfLines={1} style={styles.title}>{mapping.description || `#${mapping.id}`}</Text>
        {mapping.field_path ? (
          <Mono size={10} color={colors.faint} numberOfLines={1} style={{ marginTop: 2 }}>{mapping.field_path}</Mono>
        ) : null}
      </View>
      <View style={{ width: 96 }}>
        <Mono size={10} color={colors.muted} numberOfLines={1}>{humanize(mapping.action_type)}</Mono>
      </View>
      <View style={{ width: 48 }}>
        <Mono size={12} color={colors.muted}>{String(mapping.priority)}</Mono>
      </View>
      <View style={{ width: 56 }}>
        {mapping.is_active ? (
          <Text style={styles.activeBadge}>{t('Active')}</Text>
        ) : (
          <Text style={styles.dash}>—</Text>
        )}
      </View>
    </Pressable>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{String(children).toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 11, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  activeBadge: { fontSize: 11, fontFamily: fonts.sans.native.medium, color: colors.running },
  dash: { fontSize: 13, color: colors.faint },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
