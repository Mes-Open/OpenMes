import { FontAwesome } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useCompleteInspection,
  useInspection,
  useRecordInspectionResult,
} from '@/hooks/queries/useInspections';
import type {
  InspectionCriterionType,
  InspectionResult,
} from '@/api/inspections';

/**
 * Inspection runner wizard — operator-facing full-screen flow that walks the
 * plan criteria one at a time. Each step records a result via PATCH, then a
 * final "Complete" call decides pass/fail/conditional from the boolean flags
 * the controller computes. While pending, the operator can navigate back to
 * any step to edit a value; once `complete` is called, the modal pops back
 * to the detail screen. Re-skinned to the Geist White tokens; the step-through
 * interaction logic is unchanged.
 */
export function InspectionRunner() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const query = useInspection(numericId);
  const recordMutation = useRecordInspectionResult();
  const completeMutation = useCompleteInspection();

  // Step index — clamped to the criteria list. After clicking "Save & next"
  // on the last step, we surface the Complete CTA instead of incrementing.
  const [stepIdx, setStepIdx] = useState(0);
  const [notes, setNotes] = useState('');

  // Local per-result drafts so the operator can scrub between steps without
  // each keystroke hitting the network — we only PATCH on "Save & next".
  const [drafts, setDrafts] = useState<Record<number, ResultDraft>>({});

  const results = query.data?.results ?? [];
  const current = results[stepIdx];

  const completed = useMemo(
    () => results.every((r) => isResultComplete(r, drafts[r.id])),
    [results, drafts],
  );

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} onRetry={query.refetch} />;

  if (results.length === 0) {
    return (
      <View style={styles.screen}>
        <RunnerHeader
          onBack={() => router.back()}
          title={t('Run inspection')}
          subtitle=""
        />
        <View style={{ padding: 24 }}>
          <Mono size={11} color={colors.faint}>
            {t('No criteria attached to this inspection').toUpperCase()}
          </Mono>
        </View>
      </View>
    );
  }

  const draft = drafts[current.id] ?? draftFrom(current);

  const setDraft = (next: Partial<ResultDraft>) =>
    setDrafts((cur) => ({
      ...cur,
      [current.id]: { ...draft, ...next },
    }));

  const saveAndAdvance = async () => {
    if (current.required && !hasValue(draft)) {
      Alert.alert(t('Missing value'), t('This criterion is required.'));
      return;
    }
    try {
      await recordMutation.mutateAsync({
        inspectionId: numericId,
        resultId: current.id,
        payload: {
          value_numeric: draft.value_numeric,
          value_boolean: draft.value_boolean,
          value_text: draft.value_text,
          notes: draft.notes,
        },
      });
      if (stepIdx < results.length - 1) setStepIdx(stepIdx + 1);
    } catch (e) {
      Alert.alert(t('Save failed'), (e as Error).message);
    }
  };

  const finalize = async () => {
    try {
      await completeMutation.mutateAsync({
        id: numericId,
        payload: { notes: notes.trim() || undefined },
      });
      router.back();
    } catch (e) {
      Alert.alert(t('Could not complete'), (e as Error).message);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.screen}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <RunnerHeader
        onBack={() => router.back()}
        title={t('Inspection')}
        subtitle={`${query.data.plan?.name ?? t('Inspection')} · LOT ${query.data.lot_number}`}
      />

      {/* Progress bar — one segment per criterion, filled when the step has a
          recorded value. */}
      <View style={styles.progressBlock}>
        <View style={styles.progressHead}>
          <Mono size={10.5} color={colors.faint} letterSpacing={0.7}>
            {t('STEP').toUpperCase()} {stepIdx + 1} {t('OF').toUpperCase()} {results.length}
          </Mono>
          <Mono size={10.5} color={colors.accent} weight="600">
            {Math.round(((stepIdx + 1) / Math.max(1, results.length)) * 100)}%
          </Mono>
        </View>
        <View style={styles.progressSegments}>
          {results.map((r, i) => {
            const isCurrent = i === stepIdx;
            const isComplete =
              isCurrent || isResultComplete(r, drafts[r.id]) || i < stepIdx;
            return (
              <Pressable
                key={r.id}
                onPress={() => setStepIdx(i)}
                style={[
                  styles.progressSegment,
                  { backgroundColor: isComplete ? colors.accent : colors.line },
                ]}
              />
            );
          })}
        </View>
      </View>

      <ScrollView
        contentContainerStyle={{ padding: 20, gap: 18 }}
        keyboardShouldPersistTaps="handled">
        <View>
          <Mono size={10.5} color={colors.faint} letterSpacing={0.8}>
            {String(current.criterion_type).toUpperCase()}
            {current.required ? ' · REQUIRED' : ''}
          </Mono>
          <Text style={styles.title}>{current.criterion_name}</Text>
          {specHint(current) ? (
            <Mono size={11} color={colors.muted} style={{ marginTop: 4 }}>
              {specHint(current).toUpperCase()}
            </Mono>
          ) : null}
        </View>

        <ResultInput criterion={current} draft={draft} onChange={setDraft} />

        <View>
          <Mono size={10.5} color={colors.faint} letterSpacing={0.7}>
            {t('Notes').toUpperCase()}
          </Mono>
          <TextInput
            value={draft.notes ?? ''}
            onChangeText={(v) => setDraft({ notes: v })}
            placeholder={t('Optional observations')}
            placeholderTextColor={colors.faint}
            multiline
            style={styles.notesInput}
          />
        </View>
      </ScrollView>

      {/* Bottom action bar */}
      <View style={styles.actionBar}>
        <Pressable
          onPress={() => setStepIdx(Math.max(0, stepIdx - 1))}
          disabled={stepIdx === 0}
          style={({ pressed }) => [
            styles.secondary,
            { opacity: stepIdx === 0 ? 0.4 : pressed ? 0.85 : 1 },
          ]}>
          <Mono size={12} color={colors.ink} weight="600" letterSpacing={0.5}>
            {t('Back').toUpperCase()}
          </Mono>
        </Pressable>
        {stepIdx < results.length - 1 ? (
          <Pressable
            onPress={saveAndAdvance}
            disabled={recordMutation.isPending}
            style={({ pressed }) => [styles.primary, { opacity: pressed ? 0.9 : 1 }]}>
            <Mono size={12} color="#fff" weight="600" letterSpacing={0.6}>
              {t('Save & next').toUpperCase()}
            </Mono>
          </Pressable>
        ) : (
          <Pressable
            onPress={async () => {
              await saveAndAdvance();
              if (completed || isResultComplete(current, drafts[current.id])) {
                await finalize();
              }
            }}
            disabled={recordMutation.isPending || completeMutation.isPending}
            style={({ pressed }) => [styles.primary, { opacity: pressed ? 0.9 : 1 }]}>
            <FontAwesome name="check" size={14} color="#fff" />
            <Mono size={12} color="#fff" weight="600" letterSpacing={0.6}>
              {t('Complete').toUpperCase()}
            </Mono>
          </Pressable>
        )}
      </View>

      {/* Inspection-wide notes (collected once at the end). Rendered on the
          final step so the operator isn't surprised on submit. */}
      {stepIdx === results.length - 1 ? (
        <View style={styles.summaryNotes}>
          <Mono size={10.5} color={colors.faint} letterSpacing={0.7}>
            {t('SUMMARY NOTES').toUpperCase()}
          </Mono>
          <TextInput
            value={notes}
            onChangeText={setNotes}
            placeholder={t('Inspection-wide observations')}
            placeholderTextColor={colors.faint}
            multiline
            style={[styles.notesInput, { marginTop: 6 }]}
          />
        </View>
      ) : null}
    </KeyboardAvoidingView>
  );
}

