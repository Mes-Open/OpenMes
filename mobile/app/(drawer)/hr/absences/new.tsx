/**
 * Record worker absence (POST /api/v1/worker-absences) — worker, type, date range,
 * optional reason. Geist White, light-only v1.
 */
import { useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';
import { format } from 'date-fns';
import { useTranslation } from 'react-i18next';

import { DatePicker, Dropdown, colors } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { LoadingState } from '@/components/ui/StateViews';
import { useWorkers } from '@/hooks/queries/useHr';
import { useCreateWorkerAbsence } from '@/hooks/queries/useWorkerAbsences';
import { ABSENCE_TYPES, type AbsenceType } from '@/api/workerAbsences';

export default function NewWorkerAbsencePage() {
  const { t } = useTranslation();
  const router = useRouter();
  const workersQ = useWorkers({});
  const create = useCreateWorkerAbsence();

  const today = format(new Date(), 'yyyy-MM-dd');
  const [workerId, setWorkerId] = useState('');
  const [type, setType] = useState<AbsenceType>('vacation');
  const [startsOn, setStartsOn] = useState(today);
  const [endsOn, setEndsOn] = useState(today);
  const [reason, setReason] = useState('');

  const workers = workersQ.data?.data ?? [];
  const canSave = !!workerId && !!startsOn && !!endsOn;

  const onSave = () => {
    create.mutate(
      {
        worker_id: Number(workerId),
        type,
        starts_on: startsOn,
        ends_on: endsOn,
        reason: reason.trim() || undefined,
      },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not save absence'), e.message),
      },
    );
  };

  return (
    <View style={styles.screen}>
      <ScreenHeader back title="Record absence" subtitle="HR" />
      {workersQ.isLoading ? (
        <LoadingState />
      ) : (
        <ScrollView contentContainerStyle={styles.form}>
          <Labeled label={t('Worker')}>
            <Dropdown
              options={workers.map((w) => ({ value: String(w.id), label: w.name }))}
              value={workerId}
              onChange={(v) => setWorkerId(v as string)}
              placeholder={t('Select worker')}
            />
          </Labeled>

          <Labeled label={t('Type')}>
            <Dropdown
              options={ABSENCE_TYPES.map((t) => ({ value: t, label: t[0].toUpperCase() + t.slice(1) }))}
              value={type}
              onChange={(v) => setType(v as AbsenceType)}
            />
          </Labeled>

          <View style={styles.dateRow}>
            <Labeled label={t('Starts')} style={{ flex: 1 }}>
              <DatePicker value={startsOn} onChange={(v) => setStartsOn((v ?? today).slice(0, 10))} />
            </Labeled>
            <Labeled label={t('Ends')} style={{ flex: 1 }}>
              <DatePicker value={endsOn} onChange={(v) => setEndsOn((v ?? today).slice(0, 10))} />
            </Labeled>
          </View>

          <Field
            label="Reason"
            value={reason}
            onChangeText={setReason}
            placeholder="Optional note"
            multiline
          />

          <Button title="Record absence" onPress={onSave} loading={create.isPending} disabled={!canSave} />
        </ScrollView>
      )}
    </View>
  );
}

function Labeled({
  label,
  children,
  style,
}: {
  label: string;
  children: React.ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  return (
    <View style={style}>
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
  dateRow: { flexDirection: 'row', gap: 12 },
});
