/**
 * New crew break window (POST /api/v1/crew-break-windows) — crew, name, time
 * range (HH:MM), days of week. Geist White, light-only v1.
 */
import { useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { LoadingState } from '@/components/ui/StateViews';
import { useCrewsList } from '@/hooks/queries/useHr';
import { useCreateCrewBreakWindow } from '@/hooks/queries/useCrewBreakWindows';

const DAYS = [
  { n: 1, l: 'Mon' },
  { n: 2, l: 'Tue' },
  { n: 3, l: 'Wed' },
  { n: 4, l: 'Thu' },
  { n: 5, l: 'Fri' },
  { n: 6, l: 'Sat' },
  { n: 7, l: 'Sun' },
];
const TIME_RE = /^([01]\d|2[0-3]):[0-5]\d$/;

export default function NewCrewBreakWindowPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const crewsQ = useCrewsList();
  const create = useCreateCrewBreakWindow();

  const [crewId, setCrewId] = useState('');
  const [name, setName] = useState('');
  const [start, setStart] = useState('12:00');
  const [end, setEnd] = useState('12:30');
  const [days, setDays] = useState<number[]>([1, 2, 3, 4, 5]);

  const crews = crewsQ.data ?? [];
  const valid =
    !!crewId && !!name.trim() && TIME_RE.test(start) && TIME_RE.test(end) && days.length > 0;

  const toggleDay = (n: number) =>
    setDays((d) => (d.includes(n) ? d.filter((x) => x !== n) : [...d, n]));

  const onSave = () => {
    create.mutate(
      {
        crew_id: Number(crewId),
        name: name.trim(),
        start_time: start,
        end_time: end,
        days_of_week: [...days].sort((a, b) => a - b),
      },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not save break window'), e.message),
      },
    );
  };

  return (
    <View style={styles.screen}>
      <ScreenHeader back title="New break window" subtitle="HR" />
      {crewsQ.isLoading ? (
        <LoadingState />
      ) : (
        <ScrollView contentContainerStyle={styles.form}>
          <Labeled label={t('Crew')}>
            <Dropdown
              options={crews.map((c) => ({ value: String(c.id), label: c.name }))}
              value={crewId}
              onChange={(v) => setCrewId(v as string)}
              placeholder={t('Select crew')}
            />
          </Labeled>

          <Field label="Name" value={name} onChangeText={setName} placeholder="e.g. Lunch" />

          <View style={styles.timeRow}>
            <View style={{ flex: 1 }}>
              <Field label="Start" value={start} onChangeText={setStart} placeholder="HH:MM" mono />
            </View>
            <View style={{ flex: 1 }}>
              <Field label="End" value={end} onChangeText={setEnd} placeholder="HH:MM" mono />
            </View>
          </View>

          <Labeled label={t('Days')}>
            <View style={styles.dayRow}>
              {DAYS.map((d) => {
                const on = days.includes(d.n);
                return (
                  <Pressable
                    key={d.n}
                    onPress={() => toggleDay(d.n)}
                    style={[styles.dayChip, on ? styles.dayChipOn : null]}>
                    <Mono size={11} color={on ? '#FFFFFF' : colors.muted} weight="600">
                      {t(d.l)}
                    </Mono>
                  </Pressable>
                );
              })}
            </View>
          </Labeled>

          <Button
            title="Create break window"
            onPress={onSave}
            loading={create.isPending}
            disabled={!valid}
          />
        </ScrollView>
      )}
    </View>
  );
}

function Labeled({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <View>
      <Mono size={9} color={colors.faint} letterSpacing={1} style={{ marginBottom: 7 }}>
        {label.toUpperCase()}
      </Mono>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  form: { padding: 18, gap: 16, maxWidth: 680, width: '100%', alignSelf: 'center' },
  timeRow: { flexDirection: 'row', gap: 12 },
  dayRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  dayChip: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.card,
  },
  dayChipOn: { backgroundColor: colors.ink, borderColor: colors.ink },
});