// ── Header ───────────────────────────────────────────────────────────────────

function RunnerHeader({
  onBack,
  title,
  subtitle,
}: {
  onBack: () => void;
  title: string;
  subtitle: string;
}) {
  return (
    <View style={styles.header}>
      <Pressable onPress={onBack} hitSlop={10} style={styles.backBtn}>
        <FontAwesome name="chevron-left" size={15} color={colors.ink} />
      </Pressable>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={styles.headerTitle} numberOfLines={1}>{title}</Text>
        {subtitle ? (
          <Mono size={10} color={colors.accent} letterSpacing={0.4} numberOfLines={1} style={{ marginTop: 3 }}>
            {subtitle.toUpperCase()}
          </Mono>
        ) : null}
      </View>
    </View>
  );
}

// ── Local helpers ──────────────────────────────────────────────────────────

interface ResultDraft {
  value_numeric?: number;
  value_boolean?: boolean;
  value_text?: string;
  notes?: string;
}

function draftFrom(r: InspectionResult): ResultDraft {
  return {
    value_numeric:
      typeof r.value_numeric === 'string'
        ? Number(r.value_numeric)
        : (r.value_numeric ?? undefined),
    value_boolean: r.value_boolean ?? undefined,
    value_text: r.value_text ?? undefined,
    notes: r.notes ?? undefined,
  };
}

