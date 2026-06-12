import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { TabletShell } from '@/components/tablet/TabletShell';
import { DowntimeBanner } from '@/components/operator/DowntimeBanner';
import { MaterialAllocationModal } from '@/components/operator/MaterialAllocationModal';
import { LiveDot } from '@/components/ui/LiveDot';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useBatch } from '@/hooks/queries/useBatch';
import { useStartStep, useCompleteStep } from '@/hooks/mutations/batchSteps';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { useScheduleRealtime } from '@/hooks/useScheduleRealtime';
import { useAuthStore } from '@/stores/authStore';
import { isWorkOrderOverdue } from '@/lib/statusLabels';
import type { BatchStep, WorkOrder } from '@/types/api';

const DARK = Colors.dark;

function fmtElapsed(ms: number): string {
  const total = Math.max(0, Math.floor(ms / 1000));
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  if (h > 0) {
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
  }
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function fmtSecPart(ms: number): string {
  const s = Math.floor(ms / 1000) % 60;
  return `:${String(s).padStart(2, '0')}`;
}

export function TabletOperatorTerminal() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const activeWorkstationId = useAuthStore((s) => s.activeWorkstationId);
  const lineName = user?.lines?.find((l) => l.id === activeLineId)?.name;
  const { t } = useTranslation();

  // Live updates on the operator's line — Reverb push wakes the queries the
  // moment a supervisor reschedules or transitions a WO.
  useScheduleRealtime(true);

  const workOrdersQ = useWorkOrders({
    line_id: activeLineId ?? undefined,
    status: ['IN_PROGRESS', 'ACCEPTED', 'PENDING'],
  });

  // Active = first IN_PROGRESS, else first ACCEPTED/PENDING.
  const activeWo: WorkOrder | undefined = useMemo(() => {
    const wos = workOrdersQ.data ?? [];
    return wos.find((w) => w.status === 'IN_PROGRESS') ?? wos[0];
  }, [workOrdersQ.data]);

  // Pick the first non-DONE batch on the active WO.
  const activeBatchId = useMemo(() => {
    const batches = activeWo?.batches ?? [];
    const live = batches.find((b) => b.status === 'IN_PROGRESS' || b.status === 'PENDING');
    return live?.id;
  }, [activeWo]);

  const batchQ = useBatch(activeBatchId);
  const startMutation = useStartStep(activeBatchId);
  const completeMutation = useCompleteStep(activeBatchId);

  // Material allocation confirmation — opens when operator taps Start on a
  // step that will transition a PENDING batch into IN_PROGRESS. Backend
  // allocates server-side; this surfaces the preview first.
  const [allocBatchId, setAllocBatchId] = useState<number | null>(null);
  const pendingStepId = useRef<number | null>(null);

  if (workOrdersQ.isLoading) return <LoadingState />;
  if (workOrdersQ.isError) return <ErrorState error={workOrdersQ.error} onRetry={workOrdersQ.refetch} />;

  if (!activeWo) {
    return (
      <TabletShell
        dark
        eyebrow={lineName ? `${lineName.toUpperCase()} · ${t('No active work order').toUpperCase()}` : t('No active work order').toUpperCase()}
        title={user?.username ?? t('Operator')}>
        <EmptyState
          title={t('No active work order')}
          subtitle={t('Pull a WO from the queue to start a shift.')}
        />
      </TabletShell>
    );
  }

  const batch = batchQ.data;
  const steps: BatchStep[] = batch?.steps ?? [];
  const sortedSteps = steps.slice().sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
  const runningStep =
    sortedSteps.find((s) => s.status === 'IN_PROGRESS') ??
    sortedSteps.find((s) => s.status === 'PENDING') ??
    null;
  const doneCount = sortedSteps.filter((s) => s.status === 'DONE').length;
  const runningCount = sortedSteps.filter((s) => s.status === 'IN_PROGRESS').length;
  const stepIndex = runningStep ? sortedSteps.indexOf(runningStep) + 1 : 0;
  const totalSteps = sortedSteps.length;

  // Counters — derived from latest completed step's produced_qty totals.
  const goodCount = sortedSteps.reduce((acc, s) => Math.max(acc, s.produced_qty ?? 0), 0);
  const plannedQty = Number(activeWo.planned_qty ?? 0);
  // The backend has no scrap counter on BatchStep — we surface a static 0 until
  // scrap reporting lands in the API. TODO(api/scrap): wire a real value.
  const scrapCount = 0;

  return (
    <TabletShell
      dark
      eyebrow={[
        lineName?.toUpperCase(),
        activeWo.product_type?.name?.toUpperCase(),
        user?.username?.toUpperCase(),
      ]
        .filter(Boolean)
        .join(' · ')}
      title={`${activeWo.order_no} — ${activeWo.product_type?.name ?? 'Work order'}`}
      right={
        <>
          <StatusPill status={activeWo.status} dark />
          {isWorkOrderOverdue(activeWo) ? (
            <View style={styles.overdueChip}>
              <Mono size={11} color="#fff" weight="700" letterSpacing={0.6}>
                {t('Overdue').toUpperCase()}
              </Mono>
            </View>
          ) : null}
          <Mono size={13} color={DARK.textMuted} letterSpacing={0.4}>
            {t('BATCH').toUpperCase()} #{batch?.id ?? '—'} · {Math.round(plannedQty)} {t('PCS')}
          </Mono>
          <View style={[styles.stepChip, { backgroundColor: BRAND.amber }]}>
            <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.5}>
              {t('STEP').toUpperCase()} {stepIndex} / {totalSteps}
            </Mono>
          </View>
          <LiveDot dark />
        </>
      }>
      {activeLineId ? (
        <View style={{ marginBottom: 14 }}>
          <DowntimeBanner lineId={activeLineId} />
        </View>
      ) : null}

      <View style={styles.grid3}>
        {/* LEFT — Timer + step */}
        <View style={styles.col}>
          <ActiveStepCard
            step={runningStep}
            onStart={() => {
              if (!runningStep) return;
              // First step on a PENDING batch — confirm material allocation.
              if (batch?.status === 'PENDING') {
                pendingStepId.current = runningStep.id;
                setAllocBatchId(batch.id);
                return;
              }
              startMutation.mutate(runningStep.id, {
                onError: (e: Error) => Alert.alert(t('Start failed'), e.message),
              });
            }}
            onComplete={() =>
              runningStep &&
              completeMutation.mutate(
                { stepId: runningStep.id },
                { onError: (e: Error) => Alert.alert(t('Complete failed'), e.message) },
              )
            }
            startLoading={startMutation.isPending}
            completeLoading={completeMutation.isPending}
          />
        </View>

        {/* CENTER — Counters + process rail */}
        <View style={styles.col}>
          <View style={styles.countersRow}>
            <CounterTile label={t('GOOD')} value={String(goodCount)} target={String(Math.round(plannedQty))} color={DARK.success} />
            <CounterTile label={t('SCRAP')} value={String(scrapCount)} color={DARK.danger} />
          </View>

          <View style={styles.railCard}>
            <View style={styles.railHeader}>
              <Mono size={11} color={DARK.textFaint} letterSpacing={0.8}>
                {t('PROCESS').toUpperCase()} · {totalSteps} {t('STEPS')}
              </Mono>
              <Mono size={11} color={DARK.success}>
                {doneCount} {t('DONE')} · {runningCount} {t('RUNNING')}
              </Mono>
            </View>
            <ScrollView style={{ marginTop: 12, flex: 1 }} showsVerticalScrollIndicator={false}>
              {sortedSteps.length === 0 ? (
                <Mono size={11} color={DARK.textFaint} style={{ textAlign: 'center', padding: 14 }}>
                  {t('No steps').toUpperCase()}
                </Mono>
              ) : (
                sortedSteps.map((s) => (
                  <StepRow
                    key={s.id}
                    step={s}
                    index={s.sequence ?? 0}
                    myWorkstationId={activeWorkstationId}
                  />
                ))
              )}
            </ScrollView>
          </View>
        </View>

        {/* RIGHT — Scan tile + andon + STOP LINE */}
        <View style={styles.col}>
          <Pressable
            onPress={() => router.push('/(drawer)/(tabs)/scan' as never)}
            style={({ pressed }) => [styles.scanTile, { opacity: pressed ? 0.92 : 1 }]}>
            <FontAwesome name="qrcode" size={36} color="#1a1208" />
            <Text style={styles.scanTitle}>{t('Scan part / lot')}</Text>
            <Mono size={12} color="#3a2a0c">{t('Tap or use external scanner')}</Mono>
          </Pressable>

          <View style={styles.andonCard}>
            <Mono size={11} color={DARK.textFaint} letterSpacing={0.8}>{t('ANDON')}</Mono>
            <Text style={styles.andonTitle}>{t('Report an issue')}</Text>
            <View style={styles.andonGrid}>
              <AndonTile color={BRAND.amber} icon="cube" label={t('Material')} onPress={() => router.push('/issues/new' as never)} />
              <AndonTile color="#f97316" icon="wrench" label={t('Tooling')} onPress={() => router.push('/issues/new' as never)} />
              <AndonTile color={DARK.info} icon="shield" label={t('Quality')} onPress={() => router.push('/issues/new' as never)} />
              <AndonTile color="#a78bfa" icon="cog" label={t('Machine')} onPress={() => router.push('/issues/new' as never)} />
            </View>
            <Pressable
              onPress={() =>
                Alert.alert(t('Stop the line?'), t('This will record a blocking issue and pause production.'), [
                  { text: t('Cancel'), style: 'cancel' },
                  {
                    text: t('Stop line'),
                    style: 'destructive',
                    onPress: () => router.push('/issues/new' as never),
                  },
                ])
              }
              style={({ pressed }) => [
                styles.stopBtn,
                { backgroundColor: DARK.danger, opacity: pressed ? 0.92 : 1 },
              ]}>
              <FontAwesome name="exclamation-triangle" size={18} color="#fff" />
              <Text style={styles.stopText}>{t('STOP LINE').toUpperCase()}</Text>
            </Pressable>
          </View>
        </View>
      </View>

      <MaterialAllocationModal
        batchId={allocBatchId}
        confirmLoading={startMutation.isPending}
        onCancel={() => {
          pendingStepId.current = null;
          setAllocBatchId(null);
        }}
        onConfirm={() => {
          const stepId = pendingStepId.current;
          if (stepId == null) return;
          startMutation.mutate(stepId, {
            onSuccess: () => {
              pendingStepId.current = null;
              setAllocBatchId(null);
            },
            onError: (e: Error) => Alert.alert('Start failed', e.message),
          });
        }}
      />
    </TabletShell>
  );
}

