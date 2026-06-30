import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useDeleteProductionAnomaly,
  useProcessProductionAnomaly,
  useProductionAnomalies,
} from '@/hooks/queries/useWoExtras';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';

const STATUS_MAP: Record<string, string> = {
  draft: 'PENDING',
  processed: 'DONE',
};

export function AnomaliesList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const woId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = useProductionAnomalies({ work_order_id: woId });
  const processMutation = useProcessProductionAnomaly();
  const deleteMutation = useDeleteProductionAnomaly();

  const user = useAuthStore((s) => s.user);
  const canProcess = isSupervisorOrAdmin(user);
  const canDelete = user?.roles?.some((r) => r.name === 'Admin') ?? false;

  const items = query.data?.data ?? [];

  return (
    <ListScreen
      title="Anomalies"
      eyebrow={`WO #${woId} · ${items.length} REPORTED`}
      newRoute={`/work-orders/${woId}/anomalies/new`}
      items={items}
      keyExtractor={(a) => String(a.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No anomalies"
      emptySubtitle="Tap + to record one."
      renderItem={(item) => {
        const devNum = item.deviation_pct != null ? Number(item.deviation_pct) : null;
        const accent =
          devNum != null && Math.abs(devNum) > 10
            ? palette.danger
            : devNum != null && Math.abs(devNum) > 0
            ? palette.warning
            : BRAND.amber;
        return (
          <Card accent={accent}>
            <View style={styles.row}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono size={11} color={palette.textFaint}>
                  {(item.anomaly_reason?.code ?? `ANOM-${item.id}`).toUpperCase()}
                </Mono>
                <Text style={[styles.title, { color: palette.text }]} numberOfLines={1}>
                  {item.anomaly_reason?.name ?? 'Anomaly'}
                </Text>
              </View>
              <StatusPill status={STATUS_MAP[item.status] ?? 'PENDING'} label={item.status} />
            </View>

            <View style={styles.metaRow}>
              <Meta label="PLANNED" value={String(item.planned_qty)} />
              <Meta label="ACTUAL" value={String(item.actual_qty)} />
              {devNum != null ? (
                <Meta label="DEV" value={`${devNum}%`} accent={accent} />
              ) : null}
            </View>

            {item.comment ? (
              <Text
                style={{ color: palette.text, fontSize: 13, marginTop: 8, lineHeight: 19 }}
                numberOfLines={3}>
                {item.comment}
              </Text>
            ) : null}

            {item.created_by ? (
              <Mono size={11} color={palette.textFaint} style={{ marginTop: 8 }}>
                BY {(item.created_by.name ?? item.created_by.username).toUpperCase()}
              </Mono>
            ) : null}

            <View style={styles.actions}>
              {canProcess && item.status === 'draft' ? (
                <Pressable
                  onPress={() =>
                    processMutation.mutate(item.id, {
                      onError: (e: Error) => Alert.alert('Failed', e.message),
                    })
                  }
                  style={({ pressed }) => [
                    styles.actionBtn,
                    { borderColor: BRAND.amber, backgroundColor: '#FAF0DD', opacity: pressed ? 0.7 : 1 },
                  ]}>
                  <Mono size={11} color={'#8a5a0e'} weight="700">PROCESS</Mono>
                </Pressable>
              ) : null}
              {canDelete ? (
                <Pressable
                  onPress={() =>
                    Alert.alert('Delete anomaly', 'Permanently delete?', [
                      { text: 'Cancel', style: 'cancel' },
                      {
                        text: 'Delete',
                        style: 'destructive',
                        onPress: () =>
                          deleteMutation.mutate(item.id, {
                            onError: (e: Error) => Alert.alert('Failed', e.message),
                          }),
                      },
                    ])
                  }
                  style={({ pressed }) => [
                    styles.actionBtn,
                    {
                      borderColor: palette.danger,
                      backgroundColor: palette.dangerSoft,
                      opacity: pressed ? 0.7 : 1,
                    },
                  ]}>
                  <Mono size={11} color={palette.danger} weight="700">DELETE</Mono>
                </Pressable>
              ) : null}
            </View>
          </Card>
        );
      }}
    />
  );
}

function Meta({ label, value, accent }: { label: string; value: string; accent?: string }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View>
      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>{label}</Mono>
      <Text style={{ color: accent ?? palette.text, fontSize: 14, fontWeight: '600', marginTop: 2 }}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  title: { fontSize: 15, fontWeight: '600', letterSpacing: -0.2, marginTop: 3 },
  metaRow: { flexDirection: 'row', gap: 24, marginTop: 12 },
  actions: { flexDirection: 'row', gap: 8, marginTop: 12 },
  actionBtn: {
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 6,
    borderWidth: 1,
  },
});
