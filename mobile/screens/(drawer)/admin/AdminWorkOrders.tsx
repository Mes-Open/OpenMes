/**
 * Admin Work Orders — 1:1 with the web admin/work-orders table
 * (Pages/admin/work-orders/Index.jsx): the shared DataTable with the web's
 * column set (Order / Line / Product / Produced-Planned / Status / Prio / Due /
 * Batches), the built-in search, an inline ACTIONS ladder mirroring the web's
 * lifecycle transitions (accept/reject/pause/complete/resume/cancel/reopen +
 * edit/delete), a status filter Dropdown that honors the ?status= deep-link,
 * and "+ New Work Order". Data + mutations via REST (useWorkOrders). Rows still
 * open the WO detail; the raw status enum is shown verbatim like the web pill.
 */
import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, Modal, colors, fonts, radius } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useDeleteWorkOrder,
  useTransitionWorkOrder,
  useWorkOrders,
} from '@/hooks/queries/useWorkOrders';
import { statusLabel } from '@/lib/statusLabels';
import type { WorkOrderTransition } from '@/api/workOrders';
import type { WorkOrder, WorkOrderStatus } from '@/types/api';

const STATUSES: WorkOrderStatus[] = [
  'PENDING', 'ACCEPTED', 'IN_PROGRESS', 'PAUSED', 'BLOCKED', 'DONE', 'REJECTED', 'CANCELLED',
];

const TERMINAL: WorkOrderStatus[] = ['DONE', 'REJECTED', 'CANCELLED'];

// fg/bg per status — mirrors the web WO_STATUS_STYLES, mapped onto design tokens.
// ACCEPTED keeps web's blue-100/800 (the token set has no blue pair).
const WO_PILL: Record<string, { bg: string; fg: string }> = {
  PENDING: { bg: colors.pendingBg, fg: colors.pending },
  ACCEPTED: { bg: colors.acceptedBg, fg: colors.accepted },
  IN_PROGRESS: { bg: colors.runningBg, fg: colors.running },
  PAUSED: { bg: colors.chip, fg: colors.muted },
  BLOCKED: { bg: colors.blockedBg, fg: colors.blocked },
  DONE: { bg: colors.doneBg, fg: colors.done },
  REJECTED: { bg: colors.blockedBg, fg: colors.blocked },
  CANCELLED: { bg: colors.chip, fg: colors.faint },
};

/** Local pill that shows the RAW enum text (web renders `{r.status}` verbatim). */
function WoStatusPill({ status }: { status: string }) {
  const c = WO_PILL[status] ?? { bg: colors.chip, fg: colors.muted };
  return (
    <View style={[styles.pill, { backgroundColor: c.bg }]}>
      <Text style={[styles.pillText, { color: c.fg }]} numberOfLines={1}>
        {status}
      </Text>
    </View>
  );
}

