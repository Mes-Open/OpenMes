/**
 * Operator Work Order Queue — native 1:1 port of the web operator Queue page
 * (backend/resources/js/Pages/operator/Queue.jsx). Rendered at the root-level
 * /operator/queue route (outside the drawer) under the OperatorTopBar chrome, so
 * operators never see the admin sidebar.
 *
 * Mirrors the web: h1 + "Line: {name}" subtitle, a controls row (Queue/Workstation
 * toggle, Table/Cards toggle, Change Line), a downtime bar (Report / Stop), a
 * workstation filter with a "Ready at {ws}" queue, an Active Work Orders table
 * (or cards) and a Recently Completed DataTable (or cards). Report Issue and
 * Report Downtime open modals that POST via REST.
 *
 * Data comes from REST (the web uses synced collections): work orders for the line
 * (with batches.steps), line statuses, issue types, downtime reasons + active
 * downtime. Gaps vs. web (no REST endpoint): the board-status badge is read-only
 * (no line-status write / done-qty modal), and there is no per-operation tracking
 * badge. See the task report.
 */
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { format, isValid, parseISO } from 'date-fns';
import { useTranslation } from 'react-i18next';

import { Button, Dropdown, Modal, ProgressBar, colors, fonts, radius } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { OperatorTopBar } from '@/components/operator/OperatorTopBar';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useActiveDowntime, useDowntimeReasons, useStartDowntime, useStopDowntime } from '@/hooks/queries/useDowntime';
import { useCreateIssue, useIssueTypes } from '@/hooks/queries/useIssues';
import { useWorkstations } from '@/hooks/queries/useLines';
import { useLineStatuses } from '@/hooks/queries/useOrgStructure';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { useAuthStore } from '@/stores/authStore';
import type { LineStatus } from '@/api/orgStructure';
import type { WorkOrder } from '@/types/api';

// ─── helpers ─────────────────────────────────────────────────────────────────

const DONE_STATUSES = ['DONE', 'CANCELLED', 'REJECTED'];

function fmtQty(v: number | string | null | undefined, decimals = 0): string {
  const n = parseFloat(String(v ?? 0));
  if (Number.isNaN(n)) return '0';
  return decimals > 0 ? n.toFixed(decimals) : String(Math.round(n));
}

function fmtDate(dateStr: string | null | undefined, form: 'short' | 'long' = 'short'): string {
  if (!dateStr) return '—';
  const d = parseISO(dateStr.slice(0, 10));
  if (!isValid(d)) return '—';
  return form === 'short' ? format(d, 'dd MMM') : format(d, 'dd MMM yyyy, HH:mm');
}

function hexToRgba(hex: string | null | undefined, alpha: number): string | null {
  if (!hex || hex.length !== 7) return null;
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}

// Web operator Queue's WoStatusBadge labels — 'Pending', 'In Progress', … —
// instead of the mobile StatusPill's default renames ('Not started').
const WO_QUEUE_LABEL: Record<string, string> = {
  PENDING: 'Pending',
  ACCEPTED: 'Accepted',
  IN_PROGRESS: 'In Progress',
  PAUSED: 'Paused',
  ON_HOLD: 'On Hold',
  BLOCKED: 'Blocked',
  DONE: 'Done',
  CANCELLED: 'Cancelled',
  REJECTED: 'Rejected',
};

function boardStatusFor(wo: WorkOrder, lineStatuses: LineStatus[]): LineStatus | null {
  if (wo.line_status_id == null) return null;
  return lineStatuses.find((s) => s.id === wo.line_status_id) ?? null;
}

/** A WO is "ready" at a workstation when a batch has a PENDING/IN_PROGRESS step there. */
function currentStepAt(wo: WorkOrder, workstationId: number) {
  for (const b of wo.batches ?? []) {
    const step = (b.steps ?? []).find(
      (s) =>
        s.workstation_id != null &&
        s.workstation_id === workstationId &&
        (s.status === 'PENDING' || s.status === 'IN_PROGRESS'),
    );
    if (step) return { batch: b, step };
  }
  return null;
}

