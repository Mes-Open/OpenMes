import { useEffect, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  usePackagingChecklist,
  useSubmitPackagingChecklist,
} from '@/hooks/queries/useProductionControls';

interface Props {
  batchId: number;
}

interface Form {
  udi_readable: boolean;
  packaging_condition: boolean;
  labels_readable: boolean;
  label_matches_product: boolean;
}

const ITEMS: { key: keyof Form; label: string }[] = [
  { key: 'udi_readable', label: 'UDI readable' },
  { key: 'packaging_condition', label: 'Packaging condition OK' },
  { key: 'labels_readable', label: 'Labels readable' },
  { key: 'label_matches_product', label: 'Label matches product' },
];

export function PackagingChecklistCard({ batchId }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = usePackagingChecklist(batchId);
  const submit = useSubmitPackagingChecklist(batchId);

  const existing = query.data?.data ?? null;

  const [form, setForm] = useState<Form>({
    udi_readable: false,
    packaging_condition: false,
    labels_readable: false,
    label_matches_product: false,
  });
  const [notes, setNotes] = useState('');

  useEffect(() => {
    if (existing) {
      setForm({
        udi_readable: !!existing.udi_readable,
        packaging_condition: !!existing.packaging_condition,
        labels_readable: !!existing.labels_readable,
        label_matches_product: !!existing.label_matches_product,
      });
      setNotes(existing.notes ?? '');
    }
  }, [existing?.id]);

  const allChecked = ITEMS.every((it) => form[it.key]);
  const isComplete = !!query.data?.is_complete;
  const checked = ITEMS.filter((it) => form[it.key]).length;

  const onSubmit = () => {
    submit.mutate(
      { ...form, notes: notes || null },
      {
        onError: (e: Error) => Alert.alert('Submit failed', e.message),
      },
    );
  };

  return (
    <Card style={{ gap: 12 }}>
      <SectionLabel
        right={
          <Mono
            size={11}
            color={isComplete ? palette.success : palette.textFaint}
            weight="700"
            letterSpacing={0.6}>
            {isComplete ? 'COMPLETE' : `${checked}/${ITEMS.length}`}
          </Mono>
        }>
        Packaging checklist
      </SectionLabel>

      <View style={{ gap: 8 }}>
        {ITEMS.map((it) => {
          const checked = form[it.key];
          return (
            <Pressable
              key={it.key}
              disabled={isComplete}
              onPress={() => setForm((f) => ({ ...f, [it.key]: !f[it.key] }))}
              style={({ pressed }) => [
                styles.itemRow,
                {
                  backgroundColor: checked ? '#FAF0DD' : palette.surface,
                  borderColor: checked ? BRAND.amber : palette.border,
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <View
                style={[
                  styles.checkbox,
                  {
                    backgroundColor: checked ? BRAND.amber : 'transparent',
                    borderColor: checked ? BRAND.amber : palette.border,
                  },
                ]}>
                {checked ? <FontAwesome name="check" size={11} color="#1a1208" /> : null}
              </View>
              <Text
                style={{
                  color: checked ? '#8a5a0e' : palette.text,
                  flex: 1,
                  fontWeight: '600',
                  fontSize: 14,
                }}>
                {it.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {!isComplete ? (
        <>
          <Field
            label="Notes (optional)"
            value={notes}
            onChangeText={setNotes}
            multiline
            numberOfLines={2}
            style={{ minHeight: 60, textAlignVertical: 'top' }}
          />
          <Button
            title="Submit checklist"
            variant="success"
            onPress={onSubmit}
            disabled={!allChecked}
            loading={submit.isPending}
            leftIcon={<FontAwesome name="check" size={13} color="#fff" />}
          />
        </>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  itemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
