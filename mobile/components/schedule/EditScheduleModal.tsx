import { useEffect, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/Button';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useResizeScheduleOrder, useUpdateScheduleOrder } from '@/hooks/queries/useSchedule';
import { ScheduleConflictError } from '@/api/schedule';

interface Line {
  id: number;
  name: string;
  code?: string | null;
}

interface Props {
  visible: boolean;
  workOrderId: number | null;
  initialLineId: number | null;
  initialStartIso: string | null;
  initialEndIso: string | null;
  /** Block label for the modal header (e.g. "WO-186-001"). */
  title?: string;
  lines: Line[];
  onClose: () => void;
}

/**
 * Tap a Gantt bar → this modal. Three editable fields: line (chip row),
 * start (HH:mm + date inferred from initial), end (HH:mm). Save sends the
 * minute-level update via PUT /api/v1/schedule/{wo} with conflict detection.
 *
 * Note: this is the "lite" version of the planner write — drag/resize
 * gestures on the bar itself are a separate follow-up.
 */
export function EditScheduleModal({
  visible,
  workOrderId,
  initialLineId,
  initialStartIso,
  initialEndIso,
  title,
  lines,
  onClose,
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const update = useUpdateScheduleOrder();
  const resize = useResizeScheduleOrder();

  const [lineId, setLineId] = useState<number | null>(initialLineId);
  const [startIso, setStartIso] = useState<string>(initialStartIso ?? '');
  const [endIso, setEndIso] = useState<string>(initialEndIso ?? '');

  // Reset state every time the modal opens with a new bar.
  useEffect(() => {
    if (visible) {
      setLineId(initialLineId);
      setStartIso(initialStartIso ?? '');
      setEndIso(initialEndIso ?? '');
    }
  }, [visible, initialLineId, initialStartIso, initialEndIso]);

  if (!visible || !workOrderId) return null;

  const submit = (forceConflict = false) => {
    update.mutate(
      {
        id: workOrderId,
        input: {
          line_id: lineId,
          planned_start_at: startIso || null,
          planned_end_at: endIso || null,
          force_conflict: forceConflict,
        },
      },
      {
        onSuccess: () => onClose(),
        onError: (e: Error) => {
          if (e instanceof ScheduleConflictError) {
            Alert.alert(t('Conflict'), e.message, [
              { text: t('Cancel'), style: 'cancel' },
              {
                text: t('Reschedule anyway'),
                style: 'destructive',
                onPress: () => submit(true),
              },
            ]);
            return;
          }
          Alert.alert(t('Could not update schedule'), e.message);
        },
      },
    );
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={[styles.sheet, { backgroundColor: palette.background }]}>
          <View style={[styles.header, { borderBottomColor: palette.border }]}>
            <View style={{ flex: 1 }}>
              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.6}>
                {t('EDIT SCHEDULE')}
              </Mono>
              <Text style={[styles.title, { color: palette.text }]} numberOfLines={1}>
                {title ?? `#${workOrderId}`}
              </Text>
            </View>
            <Pressable onPress={onClose} hitSlop={10} style={styles.closeBtn}>
              <Mono size={12} color={palette.textMuted} weight="700">
                {t('CLOSE')}
              </Mono>
            </Pressable>
          </View>

          <ScrollView contentContainerStyle={styles.body}>
            <SectionLabel>{t('Line')}</SectionLabel>
            <ChipRow>
              <SelectionChip
                label={t('Unassigned')}
                active={lineId == null}
                onPress={() => setLineId(null)}
              />
              {lines.map((l) => (
                <SelectionChip
                  key={l.id}
                  label={l.code ?? l.name}
                  active={l.id === lineId}
                  onPress={() => setLineId(l.id)}
                />
              ))}
            </ChipRow>

            <View style={{ height: 12 }} />
            <SectionLabel>{t('When')}</SectionLabel>
            <Field
              label="Planned start (ISO)"
              value={startIso}
              onChangeText={setStartIso}
              autoCapitalize="none"
              autoCorrect={false}
              placeholder="2026-06-01T08:00:00"
              hint="ISO 8601 — minute precision (e.g. 2026-06-01T08:00:00)"
            />
            <Field
              label="Planned end (ISO)"
              value={endIso}
              onChangeText={setEndIso}
              autoCapitalize="none"
              autoCorrect={false}
              placeholder="2026-06-01T16:00:00"
              hint="Must be strictly after planned start"
            />

            <View style={{ height: 18 }} />
            <Button
              title="Save schedule"
              onPress={() => submit(false)}
              loading={update.isPending || resize.isPending}
              disabled={!startIso || !endIso}
              size="lg"
            />
            <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 12, textAlign: 'center' }}>
              {t('Long-press a bar on the planner to drag it. Use this modal for line changes and exact times.').toUpperCase()}
            </Mono>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
  sheet: {
    maxHeight: '85%',
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    overflow: 'hidden',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 10,
  },
  title: { fontSize: 18, fontWeight: '700', letterSpacing: -0.2, marginTop: 2 },
  closeBtn: { padding: 6 },
  body: { padding: 18, gap: 8, paddingBottom: 28 },
});
