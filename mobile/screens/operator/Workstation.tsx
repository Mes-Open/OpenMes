/**
 * Operator Workstation board — native port of the web operator Workstation page
 * (backend/resources/js/Pages/operator/Workstation.jsx). Root-level
 * /operator/workstation route under the OperatorTopBar (no sidebar).
 *
 * Mirrors the web chrome: line title, a Queue/Workstation toggle, a Change Line
 * button, an optional machine-state panel, a search box, and a work-order table
 * showing To Produce / Produced / Remaining. Row actions open the Order Details
 * and Report Issue modals.
 *
 * HONEST GAP — the web board's core interaction (entering produced quantities per
 * shift, and the Start / Add-Produced-Quantity buttons) posts to web-only Inertia
 * routes (/operator/workstation/{wo}/start|complete|shift-entry) that have NO REST
 * equivalent. Mobile production is driven per-batch instead, so the "+" action
 * routes to the work-order run flow rather than a dead shift-entry modal, and the
 * per-shift quantity columns are omitted. What IS wired via REST: machine-state
 * override (POST /machine-monitor/{ws}/state) and issue reporting (POST /issues).
 */
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button, Dropdown, Modal, colors, fonts, radius } from '@openmes/ui';

import { OperatorTopBar } from '@/components/operator/OperatorTopBar';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCreateIssue, useIssueTypes } from '@/hooks/queries/useIssues';
import { useMachineMonitor, useSetWorkstationState } from '@/hooks/queries/useMachineMonitor';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { statusLabel } from '@/lib/statusLabels';
import { useAuthStore } from '@/stores/authStore';
import type { WorkOrder } from '@/types/api';

function fmt(n: number | string | null | undefined): string {
  const v = parseFloat(String(n ?? 0));
  return Number.isNaN(v) ? '0' : String(Math.round(v));
}