export function AdminWorkOrdersScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const { status: statusParam, line_id: lineParam } = useLocalSearchParams<{
    status?: string;
    line_id?: string;
  }>();
  const [status, setStatus] = useState<string>(
    STATUSES.includes(statusParam as WorkOrderStatus) ? (statusParam as string) : '',
  );
  const [lineId, setLineId] = useState<string | null>(lineParam ?? null);

  const wq = useWorkOrders({ per_page: 200 });
  const all = useMemo(() => wq.data ?? [], [wq.data]);

  const transition = useTransitionWorkOrder();
  const del = useDeleteWorkOrder();

  // Complete needs a produced-qty input (web uses prompt()); collect it in a modal.
  const [completeFor, setCompleteFor] = useState<WorkOrder | null>(null);
  const [completeQty, setCompleteQty] = useState('');

  const rows = useMemo(
    () =>
      all
        .filter(
          (o) =>
            (!status || o.status === status) &&
            (!lineId || String(o.line_id) === String(lineId)),
        )
        .sort((a, b) => a.order_no.localeCompare(b.order_no, undefined, { numeric: true })),
    [all, status, lineId],
  );

  const lineName = lineId
    ? all.find((o) => String(o.line_id) === String(lineId))?.line?.name
    : undefined;
  const hasFilter = Boolean(status || lineId);

  const onError = (e: unknown) => Alert.alert(t('Error'), (e as Error).message);

  const run = (id: number, verb: WorkOrderTransition, data?: Record<string, unknown>) =>
    transition.mutate({ id, transition: verb, data }, { onError });

  const confirmCancel = (wo: WorkOrder) =>
    Alert.alert(t('Cancel work order'), t('Cancel work order {{no}}?', { no: wo.order_no }), [
      { text: t('No'), style: 'cancel' },
      { text: t('Yes'), style: 'destructive', onPress: () => run(wo.id, 'cancel') },
    ]);

  const confirmDelete = (wo: WorkOrder) =>
    Alert.alert(
      t('Delete work order'),
      t('Delete work order {{no}}? (only allowed if it has no batches)', { no: wo.order_no }),
      [
        { text: t('Cancel'), style: 'cancel' },
        {
          text: t('Delete'),
          style: 'destructive',
          onPress: () => del.mutate(wo.id, { onError }),
        },
      ],
    );

  const submitComplete = () => {
    if (!completeFor) return;
    const wo = completeFor;
    setCompleteFor(null);
    run(wo.id, 'complete', { produced_qty: Number(completeQty) });
  };

  const actions = (wo: WorkOrder) => {
    const s = wo.status;
    const list: {
      label: string;
      icon?: 'edit' | 'delete';
      variant?: 'primary' | 'secondary' | 'danger' | 'warning';
      onPress: () => void;
    }[] = [
      { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/work-orders/${wo.id}` as never) },
    ];

    if (s === 'PENDING') {
      list.push({ label: t('Accept'), onPress: () => run(wo.id, 'accept') });
      list.push({ label: t('Reject'), onPress: () => run(wo.id, 'reject') });
    } else if (s === 'ACCEPTED') {
      list.push({ label: t('Reject'), onPress: () => run(wo.id, 'reject') });
    } else if (s === 'IN_PROGRESS') {
      list.push({ label: t('Pause'), onPress: () => run(wo.id, 'pause') });
      list.push({
        label: t('Complete'),
        onPress: () => {
          setCompleteQty(String(wo.planned_qty ?? ''));
          setCompleteFor(wo);
        },
      });
    } else if (s === 'PAUSED') {
      list.push({ label: t('Resume'), onPress: () => run(wo.id, 'resume') });
    }

    if (TERMINAL.includes(s)) {
      list.push({ label: t('Reopen'), onPress: () => run(wo.id, 'reopen') });
    } else {
      list.push({ label: t('Cancel'), variant: 'warning', onPress: () => confirmCancel(wo) });
    }

    list.push({ label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => confirmDelete(wo) });
    return list;
  };

  const clearFilters = () => {
    setStatus('');
    setLineId(null);
    router.setParams({ status: undefined, line_id: undefined } as never);
  };

  // Status filter lives in the DataTable toolbar (toolbarExtra), beside the
  // built-in search — one row, like the web ResourceTable toolbar.
  const statusFilter = (
    <View style={{ width: 168 }}>
      <Dropdown
        value={status}
        onChange={(v) => setStatus(v as string)}
        placeholder={t('All statuses')}
        options={[
          { value: '', label: t('All statuses') },
          ...STATUSES.map((s) => ({ value: s, label: statusLabel(s) })),
        ]}
      />
    </View>
  );

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Work Orders')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 168 }}>
          <Button
            title={t('+ New Work Order')}
            variant="primary"
            size="sm"
            onPress={() => router.push('/work-orders/new' as never)}
          />
        </View>
      </View>

      {hasFilter ? (
        <View style={styles.chips}>
          {status ? <WoStatusPill status={status} /> : null}
          {lineId ? (
            <View style={[styles.pill, { backgroundColor: colors.chip }]}>
              <Text style={[styles.pillText, { color: colors.muted }]} numberOfLines={1}>
                {lineName ?? `${t('Line')} ${lineId}`}
              </Text>
            </View>
          ) : null}
          <Pressable onPress={clearFilters} hitSlop={8}>
            <Text style={styles.clear}>{t('Clear')}</Text>
          </Pressable>
        </View>
      ) : null}

      {wq.isLoading && !wq.data ? (
        <LoadingState />
      ) : wq.isError && !wq.data ? (
        <ErrorState error={wq.error} onRetry={wq.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.content}
          refreshControl={<RefreshControl refreshing={wq.isFetching} onRefresh={wq.refetch} tintColor={colors.accent} />}>
          <DataTable<WorkOrder>
            data={rows}
            searchable
            searchPlaceholder={t('Search work orders')}
            searchKeys={['order_no', 'status']}
            toolbarExtra={statusFilter}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            emptyText={t('No work orders yet.')}
            onRowPress={(wo) => router.push(`/work-orders/${wo.id}` as never)}
            actions={actions}
            actionsWidth={330}
            columns={[
              { key: 'order_no', label: t('Order'), width: 118, render: (wo) => <Mono size={12.5} color={colors.ink} weight="400">{wo.order_no}</Mono> },
              { key: 'line', label: t('Line'), flex: 1, render: (wo) => wo.line?.name ?? '—' },
              { key: 'product', label: t('Product'), flex: 1.2, render: (wo) => wo.product_type?.name ?? '—' },
              { key: 'qty', label: t('Produced / Planned'), width: 104, render: (wo) => <Mono size={11} color={colors.muted}>{`${Number(wo.produced_qty ?? 0).toFixed(0)} / ${Number(wo.planned_qty ?? 0).toFixed(0)}`}</Mono> },
              { key: 'status', label: t('Status'), width: 122, render: (wo) => <WoStatusPill status={wo.status} /> },
              { key: 'priority', label: t('Prio'), width: 44, render: (wo) => (wo.priority != null ? String(wo.priority) : '—') },
              { key: 'due_date', label: t('Due'), width: 86, render: (wo) => <Mono size={11} color={colors.muted}>{wo.due_date ? String(wo.due_date).slice(0, 10) : '—'}</Mono> },
              { key: 'batches', label: t('Batches'), width: 64, render: (wo) => String(wo.batches?.length ?? 0) },
            ]}
          />
        </ScrollView>
      )}

      <Modal
        open={completeFor != null}
        onClose={() => setCompleteFor(null)}
        title={t('Complete work order')}
        subtitle={completeFor?.order_no}
        closeLabel={t('Close')}
        footer={
          <>
            <View style={{ width: 110 }}>
              <Button title={t('Cancel')} variant="secondary" size="sm" onPress={() => setCompleteFor(null)} />
            </View>
            <View style={{ width: 130 }}>
              <Button title={t('Complete')} variant="primary" size="sm" onPress={submitComplete} />
            </View>
          </>
        }>
        <Field
          label={t('Produced quantity')}
          keyboardType="numeric"
          value={completeQty}
          onChangeText={setCompleteQty}
          mono
        />
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { paddingHorizontal: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16, paddingBottom: 12 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  chips: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: 18, paddingBottom: 12 },
  pill: { alignSelf: 'flex-start', borderRadius: radius.sm, paddingHorizontal: 8, paddingVertical: 3 },
  pillText: { fontSize: 11, fontFamily: fonts.sans.native.medium },
  clear: { fontSize: 13, color: colors.accent, fontFamily: fonts.sans.native.medium },
});