// ─── screen ──────────────────────────────────────────────────────────────────

export function OperatorQueueScreen() {
  const { t } = useTranslation();
  const router = useRouter();

  const user = useAuthStore((s) => s.user);
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const activeWorkstationId = useAuthStore((s) => s.activeWorkstationId);
  const setActiveWorkstationId = useAuthStore((s) => s.setActiveWorkstationId);
  const line = (user?.lines ?? []).find((l) => l.id === activeLineId) ?? null;

  const [view, setView] = useState<'table' | 'cards'>('table');

  const woQuery = useWorkOrders({ line_id: activeLineId ?? undefined }, { refetchInterval: 30_000 });
  const lineStatusesQuery = useLineStatuses(activeLineId ?? undefined);
  const issueTypesQuery = useIssueTypes();
  const workstationsQuery = useWorkstations(activeLineId ?? undefined);
  const downtimeReasonsQuery = useDowntimeReasons();
  const activeDowntimeQuery = useActiveDowntime(activeLineId ?? undefined);
  const stopDowntime = useStopDowntime();

  const lineStatuses = lineStatusesQuery.data ?? [];
  const issueTypes = issueTypesQuery.data ?? [];
  const workstations = workstationsQuery.data ?? [];
  const downtimeReasons = downtimeReasonsQuery.data ?? [];
  const activeDowntime = activeDowntimeQuery.data ?? null;

  const activeWorkOrders = useMemo(
    () => (woQuery.data ?? []).filter((wo) => !DONE_STATUSES.includes(wo.status)),
    [woQuery.data],
  );
  const completedWorkOrders = useMemo(
    () =>
      (woQuery.data ?? [])
        .filter((wo) => wo.status === 'DONE')
        .sort((a, b) => (b.completed_at ?? '').localeCompare(a.completed_at ?? ''))
        .slice(0, 20),
    [woQuery.data],
  );

  const selectedWs = activeWorkstationId != null ? workstations.find((w) => w.id === activeWorkstationId) ?? null : null;
  const workstationQueue = useMemo(() => {
    if (!selectedWs) return [];
    return activeWorkOrders.filter((wo) => currentStepAt(wo, selectedWs.id) != null);
  }, [activeWorkOrders, selectedWs]);

  // Modals
  const [reportWo, setReportWo] = useState<WorkOrder | null>(null);
  const [downtimeOpen, setDowntimeOpen] = useState(false);

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
        {/* ── Header ── */}
        <View style={styles.headerRow}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={styles.h1}>{t('Work Order Queue')}</Text>
            <Text style={styles.subtitle}>
              {t('Line')}: {line?.name ?? '—'}
              {selectedWs ? <Text style={styles.subtitleWs}> / {selectedWs.name}</Text> : null}
            </Text>
          </View>
        </View>

        {/* ── Controls row ── */}
        <View style={styles.controls}>
          {/* Queue / Workstation toggle (duplicates the top bar, like web) */}
          <View style={styles.segment}>
            <View style={[styles.segBtn, styles.segBtnActive]}>
              <Text style={[styles.segText, styles.segTextActive]}>{t('Queue')}</Text>
            </View>
            <Pressable style={styles.segBtn} onPress={() => router.replace('/operator/workstation' as never)}>
              <Text style={styles.segText}>{t('Workstation')}</Text>
            </Pressable>
          </View>

          {/* Table / Cards toggle */}
          <View style={styles.segment}>
            <Pressable
              style={[styles.segBtn, view === 'table' && styles.segBtnActive]}
              onPress={() => setView('table')}>
              <Text style={[styles.segText, view === 'table' && styles.segTextActive]}>{t('Table')}</Text>
            </Pressable>
            <Pressable
              style={[styles.segBtn, view === 'cards' && styles.segBtnActive]}
              onPress={() => setView('cards')}>
              <Text style={[styles.segText, view === 'cards' && styles.segTextActive]}>{t('Cards')}</Text>
            </Pressable>
          </View>

          <Pressable style={styles.changeLine} onPress={() => router.push('/select-line' as never)}>
            <Text style={styles.changeLineText}>{t('Change Line')}</Text>
          </Pressable>
        </View>

        {/* ── Downtime bar ── */}
        {activeDowntime ? (
          <View style={styles.downtimeActive}>
            <View style={styles.downtimeDot} />
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={styles.downtimeText} numberOfLines={1}>
                {t('Downtime in progress')} — {activeDowntime.reason?.name ?? ''}
              </Text>
              <Mono size={10} color={colors.blocked}>
                {t('since')} {fmtDate(activeDowntime.started_at, 'long')}
              </Mono>
            </View>
            <Button
              variant="primary"
              size="sm"
              onPress={() => stopDowntime.mutate(activeDowntime.id)}
              loading={stopDowntime.isPending}>
              {t('Stop Downtime')}
            </Button>
          </View>
        ) : downtimeReasons.length > 0 ? (
          <Pressable style={styles.downtimeBtn} onPress={() => setDowntimeOpen(true)}>
            <Text style={styles.downtimeBtnText}>{t('Report Downtime')}</Text>
          </Pressable>
        ) : null}

        {/* ── Workstation filter ── */}
        {workstations.length > 0 ? (
          <View style={styles.filterRow}>
            <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8}>
              {t('Workstation filter')}:
            </Mono>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
              <FilterChip
                label={t('All')}
                active={!selectedWs}
                onPress={() => setActiveWorkstationId(null)}
              />
              {workstations.map((ws) => {
                const active = selectedWs?.id === ws.id;
                const count = active ? workstationQueue.length : 0;
                return (
                  <FilterChip
                    key={ws.id}
                    label={ws.name}
                    count={active && count > 0 ? count : undefined}
                    active={active}
                    onPress={() => setActiveWorkstationId(ws.id)}
                  />
                );
              })}
            </ScrollView>
          </View>
        ) : null}

        {/* ── Ready at {ws} ── */}
        {selectedWs ? (
          <View style={styles.section}>
            <SectionHeading title={`${t('Ready at')} ${selectedWs.name}`} count={workstationQueue.length} />
            {workstationQueue.length === 0 ? (
              <View style={styles.emptyCard}>
                <Text style={styles.emptyText}>
                  {t('No work orders currently waiting at {{name}}', { name: selectedWs.name })}
                </Text>
              </View>
            ) : (
              <View style={styles.cardsGrid}>
                {workstationQueue.map((wo) => {
                  const cur = currentStepAt(wo, selectedWs.id);
                  const inProgress = cur?.step.status === 'IN_PROGRESS';
                  return (
                    <Pressable
                      key={wo.id}
                      style={[styles.readyCard, { borderLeftColor: inProgress ? colors.running : colors.accent }]}
                      onPress={() => router.push(`/work-orders/${wo.id}` as never)}>
                      <View style={styles.readyHead}>
                        <Mono size={13} weight="600" color={colors.ink}>
                          {wo.order_no}
                        </Mono>
                        <StatusPill
                          status={inProgress ? 'IN_PROGRESS' : 'PENDING'}
                          label={inProgress ? t('In Progress') : t('Ready')}
                        />
                      </View>
                      <Text style={styles.readyProduct}>{wo.product_type?.name ?? '-'}</Text>
                      {cur?.step ? (
                        <Text style={styles.readyStep}>
                          {t('Step')} {cur.step.sequence ?? ''}: {cur.step.name}
                        </Text>
                      ) : null}
                      <Mono size={10} upper color={colors.faint} letterSpacing={0.6} style={{ marginTop: 4 }}>
                        {t('Qty')}: {fmtQty(wo.planned_qty)} · {t('Batch')} #{cur?.batch.batch_number ?? ''}
                      </Mono>
                    </Pressable>
                  );
                })}
              </View>
            )}
          </View>
        ) : null}

        {/* ── Active Work Orders ── */}
        <View style={styles.section}>
          <SectionHeading title={t('Active Work Orders')} count={activeWorkOrders.length} />
          {activeWorkOrders.length === 0 ? (
            <EmptyState
              title={t('No active work orders')}
              subtitle={t('There are no work orders currently in progress on this line.')}
            />
          ) : view === 'table' ? (
            <ActiveTable
              rows={activeWorkOrders}
              lineStatuses={lineStatuses}
              onOpen={(wo) => router.push(`/work-orders/${wo.id}` as never)}
              onReport={setReportWo}
            />
          ) : (
            <View style={styles.cardsGrid}>
              {activeWorkOrders.map((wo) => (
                <ActiveCard
                  key={wo.id}
                  wo={wo}
                  lineStatuses={lineStatuses}
                  onOpen={() => router.push(`/work-orders/${wo.id}` as never)}
                  onReport={() => setReportWo(wo)}
                />
              ))}
            </View>
          )}
        </View>

        {/* ── Recently Completed ── */}
        <View style={styles.section}>
          <SectionHeading title={t('Recently Completed')} count={completedWorkOrders.length} />
          {completedWorkOrders.length === 0 ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyText}>{t('No recently completed work orders')}</Text>
            </View>
          ) : view === 'table' ? (
            <DataTable<WorkOrder>
              data={completedWorkOrders}
              searchable={false}
              columnToggle={false}
              paginated={false}
              onRowPress={(wo) => router.push(`/work-orders/${wo.id}` as never)}
              columns={[
                {
                  key: 'order_no',
                  label: t('Order No'),
                  width: 130,
                  render: (wo) => <Mono size={13} weight="600" color={colors.muted}>{wo.order_no}</Mono>,
                },
                { key: 'product', label: t('Product'), flex: 1.2, render: (wo) => wo.product_type?.name ?? '—' },
                {
                  key: 'produced',
                  label: t('Produced'),
                  width: 100,
                  render: (wo) => <Mono size={13} color={colors.ink}>{fmtQty(wo.produced_qty)}</Mono>,
                },
                {
                  key: 'completed_at',
                  label: t('Completed at'),
                  width: 150,
                  render: (wo) => <Mono size={12} color={colors.faint}>{fmtDate(wo.completed_at, 'long')}</Mono>,
                },
              ]}
            />
          ) : (
            <View style={styles.cardsGrid}>
              {completedWorkOrders.map((wo) => (
                <Pressable
                  key={wo.id}
                  style={styles.completedCard}
                  onPress={() => router.push(`/work-orders/${wo.id}` as never)}>
                  <View style={styles.readyHead}>
                    <Mono size={15} weight="600" color={colors.ink}>{wo.order_no}</Mono>
                    <StatusPill status="DONE" label={t('Completed')} />
                  </View>
                  <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={{ marginTop: 8 }}>
                    {t('Product')}
                  </Mono>
                  <Text style={styles.readyProduct}>{wo.product_type?.name ?? '—'}</Text>
                  <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={{ marginTop: 8 }}>
                    {t('Completed')}
                  </Mono>
                  <Mono size={13} color={colors.ink}>{fmtQty(wo.produced_qty, 2)}</Mono>
                  {wo.completed_at ? (
                    <Mono size={11} color={colors.muted} style={{ marginTop: 8 }}>
                      {fmtDate(wo.completed_at, 'long')}
                    </Mono>
                  ) : null}
                </Pressable>
              ))}
            </View>
          )}
        </View>
      </ScrollView>

      {/* ── Modals ── */}
      <ReportIssueModal
        wo={reportWo}
        issueTypes={issueTypes}
        onClose={() => setReportWo(null)}
      />
      <ReportDowntimeModal
        open={downtimeOpen}
        lineId={activeLineId ?? undefined}
        workstationId={activeWorkstationId ?? undefined}
        reasons={downtimeReasons}
        onClose={() => setDowntimeOpen(false)}
      />
    </View>
  );
}