export function OperatorWorkstationScreen() {
  const { t } = useTranslation();
  const router = useRouter();

  const user = useAuthStore((s) => s.user);
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const line = (user?.lines ?? []).find((l) => l.id === activeLineId) ?? null;

  const woQuery = useWorkOrders({ line_id: activeLineId ?? undefined }, { refetchInterval: 30_000 });
  const issueTypesQuery = useIssueTypes();
  const machineQuery = useMachineMonitor();

  const [search, setSearch] = useState('');
  const [info, setInfo] = useState<WorkOrder | null>(null);
  const [reportWo, setReportWo] = useState<WorkOrder | null>(null);

  const q = search.trim().toLowerCase();
  const visible = useMemo(() => {
    const rows = woQuery.data ?? [];
    return q
      ? rows.filter(
          (wo) =>
            wo.order_no.toLowerCase().includes(q) ||
            (wo.product_type?.name ?? '').toLowerCase().includes(q),
        )
      : rows;
  }, [woQuery.data, q]);

  // Machine tiles for this line (machine-monitor is fleet-wide; filter by line name).
  const machines = (machineQuery.data?.tiles ?? []).filter((m) => !line || m.line === line.name);
  const machineStates = machineQuery.data?.states ?? [];

  if (woQuery.isLoading && !woQuery.data) {
    return (
      <View style={styles.screen}>
        <OperatorTopBar />
        <LoadingState />
      </View>
    );
  }
  if (woQuery.isError && !woQuery.data) {
    return (
      <View style={styles.screen}>
        <OperatorTopBar />
        <ErrorState error={woQuery.error} onRetry={woQuery.refetch} />
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <OperatorTopBar />
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={woQuery.isFetching} onRefresh={woQuery.refetch} tintColor={colors.accent} />
        }>
        {/* Header */}
        <View style={styles.headerRow}>
          <Text style={styles.h1}>{line?.name ?? t('Workstation')}</Text>
          <View style={styles.headerControls}>
            <View style={styles.segment}>
              <Pressable style={styles.segBtn} onPress={() => router.replace('/operator/queue' as never)}>
                <Text style={styles.segText}>{t('Queue')}</Text>
              </Pressable>
              <View style={[styles.segBtn, styles.segBtnActive]}>
                <Text style={[styles.segText, styles.segTextActive]}>{t('Workstation')}</Text>
              </View>
            </View>
            <Pressable style={styles.changeLine} onPress={() => router.push('/select-line' as never)}>
              <Text style={styles.changeLineText}>{t('Change Line')}</Text>
            </Pressable>
          </View>
        </View>

        {/* Machine state panel */}
        {machines.length > 0 ? (
          <MachineStatePanel machines={machines} states={machineStates} />
        ) : null}

        {/* Search */}
        <TextInput
          value={search}
          onChangeText={setSearch}
          placeholder={t('Search by order number, product or data...')}
          placeholderTextColor={colors.faint}
          style={styles.search}
          autoCapitalize="none"
          autoCorrect={false}
        />

        {/* Table */}
        {visible.length === 0 ? (
          <EmptyState title={t('No work orders found')} subtitle={t('Try a different search.')} />
        ) : (
          <View style={styles.tableCard}>
            <ScrollView horizontal showsHorizontalScrollIndicator>
              <View>
                <View style={styles.tHeadRow}>
                  <Th w={130}>{t('Order No')}</Th>
                  <Th w={160}>{t('Product')}</Th>
                  <Th w={120}>{t('Status')}</Th>
                  <Th w={90}>{t('To Produce')}</Th>
                  <Th w={90}>{t('Produced')}</Th>
                  <Th w={90}>{t('Remaining')}</Th>
                  <Th w={150}>{t('Actions')}</Th>
                </View>
                {visible.map((wo) => {
                  const planned = parseFloat(String(wo.planned_qty ?? 0)) || 0;
                  const produced = parseFloat(String(wo.produced_qty ?? 0)) || 0;
                  const remaining = Math.max(0, planned - produced);
                  const done = wo.status === 'DONE';
                  return (
                    <View key={wo.id} style={styles.tRow}>
                      <Td w={130}>
                        <Mono size={13} weight="600" color={colors.ink}>
                          {wo.order_no}
                        </Mono>
                      </Td>
                      <Td w={160}>
                        <Text style={styles.cellText} numberOfLines={2}>
                          {wo.product_type?.name ?? '—'}
                        </Text>
                      </Td>
                      <Td w={120}>
                        <StatusPill status={wo.status} />
                      </Td>
                      <Td w={90}>
                        <Mono size={15} color={colors.ink}>
                          {fmt(planned)}
                        </Mono>
                      </Td>
                      <Td w={90}>
                        <Mono size={15} color={colors.muted}>
                          {fmt(produced)}
                        </Mono>
                      </Td>
                      <Td w={90}>
                        <View style={[styles.remaining, remaining <= 0 ? styles.remainingDone : styles.remainingOpen]}>
                          <Mono size={15} weight="600" color={remaining <= 0 ? colors.running : '#fff'}>
                            {fmt(remaining)}
                          </Mono>
                        </View>
                      </Td>
                      <Td w={150}>
                        <View style={styles.rowActions}>
                          {!done ? (
                            <Pressable
                              style={[styles.iconBtn, { backgroundColor: colors.accent }]}
                              accessibilityLabel={t('Add produced quantity')}
                              onPress={() => router.push(`/work-orders/${wo.id}` as never)}>
                              <Text style={styles.iconGlyphLight}>+</Text>
                            </Pressable>
                          ) : null}
                          <Pressable
                            style={[styles.iconBtn, { backgroundColor: colors.blockedBg }]}
                            accessibilityLabel={t('Report problem')}
                            onPress={() => setReportWo(wo)}>
                            <Text style={[styles.iconGlyph, { color: colors.blocked }]}>!</Text>
                          </Pressable>
                          <Pressable
                            style={[styles.iconBtn, { backgroundColor: colors.chip }]}
                            accessibilityLabel={t('Details')}
                            onPress={() => setInfo(wo)}>
                            <Text style={[styles.iconGlyph, { color: colors.ink }]}>?</Text>
                          </Pressable>
                        </View>
                      </Td>
                    </View>
                  );
                })}
              </View>
            </ScrollView>
          </View>
        )}
      </ScrollView>

      <InfoModal wo={info} onClose={() => setInfo(null)} />
      <ReportIssueModal wo={reportWo} issueTypes={issueTypesQuery.data ?? []} onClose={() => setReportWo(null)} />
    </View>
  );
}

// ─── machine state panel ───────────────────────────────────────────────────────

function MachineStatePanel({
  machines,
  states,
}: {
  machines: { id: number; name: string; state: string; color: string }[];
  states: string[];
}) {
  const { t } = useTranslation();
  const setState = useSetWorkstationState();
  return (
    <View style={styles.machinePanel}>
      <Mono size={10} upper color={colors.faint} letterSpacing={0.8} style={{ marginBottom: 8 }}>
        {t('Machine state')}
      </Mono>
      <View style={styles.machineRow}>
        {machines.map((m) => (
          <View key={m.id} style={styles.machineTile}>
            <View style={[styles.machineDot, { backgroundColor: m.color || colors.faintest }]} />
            <Text style={styles.machineName}>{m.name}</Text>
            <Dropdown
              value={m.state}
              onChange={(v) => setState.mutate({ id: m.id, state: v as string })}
              options={states.map((s) => ({ value: s, label: t(s) }))}
              style={styles.machineDropdown}
            />
          </View>
        ))}
      </View>
    </View>
  );
}

