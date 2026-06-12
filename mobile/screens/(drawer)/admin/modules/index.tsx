import { Alert, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';
import { FontAwesome } from '@expo/vector-icons';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { Switch } from '@/components/ui/Switch';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useModules, useToggleModule } from '@/hooks/queries/useSystem';

export function ModulesList() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = useModules();
  const toggleMutation = useToggleModule();

  const items = query.data ?? [];
  const enabledCount = items.filter((m) => m.enabled).length;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title="Modules"
        subtitle={`${items.length} INSTALLED · ${enabledCount} ENABLED`}
      />
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <LegendList
          data={query.data ?? []}
          keyExtractor={(m) => m.name}
          contentContainerStyle={styles.list}
          ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
          ListEmptyComponent={<EmptyState title="No modules" />}
          renderItem={({ item }) => (
            <Card>
              <View style={styles.row}>
                <View
                  style={[
                    styles.iconWrap,
                    { backgroundColor: item.enabled ? '#fbe9c8' : palette.surfaceAlt },
                  ]}>
                  <FontAwesome
                    name="cube"
                    size={16}
                    color={item.enabled ? BRAND.amber : palette.textFaint}
                  />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <View style={styles.titleRow}>
                    <Text style={[styles.name, { color: palette.text }]} numberOfLines={1}>
                      {item.display_name}
                    </Text>
                    {item.has_error ? (
                      <View style={[styles.errPill, { backgroundColor: palette.dangerSoft }]}>
                        <Mono size={9} color={palette.danger} letterSpacing={0.6}>ERROR</Mono>
                      </View>
                    ) : null}
                  </View>
                  <Mono size={11} color={palette.textFaint}>
                    {item.name}{item.version ? ` · v${item.version}` : ''}
                  </Mono>
                </View>
                <Switch
                  value={item.enabled}
                  onValueChange={(v) =>
                    toggleMutation.mutate(
                      { name: item.name, enabled: v },
                      { onError: (e: Error) => Alert.alert('Failed', e.message) },
                    )
                  }
                />
              </View>
              {item.description ? (
                <Text style={{ color: palette.textMuted, fontSize: 13, marginTop: 10, lineHeight: 19 }}>
                  {item.description}
                </Text>
              ) : null}
            </Card>
          )}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  list: { padding: 18 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconWrap: { width: 40, height: 40, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  name: { fontSize: 15, fontWeight: '600', letterSpacing: -0.2 },
  errPill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 4 },
});