// ─── sub-components ────────────────────────────────────────────────────────────

function SectionHeading({ title, count }: { title: string; count: number }) {
  return (
    <View style={styles.sectionHead}>
      <Text style={styles.h2}>{title}</Text>
      <Mono size={12} color={colors.faint}>
        ({count})
      </Mono>
    </View>
  );
}

function FilterChip({
  label,
  count,
  active,
  onPress,
}: {
  label: string;
  count?: number;
  active: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected: active }}
      style={[styles.chip, active && styles.chipActive]}>
      <Text style={[styles.chipText, active && styles.chipTextActive]}>{label}</Text>
      {count != null ? (
        <View style={styles.chipCount}>
          <Mono size={10} weight="600" color={colors.ink}>
            {count}
          </Mono>
        </View>
      ) : null}
    </Pressable>
  );
}

function BoardStatusBadge({ status }: { status: LineStatus | null }) {
  if (!status) return <Text style={styles.dash}>—</Text>;
  return (
    <View style={[styles.boardBadge, { backgroundColor: status.color ?? colors.faint }]}>
      <Mono size={10} weight="600" color="#fff" letterSpacing={0.4}>
        {status.name}
      </Mono>
    </View>
  );
}

// Active WO table — horizontally scrollable hand table matching the web columns.
function ActiveTable({
  rows,
  lineStatuses,
  onOpen,
  onReport,
}: {
  rows: WorkOrder[];
  lineStatuses: LineStatus[];
  onOpen: (wo: WorkOrder) => void;
  onReport: (wo: WorkOrder) => void;
}) {
  const { t } = useTranslation();
  const showBoard = lineStatuses.length > 0;
  return (
    <View style={styles.tableCard}>
      <ScrollView horizontal showsHorizontalScrollIndicator>
        <View>
          {/* Header */}
          <View style={styles.tHeadRow}>
            <Th w={120}>{t('Order No')}</Th>
            <Th w={110}>{t('Status')}</Th>
            {showBoard ? <Th w={120}>{t('Board Status')}</Th> : null}
            <Th w={150}>{t('Product')}</Th>
            <Th w={150}>{t('Qty (done / planned)')}</Th>
            <Th w={70}>{t('Batches')}</Th>
            <Th w={86}>{t('Priority')}</Th>
            <Th w={80}>{t('Due')}</Th>
            <Th w={110}>{t('Actions')}</Th>
            <Th w={40}> </Th>
          </View>
          {/* Rows */}
          {rows.map((wo) => {
            const ls = boardStatusFor(wo, lineStatuses);
            const planned = parseFloat(String(wo.planned_qty ?? 0)) || 0;
            const produced = parseFloat(String(wo.produced_qty ?? 0)) || 0;
            const pct = planned > 0 ? Math.min((produced / planned) * 100, 100) : 0;
            const tint = ls?.color ? hexToRgba(ls.color, 0.12) : undefined;
            return (
              <Pressable
                key={wo.id}
                onPress={() => onOpen(wo)}
                style={[
                  styles.tRow,
                  { backgroundColor: tint ?? 'transparent', borderLeftColor: ls?.color ?? 'transparent' },
                ]}>
                <Td w={120}>
                  <Mono size={13} weight="600" color={colors.ink}>
                    {wo.order_no}
                  </Mono>
                </Td>
                <Td w={110}>
                  <StatusPill status={wo.status} label={WO_QUEUE_LABEL[wo.status] ?? wo.status} />
                </Td>
                {showBoard ? (
                  <Td w={120}>
                    <BoardStatusBadge status={ls} />
                  </Td>
                ) : null}
                <Td w={150}>
                  <Text style={styles.cellText} numberOfLines={2}>
                    {wo.product_type?.name ?? '—'}
                  </Text>
                </Td>
                <Td w={150}>
                  <Mono size={13} color={colors.ink}>
                    {fmtQty(produced)} / {fmtQty(planned)}
                    {planned > 0 ? <Mono size={10} color={colors.faint}>{`  (${fmtQty(pct)}%)`}</Mono> : null}
                  </Mono>
                  {planned > 0 ? <ProgressBar value={pct} style={styles.rowBar} /> : null}
                </Td>
                <Td w={70}>
                  <Mono size={13} color={colors.muted}>
                    {wo.batches ? wo.batches.length : 0}
                  </Mono>
                </Td>
                <Td w={70}>
                  <Mono size={13} color={colors.muted}>
                    {wo.priority != null && wo.priority !== '' ? String(wo.priority) : '—'}
                  </Mono>
                </Td>
                <Td w={80}>
                  <Mono size={12} color={colors.muted}>
                    {fmtDate(wo.due_date, 'short')}
                  </Mono>
                </Td>
                <Td w={110}>
                  <Button variant="danger" size="sm" onPress={() => onReport(wo)}>
                    {t('Report')}
                  </Button>
                </Td>
                <Td w={40}>
                  <Text style={styles.chevron}>›</Text>
                </Td>
              </Pressable>
            );
          })}
        </View>
      </ScrollView>
    </View>
  );
}

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

