import { useLocalSearchParams } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { BatchActionsCard } from '@/components/production/BatchActionsCard';
import { BomRequirementsCard } from '@/components/production/BomRequirementsCard';
import { ConfirmationsCard } from '@/components/production/ConfirmationsCard';
import { LotPickerModal } from '@/components/operator/LotPickerModal';
import { MaterialAllocationModal } from '@/components/operator/MaterialAllocationModal';
import { PackagingChecklistCard } from '@/components/production/PackagingChecklistCard';
import { QualityChecksCard } from '@/components/production/QualityChecksCard';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useBatch } from '@/hooks/queries/useBatch';
import { useIssueTypes } from '@/hooks/queries/useIssues';
import { useCompleteStep, useReportStepProblem, useStartStep } from '@/hooks/mutations/batchSteps';
import { useWorkOrderRealtime } from '@/hooks/useWorkOrderRealtime';
import { useAuthStore } from '@/stores/authStore';
import type { BatchStep } from '@/types/api';

export function RunBatchScreen() {
  const { batchId, id: workOrderIdParam } = useLocalSearchParams<{ batchId: string; id: string }>();
  const numericId = Number(batchId);
  const numericWorkOrderId = Number(workOrderIdParam);
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const batch = useBatch(numericId);
  const startMutation = useStartStep(numericId);
  const completeMutation = useCompleteStep(numericId);
  const reportMutation = useReportStepProblem(numericId);

  // Live updates on this WO — Reverb push wakes the batch query the
  // instant a supervisor transitions status or another operator finishes
  // a step. Falls back silently to the 5s React Query refetch.
  useWorkOrderRealtime(Number.isFinite(numericWorkOrderId) ? numericWorkOrderId : undefined);

  const [producedQty, setProducedQty] = useState('');
  const [reportingStepId, setReportingStepId] = useState<number | null>(null);
  const [issueTypeId, setIssueTypeId] = useState<number | null>(null);
  const [issueDescription, setIssueDescription] = useState('');
  const [allocPending, setAllocPending] = useState<BatchStep | null>(null);
  const [lotPickerStepId, setLotPickerStepId] = useState<number | null>(null);

  const issueTypes = useIssueTypes();
  const myWorkstationId = useAuthStore((s) => s.activeWorkstationId);

  if (batch.isLoading) return <LoadingState />;
  if (batch.isError || !batch.data) return <ErrorState error={batch.error} onRetry={batch.refetch} />;

  const steps = batch.data.steps ?? [];
  const myStepCount = myWorkstationId
    ? steps.filter((s) => s.workstation_id === myWorkstationId).length
    : 0;
  const currentStep = steps.find((s) => s.status === 'IN_PROGRESS') ?? steps.find((s) => s.status === 'PENDING');
  const completed = steps.filter((s) => s.status === 'DONE').length;
  const total = steps.length;

  // The first step start triggers backend material allocation. Show a preview
  // modal so the operator confirms stock is sufficient before committing.
  const isBatchPending = batch.data.status === 'PENDING';
  const onStart = (step: BatchStep) => {
    if (isBatchPending) {
      setAllocPending(step);
      return;
    }
    startMutation.mutate(step.id, {
      onError: (e: Error) => Alert.alert('Start failed', e.message),
    });
  };

  const confirmAllocation = () => {
    if (!allocPending) return;
    const step = allocPending;
    startMutation.mutate(step.id, {
      onSuccess: () => setAllocPending(null),
      onError: (e: Error) => Alert.alert('Start failed', e.message),
    });
  };

  const onComplete = (step: BatchStep) => {
    const qty = producedQty ? Number(producedQty) : undefined;
    completeMutation.mutate(
      { stepId: step.id, produced_qty: qty },
      {
        onSuccess: () => setProducedQty(''),
        onError: (e: Error) => Alert.alert('Complete failed', e.message),
      },
    );
  };

  const onSubmitProblem = () => {
    if (!reportingStepId || !issueTypeId) return;
    reportMutation.mutate(
      {
        stepId: reportingStepId,
        issue_type_id: issueTypeId,
        description: issueDescription || undefined,
      },
      {
        onSuccess: () => {
          setReportingStepId(null);
          setIssueTypeId(null);
          setIssueDescription('');
          Alert.alert('Issue reported');
        },
        onError: (e: Error) => Alert.alert('Report failed', e.message),
      },
    );
  };

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title="Run batch"
        subtitle={`BATCH #${batch.data.id} · ${completed}/${total} STEPS`}
        rightSlot={<StatusPill status={batch.data.status} />}
      />
      <ScrollView
        style={{ flex: 1, backgroundColor: palette.background }}
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled">

      {/* Active step focus */}
      {currentStep ? (
        <ActiveStepCard
          step={currentStep}
          producedQty={producedQty}
          setProducedQty={setProducedQty}
          onStart={() => onStart(currentStep)}
          onComplete={() => onComplete(currentStep)}
          onReport={() => {
            setReportingStepId(currentStep.id);
            setIssueTypeId(null);
            setIssueDescription('');
          }}
          onPickLot={() => setLotPickerStepId(currentStep.id)}
          startLoading={startMutation.isPending}
          completeLoading={completeMutation.isPending}
        />
      ) : (
        <EmptyState title="No active step" subtitle="All steps for this batch are done or skipped." />
      )}

      {reportingStepId ? (
        <Card style={{ gap: 12 }}>
          <SectionLabel>Report problem</SectionLabel>
          <View style={styles.typeGrid}>
            {(issueTypes.data ?? []).map((t) => {
              const active = t.id === issueTypeId;
              return (
                <Button
                  key={t.id}
                  title={t.name}
                  variant={active ? 'primary' : 'outline'}
                  size="sm"
                  onPress={() => setIssueTypeId(t.id)}
                />
              );
            })}
          </View>
          <Field
            label="Description"
            value={issueDescription}
            onChangeText={setIssueDescription}
            multiline
            numberOfLines={3}
            placeholder="What went wrong?"
            style={{ minHeight: 80, textAlignVertical: 'top' }}
          />
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Button
              title="Submit"
              variant="danger"
              onPress={onSubmitProblem}
              loading={reportMutation.isPending}
              disabled={!issueTypeId}
              style={{ flex: 1 }}
              leftIcon={<FontAwesome name="exclamation-triangle" size={14} color="#fff" />}
            />
            <Button
              title="Cancel"
              variant="outline"
              onPress={() => {
                setReportingStepId(null);
                setIssueTypeId(null);
              }}
              style={{ flex: 1 }}
            />
          </View>
        </Card>
      ) : null}

      <SectionLabel>Production controls</SectionLabel>
      <BomRequirementsCard
        processTemplateId={batch.data.work_order?.process_snapshot?.template_id}
        quantity={Number(batch.data.target_qty)}
      />
      <ConfirmationsCard batchId={batch.data.id} />
      <QualityChecksCard batchId={batch.data.id} />
      {batch.data.status === 'IN_PROGRESS' || batch.data.status === 'DONE' ? (
        <PackagingChecklistCard batchId={batch.data.id} />
      ) : null}

      <BatchActionsCard batch={batch.data} />

      <SectionLabel
        right={<Mono size={11} color={palette.textFaint}>{steps.length} STEPS</Mono>}>
        Process · all steps
      </SectionLabel>

      {myWorkstationId && myStepCount > 0 ? (
        <View style={styles.myStationBanner}>
          <FontAwesome name="map-marker" size={13} color="#1a1208" />
          <Mono size={11} color="#1a1208" weight="700" letterSpacing={0.6} style={{ flex: 1 }}>
            YOUR STATION · {myStepCount} STEP{myStepCount === 1 ? '' : 'S'}
          </Mono>
        </View>
      ) : null}

      {steps.map((step, i) => (
        <StepRailRow
          key={step.id}
          step={step}
          index={i}
          last={i === steps.length - 1}
          myWorkstationId={myWorkstationId}
        />
      ))}

      <MaterialAllocationModal
        batchId={allocPending != null ? batch.data.id : null}
        onCancel={() => setAllocPending(null)}
        onConfirm={confirmAllocation}
        confirmLoading={startMutation.isPending}
      />

      <LotPickerModal
        open={lotPickerStepId != null}
        onClose={() => setLotPickerStepId(null)}
        batchStepId={lotPickerStepId}
        materialId={null}
      />
      </ScrollView>
    </View>
  );
}

