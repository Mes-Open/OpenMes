/**
 * Work-order detail — mirrors the web admin work-order Show page: order_no
 * eyebrow + product + status, a completion progress block, a LINE / BATCHES /
 * DUE meta box, status transitions, the batches table and links out to
 * anomalies / costs / attachments. Re-skin only — the Log-output bottom sheet,
 * create-batch flow and TransitionButtons keep their exact behavior.
 */
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';
import { FontAwesome } from '@expo/vector-icons';
import { format, isValid, parseISO } from 'date-fns';
import { useTranslation } from 'react-i18next';
import * as WebBrowser from 'expo-web-browser';

import { colors, fonts, radius } from '@openmes/ui';
import { BigStepper, BottomSheet } from '@openmes/ui/native';

import { createBatch } from '@/api/batches';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { TransitionButtons } from '@/components/workorders/TransitionButtons';
import { useBatches } from '@/hooks/queries/useBatch';
import { useIssues } from '@/hooks/queries/useIssues';
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
  const qc = useQueryClient();
  const { t } = useTranslation();
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  const wo = useWorkOrder(numericId);
  const batches = useBatches(numericId);
  const issues = useIssues(Number.isFinite(numericId) ? { work_order_id: numericId } : {});

  useWorkOrderRealtime(Number.isFinite(numericId) ? numericId : undefined);

  const [showCreate, setShowCreate] = useState(false);
  const [qty, setQty] = useState('');
  const [lotOverride, setLotOverride] = useState('');
  const [workstationId, setWorkstationId] = useState<number | null>(null);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [sheetQty, setSheetQty] = useState(1);

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
    onError: (err: Error) => Alert.alert(t('Could not create batch'), err.message),
  });

  // Quick "Log output" path (design: OpenMES Mobile.dc.html bottom sheet) — start
  // a batch of N pcs with defaults (auto lot, default workstation), then drop into
  // the run screen to produce. The full +NEW BATCH form covers lot/workstation.
  const quickLog = useMutation({
    mutationFn: () =>
      createBatch(numericId, { target_qty: sheetQty, workstation_id: null, lot_number: null }),
    onSuccess: (batch) => {
      setSheetOpen(false);
      qc.invalidateQueries({ queryKey: ['batches', numericId] });
      qc.invalidateQueries({ queryKey: ['work-order', numericId] });
      router.push(`/work-orders/${numericId}/run/${batch.id}`);
    },
    onError: (err: Error) => Alert.alert(t('Could not start batch'), err.message),
  });

  if (wo.isLoading) return <LoadingState />;
  if (wo.isError || !wo.data) return <ErrorState error={wo.error} onRetry={wo.refetch} />;

  const data = wo.data;
  const batchList = batches.data ?? data.batches ?? [];
  const planned = Number(data.planned_qty ?? 0);
  const produced = Number(data.produced_qty ?? 0);
  const pct = planned > 0 ? Math.min(100, (produced / planned) * 100) : 0;
  const overdue = isWorkOrderOverdue(data);
  const issueList = issues.data ?? [];
  const priorityLabel =
    data.priority != null && String(data.priority).trim() !== '' ? String(data.priority) : '—';

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1} numberOfLines={1}>{data.order_no}</Text>
        <StatusPill status={data.status} />
      </View>
      <LegendList
        style={styles.screen}
        data={batchList}
        keyExtractor={(b) => String(b.id)}
        contentContainerStyle={styles.container}
        refreshControl={
          <RefreshControl
            refreshing={wo.isFetching || batches.isFetching}
            tintColor={colors.accent}
            onRefresh={() => {
              wo.refetch();
              batches.refetch();
            }}
          />
        }
        ListHeaderComponent={
          <View style={{ gap: 18 }}>
            {/* Hero */}
            <View>
              <Mono size={11} color={colors.faint}>{data.order_no}</Mono>
              <Text style={styles.product} numberOfLines={2}>
                {data.product_type?.name ?? t('Work order')}
              </Text>
              <View style={styles.heroMetaRow}>
                <Mono size={11} color={colors.faint}>
                  {planned} {t('PCS PLANNED').toUpperCase()}
                </Mono>
                {overdue ? (
                  <Mono size={11} color={colors.blocked} weight="700">
                    · {t('Overdue').toUpperCase()}
                  </Mono>
                ) : null}
              </View>
            </View>

            {/* Progress */}
            {planned > 0 ? (
              <View style={styles.box}>
                <View style={styles.progressRow}>
                  <Mono size={11} color={colors.muted}>
                    {produced}/{planned} {t('produced')}
                  </Mono>
                  <Mono size={11} color={colors.ink} weight="700">{pct.toFixed(1)}%</Mono>
                </View>
                <View style={styles.barTrack}>
                  <View style={[styles.barFill, { width: `${pct}%` }]} />
                </View>
              </View>
            ) : null}

            {/* Meta */}
            <View style={styles.metaGrid}>
              <MetaCell label={t('LINE')} value={data.line?.name ?? '—'} />
              <MetaCell label={t('PRIORITY')} value={priorityLabel} />
              <MetaCell label={t('BATCHES')} value={String(batchList.length)} />
              <MetaCell label={t('DUE')} value={fmtDueDate(data.due_date)} danger={overdue} />
            </View>

            <Button
              title={t('Log output')}
              onPress={() => {
                setSheetQty(Math.max(1, planned - produced));
                setSheetOpen(true);
              }}
              leftIcon={<FontAwesome name="plus" size={13} color="#FFFFFF" />}
            />

            <TransitionButtons workOrderId={data.id} status={data.status} />

            {/* Links */}
            <View style={styles.quickRow}>
              <QuickAction
                icon="exclamation-triangle"
                label={t('Anomalies')}
                onPress={() => router.push(`/work-orders/${data.id}/costs?tab=anomalies` as never)}
              />
              <QuickAction
                icon="dollar"
                label={t('Costs')}
                onPress={() => router.push(`/work-orders/${data.id}/costs` as never)}
              />
              <QuickAction
                icon="paperclip"
                label={t('Files')}
                onPress={() => router.push(`/work-orders/${data.id}/costs?tab=attachments` as never)}
              />
              <QuickAction
                icon="print"
                label={t('Print')}
                onPress={() => WebBrowser.openBrowserAsync(`${serverUrl}/admin/work-orders/${data.id}`)}
              />
            </View>

            {/* Issues (mirrors the web Show page's Issues sidebar) */}
            {issueList.length > 0 ? (
              <View style={{ gap: 8 }}>
                <View style={styles.sectionHead}>
                  <Mono size={9} color={colors.faint} letterSpacing={0.6}>
                    {t('Issues').toUpperCase()} · {issueList.length}
                  </Mono>
                </View>
                {issueList.map((issue) => (
                  <Pressable
                    key={issue.id}
                    onPress={() => router.push(`/issues/${issue.id}` as never)}
                    style={({ pressed }) => [styles.issueRow, { opacity: pressed ? 0.6 : 1 }]}>
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text style={styles.issueTitle} numberOfLines={1}>
                        {issue.issue_type?.name ?? issue.description ?? t('Issue')}
                      </Text>
                      {issue.created_at ? (
                        <Mono size={9} color={colors.faint} style={{ marginTop: 3 }}>
                          {fmtDueDate(issue.created_at)}
                        </Mono>
                      ) : null}
                    </View>
                    <StatusPill status={issue.status} />
                    <Text style={styles.issueChevron}>›</Text>
                  </Pressable>
                ))}
              </View>
            ) : null}

            {/* Batches */}
            <View style={styles.sectionHead}>
              <Mono size={9} color={colors.faint} letterSpacing={0.6}>
                {t('Batches').toUpperCase()} · {batchList.length}
              </Mono>
              <View style={{ flex: 1 }} />
              {showCreate ? null : (
                <Pressable onPress={() => setShowCreate(true)} hitSlop={8}>
                  <Mono size={11} color={colors.accent} weight="700">+ {t('New batch').toUpperCase()}</Mono>
                </Pressable>
              )}
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

            {batchList.length > 0 ? (
              <View style={[styles.row, styles.headerRow]}>
                <HCell w={64}>{t('Batch')}</HCell>
                <HCell flex={1}>{t('Qty')}</HCell>
                <HCell w={72}>{t('Steps')}</HCell>
                <HCell w={96}>{t('Status')}</HCell>
              </View>
            ) : null}
          </View>
        }
        ListEmptyComponent={<Text style={styles.empty}>{t('No batches yet.')}</Text>}
        renderItem={({ item }) => {
          const total = item.steps?.length ?? 0;
          const done = item.steps?.filter((s) => s.status === 'DONE').length ?? 0;
          return (
            <Pressable
              onPress={() => router.push(`/work-orders/${numericId}/run/${item.id}`)}
              style={({ pressed }) => [styles.row, styles.dataRow, { opacity: pressed ? 0.6 : 1 }]}>
              <View style={{ width: 64 }}>
                <Mono size={11} color={colors.ink}>#{item.batch_number ?? item.id}</Mono>
              </View>
              <View style={{ flex: 1 }}>
                <Mono size={11} color={colors.muted}>
                  {item.produced_qty ?? 0}/{item.target_qty} {t('PCS').toUpperCase()}
                </Mono>
              </View>
              <View style={{ width: 72 }}>
                <Mono size={11} color={colors.muted}>{total > 0 ? `${done}/${total}` : '—'}</Mono>
              </View>
              <View style={{ width: 96 }}>
                <StatusPill status={item.status} />
              </View>
            </Pressable>
          );
        }}
      />
      <BottomSheet
        open={sheetOpen}
        onClose={() => setSheetOpen(false)}
        title={t('Log output')}
        subtitle={`${data.order_no} · ${produced}/${planned} ${t('PCS').toUpperCase()}`}
        footer={
          <Button
            title={`${t('Start batch')} · ${sheetQty} ${t('PCS').toUpperCase()}`}
            onPress={() => quickLog.mutate()}
            loading={quickLog.isPending}
          />
        }>
        <View style={{ alignItems: 'center', paddingVertical: 6 }}>
          <BigStepper value={sheetQty} onChange={setSheetQty} min={1} />
          <Mono size={11} color={colors.faint} style={{ marginTop: 12 }}>
            {t('PCS').toUpperCase()}
          </Mono>
        </View>
      </BottomSheet>
    </View>
  );
}

