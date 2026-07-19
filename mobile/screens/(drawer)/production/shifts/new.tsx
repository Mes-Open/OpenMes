import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { Switch } from '@/components/ui/Switch';
import { useCreateShift } from '@/hooks/queries/useOps';
import { useLines } from '@/hooks/queries/useUsers';

const TIME_RE = /^([01]\d|2[0-3]):[0-5]\d$/;
const DAYS = [
  { num: 1, label: 'Mon' },
  { num: 2, label: 'Tue' },
  { num: 3, label: 'Wed' },
  { num: 4, label: 'Thu' },
  { num: 5, label: 'Fri' },
  { num: 6, label: 'Sat' },
  { num: 7, label: 'Sun' },
];

export function NewShiftScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const m = useCreateShift();
  const linesQuery = useLines();

  const [name, setName] = useState('');
  const [startTime, setStartTime] = useState('06:00');
  const [endTime, setEndTime] = useState('14:00');
  const [days, setDays] = useState<number[]>([1, 2, 3, 4, 5]);
  const [lineId, setLineId] = useState<number | null>(null);
  const [isActive, setIsActive] = useState(true);

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    if (!TIME_RE.test(startTime)) e.startTime = 'HH:mm';
    if (!TIME_RE.test(endTime)) e.endTime = 'HH:mm';
    return e;
  }, [name, startTime, endTime]);

  const toggleDay = (n: number) =>
    setDays((cur) => (cur.includes(n) ? cur.filter((x) => x !== n) : [...cur, n].sort()));

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    m.mutate(
      {
        name: name.trim(),
        start_time: startTime,
        end_time: endTime,
        days_of_week: days.length ? days : undefined,
        line_id: lineId ?? undefined,
        is_active: isActive,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New shift')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="e.g. Morning, Night" />
      <Field label="Start time" value={startTime} onChangeText={setStartTime} error={errors.startTime} required autoCorrect={false} mono placeholder="HH:mm" />
      <Field label="End time" value={endTime} onChangeText={setEndTime} error={errors.endTime} required autoCorrect={false} mono placeholder="HH:mm" />

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Days of week').toUpperCase()}</Mono>
        <ChipRow>
          {DAYS.map((d) => (
            <SelectionChip key={d.num} label={t(d.label)} active={days.includes(d.num)} onPress={() => toggleDay(d.num)} />
          ))}
        </ChipRow>
      </View>

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Line').toUpperCase()}</Mono>
        <Dropdown
          value={lineId == null ? '' : String(lineId)}
          onChange={(v) => setLineId(v ? Number(v) : null)}
          placeholder={t('All lines')}
          options={[{ value: '', label: t('All lines') }, ...(linesQuery.data ?? []).map((l) => ({ value: String(l.id), label: l.name }))]}
        />
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={m.isPending} disabled={m.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