// ─── modals ────────────────────────────────────────────────────────────────────

function InfoModal({ wo, onClose }: { wo: WorkOrder | null; onClose: () => void }) {
  const { t } = useTranslation();
  const planned = parseFloat(String(wo?.planned_qty ?? 0)) || 0;
  const produced = parseFloat(String(wo?.produced_qty ?? 0)) || 0;
  const remaining = Math.max(0, planned - produced);

  return (
    <Modal
      open={wo != null}
      onClose={onClose}
      title={t('Order Details')}
      subtitle={wo?.order_no}
      closeLabel={t('Close')}
      footer={
        <Button variant="primary" onPress={onClose} style={{ flex: 1 }}>
          {t('Close')}
        </Button>
      }>
      <InfoRow label={t('Order #')} value={wo?.order_no ?? '—'} mono />
      <InfoRow label={t('Product')} value={wo?.product_type?.name ?? '—'} />
      <InfoRow label={t('Line')} value={wo?.line?.name ?? '—'} />
      <InfoRow label={t('Status')} value={statusLabel(wo?.status)} />
      <View style={styles.infoTriple}>
        <InfoStat label={t('Planned')} value={fmt(planned)} color={colors.ink} />
        <InfoStat label={t('Produced')} value={fmt(produced)} color={colors.running} />
        <InfoStat label={t('Remaining')} value={fmt(remaining)} color={colors.accent} />
      </View>
      <InfoRow label={t('Priority')} value={wo?.priority != null && wo.priority !== '' ? String(wo.priority) : '—'} mono />
      <InfoRow label={t('Due Date')} value={wo?.due_date ? wo.due_date.substring(0, 10) : '—'} mono />
    </Modal>
  );
}

function InfoRow({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  return (
    <View style={styles.infoRow}>
      <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8}>
        {label}
      </Mono>
      {mono ? (
        <Mono size={13} weight="600" color={colors.ink}>
          {value}
        </Mono>
      ) : (
        <Text style={styles.infoValue}>{value}</Text>
      )}
    </View>
  );
}

function InfoStat({ label, value, color }: { label: string; value: string; color: string }) {
  return (
    <View style={styles.infoStat}>
      <Mono size={9} upper color={colors.faint} letterSpacing={1}>
        {label}
      </Mono>
      <Mono size={22} weight="500" color={color} style={{ marginTop: 4 }}>
        {value}
      </Mono>
    </View>
  );
}

function ReportIssueModal({
  wo,
  issueTypes,
  onClose,
}: {
  wo: WorkOrder | null;
  issueTypes: { id: number; name: string }[];
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const createIssue = useCreateIssue();
  const [typeId, setTypeId] = useState<number | null>(null);
  const [title, setTitle] = useState('');
  const [details, setDetails] = useState('');

  const reset = () => {
    setTypeId(null);
    setTitle('');
    setDetails('');
  };

  const submit = () => {
    if (!wo || typeId == null || !title.trim()) return;
    const description = [title.trim(), details.trim()].filter(Boolean).join('\n\n');
    createIssue.mutate(
      { issue_type_id: typeId, work_order_id: wo.id, description },
      {
        onSuccess: () => {
          reset();
          onClose();
        },
      },
    );
  };

  return (
    <Modal
      open={wo != null}
      onClose={() => { reset(); onClose(); }}
      title={t('Report Issue')}
      subtitle={wo?.order_no}
      closeLabel={t('Cancel')}
      footer={
        <>
          <Button variant="secondary" onPress={() => { reset(); onClose(); }}>
            {t('Cancel')}
          </Button>
          <Button variant="danger" onPress={submit} disabled={typeId == null || !title.trim()} loading={createIssue.isPending}>
            {t('Submit Report')}
          </Button>
        </>
      }>
      <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8}>
        {t('Type')} *
      </Mono>
      <View style={styles.typeGrid}>
        {issueTypes.map((type) => {
          const selected = typeId === type.id;
          return (
            <Pressable
              key={type.id}
              onPress={() => {
                setTypeId(type.id);
                if (!title.trim()) setTitle(type.name);
              }}
              style={[styles.typeChip, selected && styles.typeChipActive]}>
              <Text style={styles.typeChipText}>{type.name}</Text>
            </Pressable>
          );
        })}
      </View>

      <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={{ marginTop: 14 }}>
        {t('Title')} *
      </Mono>
      <TextInput value={title} onChangeText={setTitle} maxLength={255} style={styles.input} placeholderTextColor={colors.faint} />

      <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={{ marginTop: 14 }}>
        {t('Details')} {t('(optional)')}
      </Mono>
      <TextInput
        value={details}
        onChangeText={setDetails}
        maxLength={2000}
        multiline
        style={[styles.input, styles.textarea]}
        placeholderTextColor={colors.faint}
      />
    </Modal>
  );
}