/** Day-first localized date, matching the web Show page's fmtDate ('dd MMM yyyy'). */
function fmtDueDate(dateStr?: string | null): string {
  if (!dateStr) return '—';
  const d = parseISO(dateStr);
  return isValid(d) ? format(d, 'dd MMM yyyy') : '—';
}

function MetaCell({ label, value, danger }: { label: string; value: string; danger?: boolean }) {
  const tint = danger ? colors.blocked : colors.faint;
  return (
    <View style={[styles.metaCard, danger ? { borderColor: colors.blocked } : null]}>
      <Mono size={9} color={tint} letterSpacing={0.6}>{label}</Mono>
      <Mono size={14} color={danger ? colors.blocked : colors.ink} weight="600" numberOfLines={1} style={{ marginTop: 4 }}>
        {value}
      </Mono>
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
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.quickItem, { opacity: pressed ? 0.7 : 1 }]}>
      <FontAwesome name={icon} size={14} color={colors.muted} />
      <Text style={styles.quickLabel}>{label}</Text>
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
  const { t } = useTranslation();
  const lotPreview = useLotPreview(productTypeId, !lotOverride.trim());
  const workstations = useWorkstations(lineId);
  const stations = (workstations.data ?? []).filter((w) => w.is_active);

  return (
    <View style={[styles.box, { gap: 12 }]}>
      <Field
        label="Quantity"
        value={qty}
        onChangeText={setQty}
        keyboardType="number-pad"
        placeholder="e.g. 50"
        mono
      />

      <Field
        label="LOT number (override, optional)"
        value={lotOverride}
        onChangeText={setLotOverride}
        placeholder={lotPreview.data ?? 'Auto-generated'}
        autoCapitalize="characters"
        mono
        hint={!lotOverride.trim() && lotPreview.data ? t('Next: {{lot}}', { lot: lotPreview.data }) : undefined}
      />

      {stations.length > 0 ? (
        <View style={{ gap: 8 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Workstation (optional)').toUpperCase()}</Mono>
          <View style={styles.chipWrap}>
            <ChipBtn label={t('None')} active={workstationId == null} onPress={() => setWorkstationId(null)} />
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

      <View style={styles.formActions}>
        <Button title={t('Cancel')} variant="ghost" onPress={onCancel} />
        <View style={{ flex: 1 }} />
        <Button
          title={t('Create')}
          onPress={onSubmit}
          loading={loading}
          disabled={!qty || Number(qty) <= 0}
        />
      </View>
    </View>
  );
}

function ChipBtn({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      style={[styles.chip, active ? styles.chipActive : null]}>
      <Text style={[styles.chipText, active ? styles.chipTextActive : null]}>{label}</Text>
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
  h1: { flex: 1, fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  container: { paddingHorizontal: 14, paddingBottom: 24, gap: 10 },
  product: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.3, marginTop: 4 },
  heroMetaRow: { flexDirection: 'row', gap: 8, marginTop: 4 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  progressRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 },
  barTrack: { height: 6, borderRadius: 3, overflow: 'hidden', backgroundColor: colors.chip },
  barFill: { height: '100%', borderRadius: 3, backgroundColor: colors.accent },
  metaGrid: { flexDirection: 'row', gap: 8 },
  metaCard: { flex: 1, padding: 12, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.card },
  quickRow: { flexDirection: 'row', gap: 8 },
  quickItem: {
    flex: 1,
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  quickLabel: { fontSize: 12, fontFamily: fonts.sans.native.medium, color: colors.ink },
  sectionHead: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 2 },
  issueRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 12,
    paddingHorizontal: 12,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  issueTitle: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  issueChevron: { fontFamily: fonts.mono.native.regular, fontSize: 16, color: colors.faintest },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  chip: { paddingVertical: 7, paddingHorizontal: 12, borderRadius: radius.pill, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.card },
  chipActive: { borderColor: colors.accent, backgroundColor: colors.runningBg },
  chipText: { fontSize: 12, fontFamily: fonts.sans.native.medium, color: colors.ink },
  chipTextActive: { color: colors.accent },
  formActions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 2 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