function ActiveStepCard({
  step,
  producedQty,
  setProducedQty,
  onStart,
  onComplete,
  onReport,
  onPickLot,
  startLoading,
  completeLoading,
}: {
  step: BatchStep;
  producedQty: string;
  setProducedQty: (v: string) => void;
  onStart: () => void;
  onComplete: () => void;
  onReport: () => void;
  onPickLot: () => void;
  startLoading: boolean;
  completeLoading: boolean;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const startedAt = useRef<number | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    if (step.status === 'IN_PROGRESS' && startedAt.current == null) {
      startedAt.current = Date.now();
    } else if (step.status !== 'IN_PROGRESS') {
      startedAt.current = null;
    }
  }, [step.status]);

  useEffect(() => {
    if (step.status !== 'IN_PROGRESS') return;
    const id = setInterval(() => setTick((t) => t + 1), 1000);
    return () => clearInterval(id);
  }, [step.status]);

  const elapsed = startedAt.current ? Date.now() - startedAt.current : 0;
  const minutes = Math.floor(elapsed / 60000);
  const seconds = Math.floor((elapsed % 60000) / 1000);
  // touch tick so React knows
  void tick;

  return (
    <Card variant="inverse" accent={BRAND.amber} style={{ padding: 18, gap: 14 }}>
      <View>
        <Mono size={10} color="#6F6C66" letterSpacing={0.6}>
          CURRENT OPERATION · STEP {step.sequence ?? '—'}
        </Mono>
        <Text style={styles.stepName}>{step.name}</Text>
      </View>

      {step.instruction ? (
        <Text style={styles.instruction}>{step.instruction}</Text>
      ) : null}

      {step.status === 'IN_PROGRESS' ? (
        <View style={styles.timerBlock}>
          <Mono size={10} color="#6F6C66" letterSpacing={1}>ELAPSED</Mono>
          <Text style={styles.timer}>
            {String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}
          </Text>
        </View>
      ) : null}

      {step.status === 'PENDING' ? (
        <Button
          title="Start step"
          size="lg"
          onPress={onStart}
          loading={startLoading}
          leftIcon={<FontAwesome name="play" size={14} color="#1a1208" />}
        />
      ) : (
        <View style={{ gap: 12 }}>
          <Field
            label="Produced quantity (optional)"
            value={producedQty}
            onChangeText={setProducedQty}
            keyboardType="number-pad"
            style={{ backgroundColor: '#F1EFEA', borderColor: '#E6E4DE', color: '#1A1917' }}
          />
          <Button
            title="Complete step"
            size="lg"
            variant="success"
            onPress={onComplete}
            loading={completeLoading}
            leftIcon={<FontAwesome name="check" size={14} color="#fff" />}
          />
        </View>
      )}

      {step.status === 'IN_PROGRESS' ? (
        <Button
          title="Pick lot"
          variant="outline"
          onPress={onPickLot}
          leftIcon={<FontAwesome name="qrcode" size={13} color={BRAND.amber} />}
          style={{ borderColor: BRAND.amber }}
        />
      ) : null}

      <Button
        title="Report problem"
        variant="outline"
        onPress={onReport}
        leftIcon={<FontAwesome name="exclamation-triangle" size={13} color="#D6442F" />}
        style={{ borderColor: '#FBEAE6' }}
      />
    </Card>
  );
}