function ActiveCard({
  wo,
  lineStatuses,
  onOpen,
  onReport,
}: {
  wo: WorkOrder;
  lineStatuses: LineStatus[];
  onOpen: () => void;
  onReport: () => void;
}) {
  const { t } = useTranslation();
  const ls = boardStatusFor(wo, lineStatuses);
  const planned = parseFloat(String(wo.planned_qty ?? 0)) || 0;
  const produced = parseFloat(String(wo.produced_qty ?? 0)) || 0;
  const pct = planned > 0 ? Math.min((produced / planned) * 100, 100) : 0;

  return (
    <View style={styles.activeCard}>
      <Pressable onPress={onOpen}>
        <View style={styles.readyHead}>
          <Mono size={15} weight="600" color={colors.ink}>
            {wo.order_no}
          </Mono>
          <StatusPill status={wo.status} label={WO_QUEUE_LABEL[wo.status] ?? wo.status} />
        </View>

        {lineStatuses.length > 0 ? (
          <View style={{ marginTop: 10 }}>
            <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8}>
              {t('Board Status')}
            </Mono>
            <View style={{ marginTop: 4, alignSelf: 'flex-start' }}>
              <BoardStatusBadge status={ls} />
            </View>
          </View>
        ) : null}

        <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={{ marginTop: 10 }}>
          {t('Product')}
        </Mono>
        <Text style={styles.cardProduct}>{wo.product_type?.name ?? '—'}</Text>

        <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={{ marginTop: 10 }}>
          {t('Quantity')}
        </Mono>
        <Mono size={15} color={colors.ink}>
          {fmtQty(produced, 2)} / {fmtQty(planned, 2)}
          {planned > 0 ? <Mono size={12} color={colors.faint}>{`  (${fmtQty(pct, 1)}%)`}</Mono> : null}
        </Mono>
        {planned > 0 ? <ProgressBar value={pct} style={{ marginTop: 8 }} /> : null}

        <View style={styles.cardFooter}>
          <Text style={styles.cardMeta}>
            {t('Priority')}: {wo.priority != null && wo.priority !== '' ? String(wo.priority) : '—'}
          </Text>
          {wo.due_date ? (
            <Text style={styles.cardMeta}>
              {t('Due')}: {fmtDate(wo.due_date, 'short')}
            </Text>
          ) : null}
        </View>
      </Pressable>

      <View style={styles.cardActions}>
        <Button variant="danger" size="sm" onPress={onReport}>
          {t('Report')}
        </Button>
        <Pressable onPress={onOpen} style={styles.viewDetails}>
          <Text style={styles.viewDetailsText}>{t('View Details')} ›</Text>
        </Pressable>
      </View>
    </View>
  );
}

