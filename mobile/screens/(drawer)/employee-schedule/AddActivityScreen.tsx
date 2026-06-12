// Add an activity to a worker's day.
// Matches the design's "Add activity" mobile screen:
//   - 9-tile type picker grid
//   - Custom-pill picker row (+ "+ NEW CUSTOM")
//   - Big from/to time inputs with computed duration banner
//   - Optional WO link (single-select sheet, omitted here — uses dropdown for now)
//   - Notes
//   - Cancel / Save buttons

import { format, parse } from 'date-fns';
import { useMemo, useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { iconForActivity } from '@/components/employee-schedule/activityIcons';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { Button } from '@/components/ui/Button';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useActivityTypes,
  useCreateActivity,
} from '@/hooks/queries/useEmployeeActivities';
import { useWorkers } from '@/hooks/queries/useHr';
import {
  formatMinutes,
  toMinutes,
  type ActivityType,
  type CreateActivityInput,
} from '@/api/employeeActivities';

const TYPE_TILE_ORDER: ActivityType[] = [
  'work', 'break', 'rest',
  'travel', 'setup', 'meeting',
  'training', 'maint', 'qc',
];

function isValidTime(v: string): boolean {
  return /^([01]\d|2[0-3]):[0-5]\d$/.test(v);
}

interface Props {
  workerId: number;
  initialDate?: Date;
  onCancel?: () => void;
  onSaved?: () => void;
}

export function AddActivityScreen({ workerId, initialDate, onCancel, onSaved }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const router = useRouter();

  const typesQ = useActivityTypes();
  const workersQ = useWorkers({ per_page: 200 });
  const createMut = useCreateActivity();

  const date = initialDate ?? new Date();
  const dateStr = format(date, 'yyyy-MM-dd');
  const initialFrom = format(date, 'HH:mm');
  const initialTo = format(
    new Date(date.getTime() + 60 * 60 * 1000),
    'HH:mm',
  );

  const [selectedType, setSelectedType] = useState<ActivityType>('work');
  const [selectedCustomCode, setSelectedCustomCode] = useState<string | null>(null);
  const [fromTime, setFromTime] = useState<string>(initialFrom);
  const [toTime, setToTime] = useState<string>(initialTo);
  const [label, setLabel] = useState<string>('');
  const [notes, setNotes] = useState<string>('');
  const [submitError, setSubmitError] = useState<string | null>(null);

  const worker = workersQ.data?.data?.find((w) => w.id === workerId);

  const typeMeta = useMemo(() => {
    const byKey: Record<string, { color: string; label: string; short: string }> = {};
    typesQ.data?.built_in.forEach((e) => {
      byKey[e.key] = { color: e.color, label: e.label, short: e.short };
    });
    return byKey;
  }, [typesQ.data]);

  const customs = typesQ.data?.custom ?? [];

  const duration = useMemo(() => {
    if (!isValidTime(fromTime) || !isValidTime(toTime)) return 0;
    return Math.max(0, toMinutes(toTime) - toMinutes(fromTime));
  }, [fromTime, toTime]);

  const validTime = isValidTime(fromTime) && isValidTime(toTime) && duration > 0;

  async function handleSave() {
    setSubmitError(null);
    if (!validTime) {
      setSubmitError(t('Enter a valid time range.'));
      return;
    }
    const input: CreateActivityInput = {
      worker_id: workerId,
      type: selectedType,
      custom_code: selectedType === 'custom' ? selectedCustomCode : null,
      label: label.trim() || null,
      starts_at: `${dateStr} ${fromTime}:00`,
      ends_at: `${dateStr} ${toTime}:00`,
      notes: notes.trim() || null,
    };
    try {
      await createMut.mutateAsync(input);
      if (onSaved) onSaved();
      else router.back();
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      setSubmitError(msg);
    }
  }

  if (typesQ.isLoading) return <LoadingState label={t('Loading types')} />;
  if (typesQ.error) return <ErrorState error={typesQ.error} onRetry={() => typesQ.refetch()} />;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title={t('Add activity')}
        subtitle={`${worker?.name ?? ''} · ${format(date, 'EEE dd MMM · HH:mm')}`}
        onBack={onCancel}
      />
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.content}>
          {/* Type tile grid */}
          <Section label={t('Type')}>
            <View style={styles.tileGrid}>
              {TYPE_TILE_ORDER.map((k) => {
                const def = typeMeta[k];
                const on = selectedType === k;
                if (!def) return null;
                return (
                  <Pressable
                    key={k}
                    onPress={() => {
                      setSelectedType(k);
                      setSelectedCustomCode(null);
                    }}
                    style={[
                      styles.tile,
                      {
                        backgroundColor: on ? def.color + '22' : palette.surface,
                        borderColor: on ? def.color : palette.border,
                        borderWidth: on ? 1.5 : 1,
                      },
                    ]}>
                    <View
                      style={[
                        styles.tileBadge,
                        { backgroundColor: def.color + '30' },
                      ]}>
                      <FontAwesome name={iconForActivity(k)} size={16} color={def.color} />
                    </View>
                    <Mono
                      size={9.5}
                      weight="700"
                      color={on ? def.color : palette.textMuted}
                      letterSpacing={0.4}
                      upper>
                      {def.short}
                    </Mono>
                  </Pressable>
                );
              })}
            </View>
          </Section>

          {/* Custom pills */}
          <Section label={t('Custom')}>
            <View style={styles.pillRow}>
              {customs.length === 0 ? (
                <Text style={[styles.placeholderText, { color: palette.textFaint }]}>
                  {t('No custom types defined.')}
                </Text>
              ) : (
                customs.map((c) => {
                  const on = selectedType === 'custom' && selectedCustomCode === c.code;
                  return (
                    <Pressable
                      key={c.code}
                      onPress={() => {
                        setSelectedType('custom');
                        setSelectedCustomCode(c.code ?? null);
                      }}
                      style={[
                        styles.pill,
                        {
                          backgroundColor: on ? c.color + '20' : palette.surface,
                          borderColor: on ? c.color : c.color + '60',
                          borderWidth: on ? 2 : 1,
                        },
                      ]}>
                      <View style={[styles.pillSwatch, { backgroundColor: c.color }]} />
                      <Mono size={11} weight="600" color={palette.text} style={{ fontFamily: undefined }}>
                        {c.label}
                      </Mono>
                    </Pressable>
                  );
                })
              )}
              <Pressable
                onPress={() => {
                  /* TODO: open "new custom type" sheet */
                }}
                style={[
                  styles.pillDashed,
                  { borderColor: palette.border },
                ]}>
                <FontAwesome name="plus" size={10} color={palette.textMuted} />
                <Mono size={10.5} weight="700" color={palette.textMuted} letterSpacing={0.4} upper>
                  {t('New custom')}
                </Mono>
              </Pressable>
            </View>
          </Section>

          {/* Time range */}
          <Section label={t('Time range')}>
            <View style={styles.timeRow}>
              <TimeBox
                label={t('From')}
                value={fromTime}
                onChange={setFromTime}
                palette={palette}
              />
              <TimeBox
                label={t('To')}
                value={toTime}
                onChange={setToTime}
                palette={palette}
              />
            </View>
            <View
              style={[
                styles.durationBanner,
                { backgroundColor: palette.warningSoft },
              ]}>
              <Mono size={11} weight="700" color={BRAND.amber} letterSpacing={0.4} upper>
                {t('Duration')} {formatMinutes(duration)}
              </Mono>
            </View>
          </Section>

          {/* Label override */}
          <Section label={t('Label')}>
            <TextInput
              value={label}
              onChangeText={setLabel}
              placeholder={t('e.g. Lunch, Shift handover')}
              placeholderTextColor={palette.textFaint}
              style={[
                styles.textInput,
                {
                  color: palette.text,
                  backgroundColor: palette.surface,
                  borderColor: palette.border,
                },
              ]}
            />
          </Section>

          {/* Notes */}
          <Section label={t('Notes')}>
            <TextInput
              value={notes}
              onChangeText={setNotes}
              multiline
              numberOfLines={3}
              placeholder={t('Optional context…')}
              placeholderTextColor={palette.textFaint}
              style={[
                styles.textInput,
                styles.textArea,
                {
                  color: palette.text,
                  backgroundColor: palette.surface,
                  borderColor: palette.border,
                },
              ]}
            />
          </Section>

          {submitError ? (
            <View style={[styles.errorBox, { backgroundColor: palette.dangerSoft }]}>
              <Mono size={11} color={palette.danger} weight="600" style={{ fontFamily: undefined }}>
                {submitError}
              </Mono>
            </View>
          ) : null}

          <View style={styles.actionRow}>
            <Button
              title={t('Cancel')}
              variant="outline"
              onPress={() => (onCancel ? onCancel() : router.back())}
              style={{ flex: 1 }}
            />
            <Button
              title={t('Save activity')}
              variant="primary"
              loading={createMut.isPending}
              disabled={!validTime || createMut.isPending}
              onPress={handleSave}
              style={{ flex: 2 }}
            />
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