function ActiveStepCard({
  step,
  onStart,
  onComplete,
  startLoading,
  completeLoading,
}: {
  step: BatchStep | null;
  onStart: () => void;
  onComplete: () => void;
  startLoading: boolean;
  completeLoading: boolean;
}) {
  const { t } = useTranslation();
  const [tick, setTick] = useState(0);
  const isRunning = step?.status === 'IN_PROGRESS';

  useEffect(() => {
    if (!isRunning) return;
    const id = setInterval(() => setTick((t) => t + 1), 1000);
    return () => clearInterval(id);
  }, [isRunning]);
  void tick;

  const elapsedMs = (() => {
    if (!step?.started_at || !isRunning) return 0;
    try {
      return Math.max(0, Date.now() - new Date(step.started_at).getTime());
    } catch {
      return 0;
    }
  })();

  const targetMin = step?.expected_duration_seconds
    ? Math.round(step.expected_duration_seconds / 60)
    : null;
  const onTrack = targetMin == null || elapsedMs / 60_000 <= targetMin;

  return (
    <View style={styles.heroCard}>
      <Mono size={11} color={onTrack ? BRAND.amber : DARK.danger} letterSpacing={0.8}>
        {t('ELAPSED').toUpperCase()} · {onTrack ? t('On track').toUpperCase() : t('Over target').toUpperCase()}
      </Mono>
      <View style={styles.heroTimeRow}>
        <Text style={styles.heroTimeMain}>
          {step ? fmtElapsed(elapsedMs) : '—:—'}
        </Text>
        <Text style={styles.heroTimeSec}>{step ? fmtSecPart(elapsedMs) : ''}</Text>
      </View>
      {targetMin != null ? (
        <Mono size={12} color={DARK.textMuted} letterSpacing={0.3} style={{ marginTop: 6 }}>
          {t('TARGET').toUpperCase()} {String(Math.floor(targetMin / 60)).padStart(2, '0')}:
          {String(targetMin % 60).padStart(2, '0')}
        </Mono>
      ) : null}

      <View style={{ marginTop: 24 }}>
        <Mono size={11} color={DARK.textFaint} letterSpacing={0.6}>{t('Operation').toUpperCase()}</Mono>
        <Text style={styles.heroOpName}>{step?.name ?? t('No active step')}</Text>
        {step?.instruction ? (
          <Text style={styles.heroInstruction} numberOfLines={4}>
            {step.instruction}
          </Text>
        ) : null}
      </View>

      <View style={{ flex: 1 }} />

      {step ? (
        <View style={styles.heroActions}>
          {step.status === 'PENDING' ? (
            <Pressable
              onPress={onStart}
              disabled={startLoading}
              style={({ pressed }) => [
                styles.heroPrimary,
                { backgroundColor: BRAND.amber, opacity: startLoading ? 0.7 : pressed ? 0.92 : 1 },
              ]}>
              <FontAwesome name="play" size={20} color="#1a1208" />
              <Text style={styles.heroPrimaryText}>{startLoading ? t('Starting…') : t('Start step')}</Text>
            </Pressable>
          ) : (
            <>
              <Pressable
                style={[styles.heroSecondary, { borderColor: DARK.border }]}
                onPress={() => {}}>
                <FontAwesome name="pause" size={20} color={DARK.text} />
              </Pressable>
              <Pressable
                onPress={onComplete}
                disabled={completeLoading}
                style={({ pressed }) => [
                  styles.heroPrimary,
                  {
                    backgroundColor: DARK.success,
                    opacity: completeLoading ? 0.7 : pressed ? 0.92 : 1,
                  },
                ]}>
                <FontAwesome name="check" size={20} color="#0e2718" />
                <Text style={[styles.heroPrimaryText, { color: '#0e2718' }]}>
                  {completeLoading ? t('Completing…') : t('Complete step')}
                </Text>
              </Pressable>
            </>
          )}
        </View>
      ) : null}
    </View>
  );
}

