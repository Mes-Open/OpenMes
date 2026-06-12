import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import * as WebBrowser from 'expo-web-browser';

import { createBatch } from '@/api/batches';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { TransitionButtons } from '@/components/workorders/TransitionButtons';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useBatches } from '@/hooks/queries/useBatch';
import { useWorkOrder } from '@/hooks/queries/useWorkOrders';
import { useLotPreview } from '@/hooks/queries/useLot';
import { useWorkstations } from '@/hooks/queries/useLines';
import { isWorkOrderOverdue } from '@/lib/statusLabels';
import { useWorkOrderRealtime } from '@/hooks/useWorkOrderRealtime';
import { useSettingsStore } from '@/stores/settingsStore';

export function WorkOrderDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const qc = useQueryClient();
  const { t } = useTranslation();
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  const wo = useWorkOrder(numericId);
  const batches = useBatches(numericId);

  useWorkOrderRealtime(Number.isFinite(numericId) ? numericId : undefined);

  const [showCreate, setShowCreate] = useState(false);
  const [qty, setQty] = useState('');
  const [lotOverride, setLotOverride] = useState('');
  const [workstationId, setWorkstationId] = useState<number | null>(null);

  const createMutation = useMutation({
    mutationFn: () =>
      createBatch(numericId, {
        target_qty: Number(qty),
        workstation_id: workstationId,
        lot_number: lotOverride.trim() || null,
      }),
    onSuccess: (batch) => {
      setShowCreate(false);
      setQty('');
      setLotOverride('');
      setWorkstationId(null);
      qc.invalidateQueries({ queryKey: ['batches', numericId] });
      qc.invalidateQueries({ queryKey: ['work-order', numericId] });
      router.push(`/work-orders/${numericId}/run/${batch.id}`);
    },
    onError: (err: Error) => Alert.alert('Could not create batch', err.message),
  });

  if (wo.isLoading) return <LoadingState />;
  if (wo.isError || !wo.data) return <ErrorState error={wo.error} onRetry={wo.refetch} />;

  const data = wo.data;
  const batchList = batches.data ?? data.batches ?? [];
  const planned = data.planned_qty ?? 0;
  const produced = data.produced_qty ?? 0;
  const pct = planned > 0 ? Math.min(100, Math.round((produced / planned) * 100)) : 0;
  const overdue = isWorkOrderOverdue(data);

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader back title={data.order_no} subtitle={data.product_type?.name?.toUpperCase()} />
    <LegendList
      style={{ backgroundColor: palette.background }}
      data={batchList}
      keyExtractor={(b) => String(b.id)}
      contentContainerStyle={styles.container}
      refreshControl={
        <RefreshControl
          refreshing={wo.isFetching || batches.isFetching}
          onRefresh={() => {
            wo.refetch();
            batches.refetch();
          }}
        />
      }
      ListHeaderComponent={
        <View style={{ gap: 18 }}>
          {/* Hero meta */}
          <View>
            <View style={styles.headerRow}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono size={11} color={palette.textFaint}>{data.order_no}</Mono>
                <Text style={[styles.product, { color: palette.text }]} numberOfLines={2}>
                  {data.product_type?.name ?? 'Work order'}
                </Text>
                <View style={{ flexDirection: 'row', gap: 8, marginTop: 2 }}>
                  <Mono size={11} color={palette.textFaint}>
                    {planned} {t('PCS PLANNED').toUpperCase()}
                  </Mono>
                  {overdue ? (
                    <Mono size={11} color={palette.danger} weight="700">
                      · {t('Overdue').toUpperCase()}
                    </Mono>
                  ) : null}
                </View>
              </View>
              <StatusPill status={data.status} />
            </View>

            {planned > 0 ? (
              <View style={styles.heroProgress}>
                <View style={styles.heroProgressRow}>
                  <Mono size={11} color={palette.textMuted}>
                    {produced}/{planned} {t('produced')}
                  </Mono>
                  <Mono size={11} color={palette.text} weight="700">
                    {pct}%
                  </Mono>
                </View>
                <View style={[styles.barTrack, { backgroundColor: palette.surfaceAlt }]}>
                  <View style={[styles.barFill, { width: `${pct}%` }]} />
                </View>
              </View>
            ) : null}

            <View style={styles.metaGrid}>
              <MetaCard label={t('LINE')} value={data.line?.name ?? '—'} />
              <MetaCard label={t('BATCHES')} value={String(batchList.length)} />
              <MetaCard
                label={t('DUE')}
                value={data.due_date ? data.due_date.slice(5, 10) : '—'}
                tone={overdue ? 'danger' : undefined}
              />
            </View>
          </View>

          <TransitionButtons workOrderId={data.id} status={data.status} />

          <View style={styles.quickRow}>
            <QuickAction
              icon="exclamation-triangle"
              label={t('Anomalies')}
              onPress={() =>
                router.push(`/work-orders/${data.id}/costs?tab=anomalies` as never)
              }
            />
            <QuickAction
              icon="dollar"
              label={t('Costs')}
              onPress={() => router.push(`/work-orders/${data.id}/costs` as never)}
            />
            <QuickAction
              icon="paperclip"
              label={t('Files')}
              onPress={() =>
                router.push(`/work-orders/${data.id}/costs?tab=attachments` as never)
              }
            />
            <QuickAction
              icon="print"
              label={t('Print')}
              onPress={() =>
                WebBrowser.openBrowserAsync(`${serverUrl}/admin/work-orders/${data.id}`)
              }
            />
          </View>

          <View>
            <SectionLabel
              right={
                showCreate ? null : (
                  <Pressable onPress={() => setShowCreate(true)}>
                    <Mono size={11} color={BRAND.amber} weight="700">+ {t('NEW BATCH').toUpperCase()}</Mono>
                  </Pressable>
                )
              }>
              {`Batches · ${batchList.length}`}
            </SectionLabel>
          </View>

          {showCreate ? (
            <CreateBatchForm
              qty={qty}
              setQty={setQty}
              lotOverride={lotOverride}
              setLotOverride={setLotOverride}
              workstationId={workstationId}
              setWorkstationId={setWorkstationId}
              productTypeId={data.product_type_id ?? undefined}
              lineId={data.line_id ?? undefined}
              loading={createMutation.isPending}
              onSubmit={() => createMutation.mutate()}
              onCancel={() => {
                setShowCreate(false);
                setQty('');
                setLotOverride('');
                setWorkstationId(null);
              }}
            />
          ) : null}
        </View>
      }
      ListEmptyComponent={
        <EmptyState title="No batches yet" subtitle="Create a batch to start production." />
      }
      ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
      renderItem={({ item }) => {
        const total = item.steps?.length ?? 0;
        const done = item.steps?.filter((s) => s.status === 'DONE').length ?? 0;
        const stepPct = total > 0 ? Math.round((done / total) * 100) : 0;
        return (
          <Card onPress={() => router.push(`/work-orders/${numericId}/run/${item.id}`)}>
            <View style={styles.headerRow}>
              <View style={[styles.batchIcon, { backgroundColor: palette.surfaceAlt }]}>
                <Mono size={11} color={palette.text} weight="700">#{item.id}</Mono>
              </View>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text style={{ color: palette.text, fontSize: 14, fontWeight: '600' }}>
                  {t('Batch')} #{item.id}
                </Text>
                <Mono size={11} color={palette.textFaint} style={{ marginTop: 2 }}>
                  {item.produced_qty ?? 0}/{item.target_qty} {t('PCS').toUpperCase()}
                  {total > 0 ? ` · ${done}/${total} ${t('STEPS').toUpperCase()}` : ''}
                </Mono>
              </View>
              <StatusPill status={item.status} />
            </View>
            {total > 0 ? (
              <View style={styles.batchSteps}>
                {Array.from({ length: total }).map((_, i) => {
                  const s = item.steps?.[i];
                  const k = s?.status === 'DONE' ? 'done' : s?.status === 'IN_PROGRESS' ? 'running' : 'queued';
                  return (
                    <View
                      key={i}
                      style={[
                        styles.stepBlock,
                        {
                          backgroundColor:
                            k === 'done' ? palette.success : k === 'running' ? BRAND.amber : palette.surfaceAlt,
                        },
                      ]}
                    />
                  );
                })}
              </View>
            ) : null}
            {stepPct > 0 ? (
              <Mono size={10} color={palette.textFaint} style={{ marginTop: 6 }}>
                {stepPct}% {t('COMPLETE').toUpperCase()}
              </Mono>
            ) : null}
          </Card>
        );
      }}
    />
    </View>
  );
}

