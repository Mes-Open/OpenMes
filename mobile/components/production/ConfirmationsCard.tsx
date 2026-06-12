import { useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useConfirmationStatus,
  useCreateConfirmation,
  useProcessConfirmations,
} from '@/hooks/queries/useProductionControls';
import type { ConfirmationType } from '@/api/productionControls';

interface Props {
  batchId: number;
}

const TYPES: { key: ConfirmationType; label: string }[] = [
  { key: 'parameters', label: 'Parameters' },
  { key: 'drying', label: 'Drying' },
  { key: 'custom', label: 'Custom' },
];

export function ConfirmationsCard({ batchId }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const status = useConfirmationStatus(batchId);
  const list = useProcessConfirmations(batchId);
  const create = useCreateConfirmation(batchId);

  const [open, setOpen] = useState(false);
  const [type, setType] = useState<ConfirmationType>('parameters');
  const [value, setValue] = useState('');
  const [notes, setNotes] = useState('');

  const reset = () => {
    setOpen(false);
    setType('parameters');
    setValue('');
    setNotes('');
  };

  const submit = () => {
    if (type === 'drying' && (!value || !Number.isFinite(Number(value)))) {
      Alert.alert('Drying hours required', 'Enter a numeric hours value.');
      return;
    }
    create.mutate(
      {
        confirmation_type: type,
        notes: notes || null,
        value: value || null,
      },
      {
        onSuccess: reset,
        onError: (e: Error) => Alert.alert('Confirmation failed', e.message),
      },
    );
  };

  const total = status.data?.total_confirmations ?? list.data?.length ?? 0;
  const today = !!status.data?.confirmed_today;

  return (
    <Card style={{ gap: 12 }}>
      <SectionLabel
        right={
          <View
            style={[
              styles.statusPill,
              { backgroundColor: today ? palette.successSoft : palette.surfaceAlt },
            ]}>
            <View style={[styles.dot, { backgroundColor: today ? palette.success : palette.textFaint }]} />
            <Mono size={10} color={today ? palette.success : palette.textFaint} letterSpacing={0.6} weight="700">
              {today ? `CONFIRMED TODAY · ${total}` : `NOT CONFIRMED · ${total}`}
            </Mono>
          </View>
        }>
        Process confirmations
      </SectionLabel>

      {open ? (
        <View style={{ gap: 10 }}>
          <View style={styles.chipsRow}>
            {TYPES.map((t) => {
              const active = t.key === type;
              return (
                <Pressable
                  key={t.key}
                  onPress={() => setType(t.key)}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: active ? '#FAF0DD' : palette.surface,
                      borderColor: active ? BRAND.amber : palette.border,
                    },
                  ]}>
                  <Text
                    style={{
                      fontSize: 12,
                      fontWeight: '600',
                      color: active ? '#8a5a0e' : palette.text,
                    }}>
                    {t.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          <Field
            label={type === 'drying' ? 'Hours' : 'Value (optional)'}
            value={value}
            onChangeText={setValue}
            keyboardType={type === 'drying' ? 'number-pad' : 'default'}
            placeholder={type === 'drying' ? 'e.g. 12' : ''}
          />
          <Field
            label="Notes (optional)"
            value={notes}
            onChangeText={setNotes}
            multiline
            numberOfLines={2}
            style={{ minHeight: 60, textAlignVertical: 'top' }}
          />
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Button
              title="Confirm"
              onPress={submit}
              loading={create.isPending}
              style={{ flex: 1 }}
              leftIcon={<FontAwesome name="check" size={13} color="#1a1208" />}
            />
            <Button title="Cancel" variant="outline" onPress={reset} style={{ flex: 1 }} />
          </View>
        </View>
      ) : (
        <Button
          title="Add confirmation"
          variant="outline"
          onPress={() => setOpen(true)}
          leftIcon={<FontAwesome name="plus" size={13} color={palette.text} />}
        />
      )}
    </Card>
  );
}

const styles = StyleSheet.create({
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 999,
  },
  dot: { width: 6, height: 6, borderRadius: 3 },
  chipsRow: { gap: 8, flexDirection: 'row', flexWrap: 'wrap' },
  chip: {
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
});
