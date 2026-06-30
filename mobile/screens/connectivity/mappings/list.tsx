import { FontAwesome } from '@expo/vector-icons';
import { LegendList } from '@legendapp/list';
import { useRouter } from 'expo-router';
import { Alert, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeleteMapping, useMappings } from '@/hooks/queries/useConnectivity';
import type { TopicMapping } from '@/api/connectivity';

/**
 * Topic mappings list — admin-only. Tap a row to edit, swipe-equivalent
 * (long-press) to delete. "New mapping" button opens the create form; the
 * picker inside that form lets the admin choose which topic the mapping
 * attaches to.
 */
export function MappingsListScreen() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const q = useMappings({ include_inactive: true });
  const del = useDeleteMapping();

  const onDelete = (m: TopicMapping) => {
    Alert.alert(
      t('Delete mapping'),
      m.description || `#${m.id}`,
      [
        { text: t('Cancel'), style: 'cancel' },
        {
          text: t('Delete'),
          style: 'destructive',
          onPress: () =>
            del.mutate(m.id, {
              onError: (e: Error) => Alert.alert('Could not delete', e.message),
            }),
        },
      ],
    );
  };

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title={t('Topic mappings')} subtitle="ADMIN · CONNECTIVITY" />
      {q.isLoading ? (
        <LoadingState />
      ) : q.isError ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <LegendList
          data={q.data ?? []}
          keyExtractor={(m) => String(m.id)}
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} />}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          ListEmptyComponent={
            <EmptyState
              title={t('No mappings yet')}
              subtitle={t('Add one to forward a topic payload into the backend.')}
            />
          }
          ListFooterComponent={
            <Pressable
              onPress={() => router.push('/connectivity/mappings/new' as never)}
              style={({ pressed }) => [
                styles.addBtn,
                { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
              ]}>
              <FontAwesome name="plus" size={12} color={palette.text} />
              <Mono size={11} weight="700" letterSpacing={0.5} color={palette.text}>
                {t('NEW MAPPING')}
              </Mono>
            </Pressable>
          }
          renderItem={({ item: m }) => (
            <Pressable
              onPress={() => router.push(`/connectivity/mappings/${m.id}/edit` as never)}
              onLongPress={() => onDelete(m)}
              style={({ pressed }) => [
                styles.row,
                {
                  backgroundColor: palette.surface,
                  borderColor: palette.border,
                  opacity: pressed ? 0.85 : 1,
                  borderLeftColor: m.is_active ? palette.success : palette.textFaint,
                },
              ]}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text style={[styles.desc, { color: palette.text }]} numberOfLines={1}>
                  {m.description || `#${m.id}`}
                </Text>
                <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
                  {m.action_type.toUpperCase()} · PRIORITY {m.priority}
                </Mono>
                {m.field_path ? (
                  <Mono size={10.5} color={palette.textMuted} style={{ marginTop: 2 }}>
                    {m.field_path}
                  </Mono>
                ) : null}
              </View>
              <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
            </Pressable>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  list: { padding: 18, paddingBottom: 32 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderLeftWidth: 3,
  },
  desc: { fontSize: 14, fontWeight: '600' },
  addBtn: {
    marginTop: 14,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
});