function MetaCard({ label, value, tone }: { label: string; value: string; tone?: 'danger' }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const isDanger = tone === 'danger';
  return (
    <View
      style={[
        styles.metaCard,
        { backgroundColor: palette.surface, borderColor: isDanger ? palette.danger : palette.border },
      ]}>
      <Mono size={10} color={isDanger ? palette.danger : palette.textFaint} letterSpacing={0.6}>{label}</Mono>
      <Text
        style={[
          styles.metaValue,
          { color: isDanger ? palette.danger : palette.text, fontFamily: MONO },
        ]}
        numberOfLines={1}>
        {value}
      </Text>
    </View>
  );
}

function QuickAction({
  icon,
  label,
  onPress,
}: {
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  label: string;
  onPress: () => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.quickItem,
        { backgroundColor: palette.surface, borderColor: palette.border, opacity: pressed ? 0.85 : 1 },
      ]}>
      <FontAwesome name={icon} size={14} color={palette.textMuted} />
      <Text style={{ fontSize: 12, fontWeight: '600', color: palette.text }}>{label}</Text>
    </Pressable>
  );
}

function CreateBatchForm({
  qty,
  setQty,
  lotOverride,
  setLotOverride,
  workstationId,
  setWorkstationId,
  productTypeId,
  lineId,
  loading,
  onSubmit,
  onCancel,
}: {
  qty: string;
  setQty: (v: string) => void;
  lotOverride: string;
  setLotOverride: (v: string) => void;
  workstationId: number | null;
  setWorkstationId: (v: number | null) => void;
  productTypeId?: number;
  lineId?: number;
  loading: boolean;
  onSubmit: () => void;
  onCancel: () => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const lotPreview = useLotPreview(productTypeId, !lotOverride.trim());
  const workstations = useWorkstations(lineId);
  const stations = (workstations.data ?? []).filter((w) => w.is_active);

  return (
    <Card style={{ gap: 12 }}>
      <Field
        label="Quantity"
        value={qty}
        onChangeText={setQty}
        keyboardType="number-pad"
        placeholder="e.g. 50"
      />

      <Field
        label="LOT number (override, optional)"
        value={lotOverride}
        onChangeText={setLotOverride}
        placeholder={lotPreview.data ?? 'Auto-generated'}
        autoCapitalize="characters"
        hint={!lotOverride.trim() && lotPreview.data ? `Next: ${lotPreview.data}` : undefined}
      />

      {stations.length > 0 ? (
        <View style={{ gap: 8 }}>
          <Mono size={10} color={palette.textFaint} letterSpacing={0.8}>WORKSTATION (OPTIONAL)</Mono>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
            <ChipBtn label="None" active={workstationId == null} onPress={() => setWorkstationId(null)} />
            {stations.map((w) => (
              <ChipBtn
                key={w.id}
                label={w.name}
                active={w.id === workstationId}
                onPress={() => setWorkstationId(w.id)}
              />
            ))}
          </View>
        </View>
      ) : null}

      <View style={{ flexDirection: 'row', gap: 8 }}>
        <Button
          title="Create"
          onPress={onSubmit}
          loading={loading}
          disabled={!qty || Number(qty) <= 0}
          style={{ flex: 1 }}
        />
        <Button title="Cancel" variant="outline" onPress={onCancel} style={{ flex: 1 }} />
      </View>
    </Card>
  );
}

function ChipBtn({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Pressable
      onPress={onPress}
      style={{
        paddingVertical: 7,
        paddingHorizontal: 12,
        borderRadius: 999,
        borderWidth: 1,
        borderColor: active ? BRAND.amber : palette.border,
        backgroundColor: active ? '#fbe9c8' : 'transparent',
      }}>
      <Text style={{ fontSize: 12, fontWeight: '600', color: active ? '#8a5a0e' : palette.text }}>
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: { padding: 18, gap: 10 },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  product: { fontSize: 22, fontWeight: '600', letterSpacing: -0.3, marginTop: 4 },
  heroProgress: { gap: 6, marginTop: 14 },
  heroProgressRow: { flexDirection: 'row', justifyContent: 'space-between' },
  barTrack: { height: 6, borderRadius: 1, overflow: 'hidden' },
  barFill: { height: '100%', backgroundColor: BRAND.amber },
  metaGrid: { flexDirection: 'row', gap: 8, marginTop: 14 },
  metaCard: { flex: 1, padding: 10, borderRadius: 10, borderWidth: 1, gap: 4 },
  metaValue: { fontSize: 14, fontWeight: '600', letterSpacing: 0.2 },
  quickRow: { flexDirection: 'row', gap: 8 },
  quickItem: {
    flex: 1,
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  batchIcon: { width: 38, height: 38, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  batchSteps: { flexDirection: 'row', gap: 4, marginTop: 12 },
  stepBlock: { flex: 1, height: 4, borderRadius: 1 },
});