function CounterTile({
  label,
  value,
  target,
  color,
}: {
  label: string;
  value: string;
  target?: string;
  color: string;
}) {
  return (
    <View style={styles.counter}>
      <View style={styles.counterHead}>
        <Mono size={11} color={DARK.textFaint} letterSpacing={0.6}>{label}</Mono>
        {target ? <Mono size={10} color={DARK.textFaint}>/ {target}</Mono> : null}
      </View>
      <Text style={[styles.counterValue, { color }]}>{value}</Text>
    </View>
  );
}

function StepRow({
  step,
  index,
  myWorkstationId,
}: {
  step: BatchStep;
  index: number;
  myWorkstationId: number | null;
}) {
  const isDone = step.status === 'DONE';
  const isRunning = step.status === 'IN_PROGRESS';
  // Workstation routing — dim steps owned by another workstation so the operator
  // sees full context but knows which ones are theirs. Mirrors web commit 7dec2bf.
  const isMine = myWorkstationId == null || step.workstation_id === myWorkstationId;
  const dim = !isMine && !isRunning;
  const dotBg = isDone ? DARK.success : isRunning ? BRAND.amber : '#26262d';
  const elapsed = (() => {
    if (!step.started_at) return '—';
    try {
      return fmtElapsed(Date.now() - new Date(step.started_at).getTime());
    } catch {
      return '—';
    }
  })();
  return (
    <View
      style={[
        styles.stepRow,
        isRunning
          ? { backgroundColor: '#241a08', borderColor: BRAND.amber }
          : { borderColor: 'transparent' },
        dim ? { opacity: 0.45 } : null,
      ]}>
      <View style={[styles.stepDot, { backgroundColor: dotBg }]}>
        {isDone ? (
          <FontAwesome name="check" size={12} color="#0e2718" />
        ) : (
          <Mono
            size={11}
            color={isRunning ? '#1a1208' : DARK.textFaint}
            weight="700">
            {index}
          </Mono>
        )}
      </View>
      <Text
        style={[
          styles.stepName,
          {
            color: isDone ? DARK.textMuted : DARK.text,
            fontWeight: isRunning ? '600' : '400',
            textDecorationLine: isDone ? 'line-through' : 'none',
          },
        ]}
        numberOfLines={1}>
        {step.name}
      </Text>
      <Mono size={12} color={DARK.textFaint}>{isRunning ? elapsed : isDone ? '✓' : '—'}</Mono>
    </View>
  );
}

