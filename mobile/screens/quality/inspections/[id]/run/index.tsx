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

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
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
 * to the detail screen.
 */
export function InspectionRunner() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  // Operator wizard is always rendered against the dark palette regardless of
  // user color preference — matches the design's high-contrast factory-floor
  // aesthetic where every other operator surface (batch run, downtime banner,
  // anomaly form) is dark.
  const palette = Colors.dark;
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
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        <ScreenHeader back title={t('Run inspection')} />
        <View style={{ padding: 24 }}>
          <Mono size={11} color={palette.textFaint}>
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
      style={{ flex: 1, backgroundColor: palette.background }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScreenHeader
        back
        variant="dark"
        title={t('Inspection')}
        subtitle={`${query.data.plan?.name ?? t('Inspection')} · LOT ${query.data.lot_number}`}
        subtitleColor={BRAND.amber}
      />

      {/* Progress bar — 5-segment style. Each segment fills with amber when
          the step has a recorded value (matching the design). */}
      <View style={styles.progressBlock}>
        <View style={styles.progressHead}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.7}>
            {t('STEP').toUpperCase()} {stepIdx + 1} {t('OF').toUpperCase()} {results.length}
          </Mono>
          <Mono size={10.5} color={BRAND.amber} weight="700">
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
                  {
                    backgroundColor: isComplete ? BRAND.amber : palette.border,
                  },
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
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
            {current.criterion_type.toUpperCase()}
            {current.required ? ' · REQUIRED' : ''}
          </Mono>
          <Text style={[styles.title, { color: palette.text }]}>
            {current.criterion_name}
          </Text>
          {specHint(current) ? (
            <Mono size={11} color={palette.textMuted} style={{ marginTop: 4 }}>
              {specHint(current).toUpperCase()}
            </Mono>
          ) : null}
        </View>

        <ResultInput
          criterion={current}
          draft={draft}
          onChange={setDraft}
          palette={palette}
        />

        <View>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.7}>
            {t('Notes').toUpperCase()}
          </Mono>
          <TextInput
            value={draft.notes ?? ''}
            onChangeText={(v) => setDraft({ notes: v })}
            placeholder={t('Optional observations')}
            placeholderTextColor={palette.textFaint}
            multiline
            style={[
              styles.notesInput,
              {
                backgroundColor: palette.surface,
                borderColor: palette.border,
                color: palette.text,
              },
            ]}
          />
        </View>
      </ScrollView>

      {/* Bottom action bar */}
      <View
        style={[
          styles.actionBar,
          { backgroundColor: palette.background, borderTopColor: palette.border },
        ]}>
        <Pressable
          onPress={() => setStepIdx(Math.max(0, stepIdx - 1))}
          disabled={stepIdx === 0}
          style={({ pressed }) => [
            styles.secondary,
            {
              backgroundColor: palette.surface,
              borderColor: palette.border,
              opacity: stepIdx === 0 ? 0.4 : pressed ? 0.85 : 1,
            },
          ]}>
          <Mono size={12} color={palette.text} weight="700" letterSpacing={0.5}>
            {t('Back').toUpperCase()}
          </Mono>
        </Pressable>
        {stepIdx < results.length - 1 ? (
          <Pressable
            onPress={saveAndAdvance}
            disabled={recordMutation.isPending}
            style={({ pressed }) => [
              styles.primary,
              { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
            ]}>
            <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.6}>
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
            style={({ pressed }) => [
              styles.primary,
              { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
            ]}>
            <FontAwesome name="check" size={14} color="#1a1208" />
            <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.6}>
              {t('Complete').toUpperCase()}
            </Mono>
          </Pressable>
        )}
      </View>

      {/* Inspection-wide notes (collected once at the end). The first time the
          operator opens the final step, we render this above the step input
          via an extra section so they aren't surprised on submit. */}
      {stepIdx === results.length - 1 ? (
        <View style={styles.summaryNotes}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.7}>
            {t('SUMMARY NOTES').toUpperCase()}
          </Mono>
          <TextInput
            value={notes}
            onChangeText={setNotes}
            placeholder={t('Inspection-wide observations')}
            placeholderTextColor={palette.textFaint}
            multiline
            style={[
              styles.notesInput,
              {
                backgroundColor: palette.surface,
                borderColor: palette.border,
                color: palette.text,
                marginTop: 6,
              },
            ]}
          />
        </View>
      ) : null}
    </KeyboardAvoidingView>
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
  palette,
}: {
  criterion: InspectionResult;
  draft: ResultDraft;
  onChange: (next: Partial<ResultDraft>) => void;
  palette: typeof Colors.light;
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
          placeholderTextColor={palette.textFaint}
          style={[
            styles.numericInput,
            {
              backgroundColor: palette.surface,
              borderColor: palette.border,
              color: palette.text,
              fontFamily: MONO,
            },
          ]}
        />
      </View>
    );
  }

  if (type === 'pass_fail' || type === 'functional') {
    return (
      <View style={styles.boolRow}>
        {[
          { v: true, label: 'PASS', color: '#1C9A55' },
          { v: false, label: 'FAIL', color: '#D6442F' },
        ].map((opt) => {
          const on = draft.value_boolean === opt.v;
          return (
            <Pressable
              key={opt.label}
              onPress={() => onChange({ value_boolean: opt.v })}
              style={({ pressed }) => [
                styles.boolTile,
                {
                  backgroundColor: on ? `${opt.color}11` : palette.surface,
                  borderColor: on ? opt.color : palette.border,
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <Mono size={16} color={opt.color} weight="700" letterSpacing={0.5}>
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
      placeholderTextColor={palette.textFaint}
      multiline
      style={[
        styles.notesInput,
        {
          backgroundColor: palette.surface,
          borderColor: palette.border,
          color: palette.text,
        },
      ]}
    />
  );
}

const styles = StyleSheet.create({
  progressBlock: { paddingHorizontal: 20, paddingTop: 16, gap: 8 },
  progressHead: { flexDirection: 'row', justifyContent: 'space-between' },
  progressSegments: { flexDirection: 'row', gap: 4 },
  progressSegment: { flex: 1, height: 6, borderRadius: 3 },
  title: { fontSize: 22, fontWeight: '700', letterSpacing: -0.3, marginTop: 4 },
  numericInput: {
    height: 64,
    paddingHorizontal: 16,
    fontSize: 28,
    fontWeight: '700',
    borderRadius: 12,
    borderWidth: 1,
  },
  notesInput: {
    minHeight: 90,
    borderRadius: 10,
    borderWidth: 1,
    padding: 12,
    fontSize: 14,
    textAlignVertical: 'top',
  },
  boolRow: { flexDirection: 'row', gap: 10 },
  boolTile: {
    flex: 1,
    height: 96,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionBar: {
    flexDirection: 'row',
    gap: 10,
    padding: 16,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  secondary: {
    flex: 1,
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primary: {
    flex: 2,
    height: 48,
    borderRadius: 12,
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