// ─── small table primitives ────────────────────────────────────────────────────

function Th({ w, children }: { w: number; children: React.ReactNode }) {
  return (
    <View style={[styles.th, { width: w }]}>
      <Mono size={9} upper color={colors.faint} letterSpacing={1}>
        {children}
      </Mono>
    </View>
  );
}

function Td({ w, children }: { w: number; children: React.ReactNode }) {
  return <View style={[styles.td, { width: w }]}>{children}</View>;
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 48 },

  headerRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 12, marginBottom: 16 },
  h1: { flex: 1, fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  headerControls: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  segment: {
    flexDirection: 'row',
    gap: 3,
    padding: 3,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  segBtn: { paddingVertical: 7, paddingHorizontal: 12, borderRadius: 6 },
  segBtnActive: { backgroundColor: colors.ink },
  segText: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.muted },
  segTextActive: { color: colors.onInk },
  changeLine: {
    paddingVertical: 9,
    paddingHorizontal: 14,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  changeLineText: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },

  machinePanel: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.sm,
    padding: 12,
    marginBottom: 16,
  },
  machineRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  machineTile: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderWidth: 1,
    borderColor: colors.line2,
    borderRadius: radius.sm,
    paddingVertical: 6,
    paddingHorizontal: 10,
  },
  machineDot: { width: 8, height: 8, borderRadius: 4 },
  machineName: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  machineDropdown: { minWidth: 130 },

  search: {
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.sm,
    paddingVertical: 10,
    paddingHorizontal: 12,
    fontSize: 13,
    fontFamily: fonts.sans.native.regular,
    color: colors.ink,
    backgroundColor: colors.bg,
    marginBottom: 16,
  },

  tableCard: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    overflow: 'hidden',
  },
  tHeadRow: { flexDirection: 'row', backgroundColor: colors.panel, borderBottomWidth: 1, borderBottomColor: colors.line2 },
  th: { paddingVertical: 12, paddingHorizontal: 10, justifyContent: 'center' },
  tRow: {
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line2,
  },
  td: { paddingVertical: 10, paddingHorizontal: 10, justifyContent: 'center' },
  cellText: { fontSize: 13.5, fontFamily: fonts.sans.native.medium, color: colors.ink },
  remaining: { alignSelf: 'flex-start', paddingVertical: 4, paddingHorizontal: 10, borderRadius: radius.sm },
  remainingOpen: { backgroundColor: colors.accent },
  remainingDone: { backgroundColor: colors.runningBg },
  rowActions: { flexDirection: 'row', gap: 6 },
  iconBtn: { width: 36, height: 36, borderRadius: radius.sm, alignItems: 'center', justifyContent: 'center' },
  iconGlyph: { fontSize: 17, fontFamily: fonts.sans.native.semibold },
  iconGlyphLight: { fontSize: 18, fontFamily: fonts.sans.native.semibold, color: '#fff' },

  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line2,
    paddingBottom: 8,
    marginBottom: 8,
  },
  infoValue: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  infoTriple: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 8 },
  infoStat: { alignItems: 'center', flex: 1 },

  input: {
    marginTop: 6,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.sm,
    paddingVertical: 10,
    paddingHorizontal: 12,
    fontSize: 14,
    fontFamily: fonts.sans.native.regular,
    color: colors.ink,
    backgroundColor: colors.bg,
  },
  textarea: { minHeight: 76, textAlignVertical: 'top' },
  typeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 8 },
  typeChip: { minWidth: '47%', flexGrow: 1, borderWidth: 1, borderColor: colors.line, borderRadius: radius.sm, padding: 12 },
  typeChipActive: { borderColor: colors.accent, backgroundColor: colors.selectedRow },
  typeChipText: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