function Section({ label, children }: { label: string; children: React.ReactNode }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View style={{ gap: 8 }}>
      <Mono size={10.5} color={palette.textMuted} letterSpacing={0.8} upper>
        {label}
      </Mono>
      {children}
    </View>
  );
}

function TimeBox({
  label,
  value,
  onChange,
  palette,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  palette: typeof Colors.light;
}) {
  return (
    <View
      style={[
        styles.timeBox,
        { backgroundColor: palette.surface, borderColor: palette.border },
      ]}>
      <Mono size={9.5} color={palette.textMuted} letterSpacing={0.5} upper>
        {label}
      </Mono>
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder="HH:mm"
        placeholderTextColor={palette.textFaint}
        keyboardType="numbers-and-punctuation"
        maxLength={5}
        style={{
          fontFamily: MONO,
          fontSize: 24,
          fontWeight: '700',
          color: palette.text,
          marginTop: 4,
          letterSpacing: -0.5,
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  content: {
    padding: 16,
    gap: 16,
    paddingBottom: 40,
  },
  tileGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  tile: {
    width: '32%',
    padding: 12,
    borderRadius: 10,
    alignItems: 'center',
    gap: 6,
    minHeight: 72,
  },
  tileBadge: {
    width: 32,
    height: 32,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pillRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 100,
  },
  pillSwatch: {
    width: 8,
    height: 8,
    borderRadius: 2,
  },
  pillDashed: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 100,
    borderWidth: 1,
    borderStyle: 'dashed',
  },
  placeholderText: {
    fontFamily: MONO,
    fontSize: 10.5,
    fontStyle: 'italic',
    paddingHorizontal: 6,
  },
  timeRow: {
    flexDirection: 'row',
    gap: 8,
  },
  timeBox: {
    flex: 1,
    paddingVertical: 14,
    paddingHorizontal: 14,
    borderWidth: 1,
    borderRadius: 10,
  },
  durationBanner: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  textInput: {
    minHeight: 44,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
    fontSize: 13,
  },
  textArea: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  errorBox: {
    padding: 10,
    borderRadius: 8,
  },
  actionRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 8,
  },
});
