import { FontAwesome } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeleteLineStatus, useLineStatuses } from '@/hooks/queries/useOrgStructure';

export function LineStatusesList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const lineId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = useLineStatuses(lineId);
  const deleteMutation = useDeleteLineStatus();
  const items = query.data ?? [];

  const onDelete = (statusId: number, name: string) =>
    Alert.alert('Delete status', `Remove "${name}"?`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(statusId, {
            onError: (e: Error) => Alert.alert('Failed', e.message),
          }),
      },
    ]);

  return (
    <ListScreen
      title="Custom statuses"
      eyebrow={`LINE #${lineId} · ${items.length} COLUMNS`}
      newRoute={`/structure/lines/${lineId}/statuses/new`}
      items={items}
      keyExtractor={(s) => String(s.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No custom statuses"
      emptySubtitle="Add a Kanban column for this line."
      renderItem={(item) => (
        <Card>
          <View style={styles.row}>
            <View style={[styles.swatch, { backgroundColor: item.color || '#94a3b8' }]} />
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={[styles.name, { color: palette.text }]}>{item.name}</Text>
              <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
                ORDER {item.sort_order}
                {item.is_default ? ' · DEFAULT' : ''}
                {item.is_done_status ? ' · DONE' : ''}
              </Mono>
            </View>
            <Pressable
              onPress={() => onDelete(item.id, item.name)}
              hitSlop={8}
              style={({ pressed }) => [
                styles.deleteBtn,
                {
                  borderColor: palette.border,
                  backgroundColor: palette.dangerSoft,
                  opacity: pressed ? 0.7 : 1,
                },
              ]}>
              <FontAwesome name="trash" size={13} color={palette.danger} />
            </Pressable>
          </View>
        </Card>
      )}
    />
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  swatch: { width: 22, height: 22, borderRadius: 6 },
  name: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  deleteBtn: {
    width: 36,
    height: 36,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