// ─── Report Issue modal ────────────────────────────────────────────────────────

function ReportIssueModal({
  wo,
  issueTypes,
  onClose,
}: {
  wo: WorkOrder | null;
  issueTypes: { id: number; name: string; is_blocking?: boolean }[];
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
      onClose={() => {
        reset();
        onClose();
      }}
      title={t('Report Issue')}
      subtitle={wo?.order_no}
      closeLabel={t('Cancel')}
      footer={
        <>
          <Button variant="secondary" onPress={() => { reset(); onClose(); }}>
            {t('Cancel')}
          </Button>
          <Button
            variant="danger"
            onPress={submit}
            disabled={typeId == null || !title.trim()}
            loading={createIssue.isPending}>
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
              <Text style={[styles.typeChipText, selected && styles.typeChipTextActive]}>{type.name}</Text>
              {type.is_blocking ? (
                <Mono size={9} color={colors.blocked}>
                  ⚠ {t('blocking')}
                </Mono>
              ) : null}
            </Pressable>
          );
        })}
      </View>

      <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={{ marginTop: 14 }}>
        {t('Title')} *
      </Mono>
      <TextInput
        value={title}
        onChangeText={setTitle}
        placeholder={t('Brief summary…')}
        placeholderTextColor={colors.faint}
        maxLength={255}
        style={styles.input}
      />

      <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={{ marginTop: 14 }}>
        {t('Details')} {t('(optional)')}
      </Mono>
      <TextInput
        value={details}
        onChangeText={setDetails}
        placeholder={t('Additional details…')}
        placeholderTextColor={colors.faint}
        maxLength={2000}
        multiline
        style={[styles.input, styles.textarea]}
      />
    </Modal>
  );
}