function AndonTile({
  color,
  icon,
  label,
  onPress,
}: {
  color: string;
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.andonTile,
        { borderColor: DARK.border, opacity: pressed ? 0.7 : 1 },
      ]}>
      <FontAwesome name={icon} size={22} color={color} />
      <Text style={{ color: DARK.text, fontSize: 12, fontWeight: '500', marginTop: 6 }}>
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  stepChip: { paddingVertical: 8, paddingHorizontal: 14, borderRadius: 10 },
  overdueChip: {
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 4,
    backgroundColor: '#dc2626',
    alignSelf: 'center',
  },
  grid3: { flex: 1, flexDirection: 'row', gap: 16 },
  col: { flex: 1, gap: 12 },

  // Hero card
  heroCard: {
    flex: 1,
    backgroundColor: '#1a1a1f',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: DARK.border,
    padding: 22,
  },
  heroTimeRow: { flexDirection: 'row', alignItems: 'flex-end', marginTop: 10 },
  heroTimeMain: {
    fontFamily: MONO,
    fontSize: 88,
    fontWeight: '500',
    color: DARK.text,
    letterSpacing: -3,
    lineHeight: 92,
  },
  heroTimeSec: {
    fontFamily: MONO,
    fontSize: 38,
    color: DARK.textFaint,
    paddingBottom: 8,
  },
  heroOpName: {
    color: DARK.text,
    fontSize: 22,
    fontWeight: '600',
    letterSpacing: -0.3,
    marginTop: 6,
  },
  heroInstruction: {
    color: DARK.textMuted,
    fontSize: 14,
    lineHeight: 22,
    marginTop: 8,
  },
  heroActions: { flexDirection: 'row', gap: 10, marginTop: 16 },
  heroSecondary: {
    width: 64,
    height: 64,
    borderRadius: 14,
    borderWidth: 1,
    backgroundColor: '#0e0e10',
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroPrimary: {
    flex: 1,
    height: 64,
    borderRadius: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  heroPrimaryText: { color: '#1a1208', fontSize: 17, fontWeight: '600' },

  // Counters
  countersRow: { flexDirection: 'row', gap: 12 },
  counter: {
    flex: 1,
    backgroundColor: DARK.surface,
    borderWidth: 1,
    borderColor: DARK.border,
    borderRadius: 16,
    padding: 18,
  },
  counterHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  counterValue: {
    fontFamily: MONO,
    fontSize: 52,
    fontWeight: '600',
    letterSpacing: -1.5,
    marginTop: 6,
    lineHeight: 56,
  },

  // Rail
  railCard: {
    flex: 1,
    backgroundColor: DARK.surface,
    borderWidth: 1,
    borderColor: DARK.border,
    borderRadius: 16,
    padding: 18,
    minHeight: 0,
  },
  railHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  stepRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
    marginBottom: 4,
  },
  stepDot: {
    width: 26,
    height: 26,
    borderRadius: 13,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepName: { flex: 1, fontSize: 14 },

  // Scan tile + andon
  scanTile: {
    backgroundColor: BRAND.amber,
    borderRadius: 16,
    padding: 22,
    gap: 8,
  },
  scanTitle: { fontSize: 18, fontWeight: '700', color: '#1a1208', marginTop: 4 },
  andonCard: {
    flex: 1,
    backgroundColor: DARK.surface,
    borderWidth: 1,
    borderColor: DARK.border,
    borderRadius: 16,
    padding: 18,
    minHeight: 0,
  },
  andonTitle: { color: DARK.text, fontSize: 15, fontWeight: '500', marginTop: 6 },
  andonGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 12,
  },
  andonTile: {
    flexBasis: '47%',
    flexGrow: 1,
    minHeight: 76,
    borderRadius: 12,
    backgroundColor: '#0e0e10',
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
  },
  stopBtn: {
    marginTop: 12,
    height: 52,
    borderRadius: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  stopText: { color: '#fff', fontSize: 14, fontWeight: '600', letterSpacing: 0.4 },
});