function hasValue(d: ResultDraft): boolean {
  return (
    d.value_numeric != null ||
    d.value_boolean != null ||
    (d.value_text != null && d.value_text.trim().length > 0)
  );
}

function isResultComplete(r: InspectionResult, d: ResultDraft | undefined) {
  if (!r.required) return true;
  if (d) return hasValue(d);
  return (
    r.value_numeric != null ||
    r.value_boolean != null ||
    (r.value_text != null && r.value_text.trim().length > 0)
  );
}

function specHint(r: InspectionResult): string {
  const min = r.spec_min;
  const max = r.spec_max;
  if (min != null && max != null) return `${min} – ${max}${r.unit ? ` ${r.unit}` : ''}`;
  if (min != null) return `≥ ${min}${r.unit ? ` ${r.unit}` : ''}`;
  if (max != null) return `≤ ${max}${r.unit ? ` ${r.unit}` : ''}`;
  return '';
}

function ResultInput({
  criterion,
  draft,
  onChange,
}: {
  criterion: InspectionResult;
  draft: ResultDraft;
  onChange: (next: Partial<ResultDraft>) => void;
}) {
  const { t } = useTranslation();
  const type = criterion.criterion_type as InspectionCriterionType;

  if (type === 'measurement') {
    return (
      <View>
        <TextInput
          value={draft.value_numeric != null ? String(draft.value_numeric) : ''}
          onChangeText={(v) => {
            const n = v.trim() === '' ? undefined : Number(v);
            onChange({ value_numeric: Number.isFinite(n) ? n : undefined });
          }}
          keyboardType="numeric"
          placeholder={t('Enter value')}
          placeholderTextColor={colors.faint}
          style={styles.numericInput}
        />
      </View>
    );
  }

  if (type === 'pass_fail' || type === 'functional') {
    return (
      <View style={styles.boolRow}>
        {[
          { v: true, label: 'PASS', color: colors.running, bg: colors.runningBg },
          { v: false, label: 'FAIL', color: colors.blocked, bg: colors.blockedBg },
        ].map((opt) => {
          const on = draft.value_boolean === opt.v;
          return (
            <Pressable
              key={opt.label}
              onPress={() => onChange({ value_boolean: opt.v })}
              style={({ pressed }) => [
                styles.boolTile,
                {
                  backgroundColor: on ? opt.bg : colors.card,
                  borderColor: on ? opt.color : colors.line,
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <Mono size={16} color={opt.color} weight="600" letterSpacing={0.5}>
                {t(opt.label).toUpperCase()}
              </Mono>
            </Pressable>
          );
        })}
      </View>
    );
  }

  // visual / other → free-form text
  return (
    <TextInput
      value={draft.value_text ?? ''}
      onChangeText={(v) => onChange({ value_text: v })}
      placeholder={t('Describe finding')}
      placeholderTextColor={colors.faint}
      multiline
      style={styles.notesInput}
    />
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line,
  },
  backBtn: {
    width: 34,
    height: 34,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: { fontSize: 17, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.3 },
  progressBlock: { paddingHorizontal: 20, paddingTop: 16, gap: 8 },
  progressHead: { flexDirection: 'row', justifyContent: 'space-between' },
  progressSegments: { flexDirection: 'row', gap: 4 },
  progressSegment: { flex: 1, height: 6, borderRadius: 3 },
  title: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4, marginTop: 4 },
  numericInput: {
    height: 64,
    paddingHorizontal: 16,
    fontSize: 28,
    fontFamily: fonts.mono.native.semibold,
    color: colors.ink,
    backgroundColor: colors.card,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
  },
  notesInput: {
    minHeight: 90,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    color: colors.ink,
    padding: 12,
    fontSize: 14,
    fontFamily: fonts.sans.native.regular,
    textAlignVertical: 'top',
  },
  boolRow: { flexDirection: 'row', gap: 10 },
  boolTile: {
    flex: 1,
    height: 96,
    borderRadius: radius.md,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionBar: {
    flexDirection: 'row',
    gap: 10,
    padding: 16,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.line,
    backgroundColor: colors.bg,
  },
  secondary: {
    flex: 1,
    height: 48,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primary: {
    flex: 2,
    height: 48,
    borderRadius: radius.md,
    backgroundColor: colors.accent,
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  summaryNotes: {
    paddingHorizontal: 20,
    paddingBottom: 12,
  },
});
