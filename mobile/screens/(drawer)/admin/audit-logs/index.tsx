import { format, parseISO } from 'date-fns';
import { useState } from 'react';
import { Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';
import * as WebBrowser from 'expo-web-browser';

import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAuditLogs } from '@/hooks/queries/useAuditLogs';
import { auditLogsExportUrl, type AuditAction } from '@/api/auditLogs';

const ACTIONS: { id: AuditAction | 'all'; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'created', label: 'Created' },
  { id: 'updated', label: 'Updated' },
  { id: 'deleted', label: 'Deleted' },
];

export function AuditLogsScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const [action, setAction] = useState<AuditAction | 'all'>('all');
  const [entityType, setEntityType] = useState('');

  const filters = {
    action: action === 'all' ? undefined : action,
    entity_type: entityType.trim() || undefined,
    per_page: 50,
  };
  const query = useAuditLogs(filters);

  const total = query.data?.meta?.total ?? query.data?.data.length ?? 0;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader back title="Audit logs" subtitle={`IMMUTABLE · ${total} ENTRIES`} />
      <View style={[styles.toolbar, { borderBottomColor: palette.border }]}>
        <View style={styles.chipRow}>
          {ACTIONS.map((a) => {
            const active = a.id === action;
            return (
              <Pressable
                key={a.id}
                onPress={() => setAction(a.id)}
                style={[
                  styles.chip,
                  {
                    backgroundColor: active ? '#241a08' : 'transparent',
                    borderColor: active ? BRAND.amber : palette.border,
                  },
                ]}>
                <Text style={[styles.chipText, { color: active ? BRAND.amber : palette.textMuted }]}>
                  {a.label}
                </Text>
              </Pressable>
            );
          })}
        </View>
        <Field
          label="Entity type filter"
          value={entityType}
          onChangeText={setEntityType}
          placeholder="e.g. WorkOrder, User"
          autoCapitalize="none"
          autoCorrect={false}
        />
        <Button
          title="Export CSV"
          variant="outline"
          onPress={() =>
            WebBrowser.openBrowserAsync(
              auditLogsExportUrl({
                action: action === 'all' ? undefined : action,
                entity_type: entityType.trim() || undefined,
              }),
            )
          }
        />
      </View>
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <LegendList
          data={query.data?.data ?? []}
          keyExtractor={(l) => String(l.id)}
          contentContainerStyle={styles.list}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          ListEmptyComponent={<EmptyState title="No audit logs match these filters" />}
          renderItem={({ item }) => {
            const tone = actionTone(item.action, palette);
            return (
              <Card accent={tone.accent}>
                <View style={styles.row}>
                  <View style={[styles.actionPill, { backgroundColor: tone.bg }]}>
                    <Mono size={9.5} color={tone.fg} letterSpacing={0.6} weight="700">
                      {item.action.toUpperCase()}
                    </Mono>
                  </View>
                  <Text style={[styles.entity, { color: palette.text }]} numberOfLines={1}>
                    {item.entity_name ?? item.entity_type} #{item.entity_id}
                  </Text>
                </View>
                <Mono size={11} color={palette.textFaint} style={{ marginTop: 6 }}>
                  {(item.user?.username ?? 'system').toUpperCase()}
                  {' · '}
                  {format(parseISO(item.created_at), 'd MMM yyyy, HH:mm:ss')}
                  {item.ip_address ? ` · ${item.ip_address}` : ''}
                </Mono>
              </Card>
            );
          }}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} />}
        />
      )}
    </View>
  );
}

function actionTone(a: string, palette: { success: string; warning: string; danger: string; info: string }) {
  switch (a) {
    case 'created':
      return { bg: '#dff5e9', fg: '#0f7a4f', accent: palette.success };
    case 'updated':
      return { bg: '#e2ecfa', fg: '#1d4ed8', accent: palette.info };
    case 'deleted':
      return { bg: '#fbe2e2', fg: '#991b1b', accent: palette.danger };
    default:
      return { bg: '#ebe8e0', fg: '#5f5d56', accent: '#8a8780' };
  }
}

const styles = StyleSheet.create({
  toolbar: { padding: 18, paddingTop: 14, gap: 12, borderBottomWidth: StyleSheet.hairlineWidth },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingVertical: 7, paddingHorizontal: 12, borderRadius: 999, borderWidth: 1 },
  chipText: { fontSize: 12, fontWeight: '600', textTransform: 'capitalize' },
  list: { padding: 18 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  entity: { flex: 1, fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  actionPill: { paddingVertical: 3, paddingHorizontal: 8, borderRadius: 4 },
});