// ─── Report Downtime modal ─────────────────────────────────────────────────────

function ReportDowntimeModal({
  open,
  lineId,
  workstationId,
  reasons,
  onClose,
}: {
  open: boolean;
  lineId?: number;
  workstationId?: number;
  reasons: { id: number; name: string }[];
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const startDowntime = useStartDowntime();
  const [reasonId, setReasonId] = useState('');
  const [notes, setNotes] = useState('');

  const reset = () => {
    setReasonId('');
    setNotes('');
  };

  const submit = () => {
    if (!lineId || !reasonId) return;
    startDowntime.mutate(
      {
        line_id: lineId,
        workstation_id: workstationId ?? null,
        downtime_reason_id: Number(reasonId),
        notes: notes.trim() || undefined,
      },
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
      open={open}
      onClose={() => { reset(); onClose(); }}
      title={t('Report Downtime')}
      subtitle={t('Record a production stoppage for this line')}
      closeLabel={t('Cancel')}
      footer={
        <>
          <Button variant="secondary" onPress={() => { reset(); onClose(); }}>
            {t('Cancel')}
          </Button>
          <Button variant="danger" onPress={submit} disabled={!reasonId} loading={startDowntime.isPending}>
            {t('Start Downtime')}
          </Button>
        </>
      }>
      <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8}>
        {t('Reason')} *
      </Mono>
      <Dropdown
        value={reasonId}
        onChange={(v) => setReasonId(v as string)}
        placeholder={t('— select reason —')}
        options={reasons.map((r) => ({ value: String(r.id), label: r.name }))}
        style={{ marginTop: 6 }}
      />

      <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={{ marginTop: 14 }}>
        {t('Notes')} {t('(optional)')}
      </Mono>
      <TextInput
        value={notes}
        onChangeText={setNotes}
        placeholder={t('Additional context…')}
        placeholderTextColor={colors.faint}
        maxLength={2000}
        multiline
        style={[styles.input, styles.textarea]}
      />
    </Modal>
  );
}

// ─── styles ────────────────────────────────────────────────────────────────────

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 48, maxWidth: 1100, width: '100%', alignSelf: 'center' },

  headerRow: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 16 },
  h1: { fontSize: 26, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.5 },
  subtitle: { fontSize: 13, fontFamily: fonts.sans.native.regular, color: colors.muted, marginTop: 6 },
  subtitleWs: { fontFamily: fonts.mono.native.medium, color: colors.accent },

  controls: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 10, marginBottom: 16 },
  segment: {
    flexDirection: 'row',
    gap: 3,
    padding: 3,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.bg,
  },
  segBtn: { paddingVertical: 8, paddingHorizontal: 14, borderRadius: 6 },
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

  downtimeActive: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.blocked,
    backgroundColor: colors.blockedBg,
    marginBottom: 16,
  },
  downtimeDot: { width: 10, height: 10, borderRadius: 5, backgroundColor: colors.blocked },
  downtimeText: { fontSize: 13, fontFamily: fonts.sans.native.semibold, color: colors.blocked },
  downtimeBtn: {
    alignSelf: 'flex-end',
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.downtime,
    backgroundColor: colors.downtimeBg,
    marginBottom: 16,
  },
  downtimeBtnText: { fontSize: 13, fontFamily: fonts.sans.native.semibold, color: colors.downtime },

  filterRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 16 },
  chips: { flexDirection: 'row', gap: 8, paddingRight: 8 },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: radius.sm,
    backgroundColor: colors.chip,
  },
  chipActive: { backgroundColor: colors.ink },
  chipText: { fontSize: 12, fontFamily: fonts.sans.native.medium, color: colors.muted },
  chipTextActive: { color: colors.onInk },
  chipCount: {
    minWidth: 20,
    height: 20,
    paddingHorizontal: 5,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.card,
  },

  section: { marginBottom: 24 },
  sectionHead: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12 },
  h2: { fontSize: 17, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.2 },

  emptyCard: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 24,
    alignItems: 'center',
  },
  emptyText: { fontSize: 13, fontFamily: fonts.sans.native.regular, color: colors.muted, textAlign: 'center' },

  cardsGrid: { gap: 14 },

  readyCard: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderLeftWidth: 3,
    borderRadius: radius.md,
    padding: 14,
  },
  readyHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  readyProduct: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink, marginTop: 2 },
  readyStep: { fontSize: 12, fontFamily: fonts.sans.native.medium, color: colors.accent, marginTop: 6 },

  completedCard: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 18,
    opacity: 0.85,
  },

  activeCard: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 18,
  },
  cardProduct: { fontSize: 15, fontFamily: fonts.sans.native.medium, color: colors.ink, marginTop: 2 },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.line2,
    paddingTop: 12,
    marginTop: 12,
  },
  cardMeta: { fontSize: 13, fontFamily: fonts.sans.native.regular, color: colors.muted },
  cardActions: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 14 },
  viewDetails: { paddingVertical: 6 },
  viewDetailsText: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.accent },

  // Table
  tableCard: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    overflow: 'hidden',
  },
  tHeadRow: { flexDirection: 'row', backgroundColor: colors.panel, borderBottomWidth: 1, borderBottomColor: colors.line2 },
  th: { paddingVertical: 12, paddingHorizontal: 12, justifyContent: 'center' },
  tRow: {
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line2,
    borderLeftWidth: 3,
  },
  td: { paddingVertical: 12, paddingHorizontal: 12, justifyContent: 'center' },
  cellText: { fontSize: 13.5, fontFamily: fonts.sans.native.medium, color: colors.ink },
  rowBar: { marginTop: 6, width: 96 },
  chevron: { fontSize: 18, fontFamily: fonts.mono.native.regular, color: colors.faint },
  dash: { fontSize: 13, color: colors.faintest },

  boardBadge: { alignSelf: 'flex-start', paddingVertical: 4, paddingHorizontal: 10, borderRadius: 20 },

  // Modal inputs
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
  typeChip: {
    minWidth: '47%',
    flexGrow: 1,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.sm,
    padding: 12,
  },
  typeChipActive: { borderColor: colors.accent, backgroundColor: colors.selectedRow },
  typeChipText: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  typeChipTextActive: { color: colors.ink },
});