function StepRailRow({
  step,
  index,
  last,
  myWorkstationId,
}: {
  step: BatchStep;
  index: number;
  last: boolean;
  myWorkstationId: number | null;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const isDone = step.status === 'DONE';
  const isRunning = step.status === 'IN_PROGRESS';
  const isMine = myWorkstationId != null && step.workstation_id === myWorkstationId;
  // Dim non-mine non-active steps when an operator has a workstation selected,
  // matching the design's "focus on my work" affordance.
  const dim = myWorkstationId != null && !isMine && !isRunning;
  const dotBg = isDone ? palette.success : isRunning ? BRAND.amber : palette.surfaceAlt;
  const wsCode = step.workstation?.code ?? step.workstation?.name ?? null;

  return (
    <View style={[styles.railRow, dim ? { opacity: 0.45 } : null]}>
      <View style={styles.railCol}>
        <View style={[styles.railDot, { backgroundColor: dotBg }]}>
          {isDone ? (
            <FontAwesome name="check" size={10} color="#fff" />
          ) : (
            <Mono size={9} color={isRunning ? '#1a1208' : palette.textFaint} weight="700">
              {step.sequence ?? index + 1}
            </Mono>
          )}
        </View>
        {!last ? <View style={[styles.railLine, { backgroundColor: palette.border }]} /> : null}
      </View>
      <View
        style={[
          styles.railContent,
          (isRunning || isMine) && styles.railContentActive,
          isRunning && { borderColor: BRAND.amber, backgroundColor: palette.surface },
          isMine && !isRunning && {
            borderColor: palette.border,
            backgroundColor: palette.surface,
            borderLeftWidth: 4,
            borderLeftColor: BRAND.amber,
          },
        ]}>
        <View style={styles.row}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
              <Text
                style={[
                  styles.railName,
                  {
                    color: isDone ? palette.textMuted : palette.text,
                    textDecorationLine: isDone ? 'line-through' : 'none',
                  },
                ]}
                numberOfLines={2}>
                {step.name}
              </Text>
              {isMine ? (
                <View style={styles.myStationPill}>
                  <Mono size={9} color="#1a1208" weight="700" letterSpacing={0.5}>
                    MY STATION
                  </Mono>
                </View>
              ) : null}
            </View>
            {wsCode || step.produced_qty != null ? (
              <View style={styles.metaRow}>
                {wsCode ? (
                  <View
                    style={[
                      styles.wsChip,
                      {
                        backgroundColor: palette.surfaceAlt,
                        borderColor: palette.border,
                      },
                    ]}>
                    <Mono
                      size={9.5}
                      color={isMine ? BRAND.amber : palette.textMuted}
                      weight="600"
                      letterSpacing={0.5}>
                      {wsCode}
                    </Mono>
                  </View>
                ) : null}
                {step.produced_qty != null ? (
                  <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4}>
                    PRODUCED {step.produced_qty}
                  </Mono>
                ) : null}
              </View>
            ) : null}
          </View>
          <StatusPill status={step.status} />
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { padding: 18, gap: 14 },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  title: { fontSize: 22, fontWeight: '600', letterSpacing: -0.3, marginTop: 4 },
  stepName: { color: '#fff', fontSize: 22, fontWeight: '600', letterSpacing: -0.3, marginTop: 6 },
  instruction: { color: '#1A1917', fontSize: 14, lineHeight: 21 },
  timerBlock: { alignItems: 'center', paddingVertical: 6 },
  timer: { color: '#fff', fontSize: 56, fontWeight: '500', fontFamily: MONO, letterSpacing: -2, lineHeight: 56, marginTop: 4 },
  typeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  railRow: { flexDirection: 'row', gap: 12 },
  railCol: { alignItems: 'center', width: 22 },
  railDot: { width: 22, height: 22, borderRadius: 11, alignItems: 'center', justifyContent: 'center' },
  railLine: { flex: 1, width: 2, minHeight: 18, marginTop: 2 },
  railContent: { flex: 1, paddingBottom: 14 },
  railContentActive: { padding: 12, borderRadius: 12, borderWidth: 1, marginTop: -4, marginBottom: 8 },
  row: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 8 },
  railName: { fontSize: 14, fontWeight: '500', flexShrink: 1 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 6 },
  wsChip: {
    paddingVertical: 2,
    paddingHorizontal: 6,
    borderRadius: 4,
    borderWidth: 1,
  },
  myStationPill: {
    paddingVertical: 1,
    paddingHorizontal: 5,
    borderRadius: 3,
    backgroundColor: BRAND.amber,
  },
  myStationBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 8,
    backgroundColor: BRAND.amber,
  },
});
