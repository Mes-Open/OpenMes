import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { ActiveToggleCard } from '@/components/ui/ActiveToggleCard';
import { Card } from '@/components/ui/Card';
import { ControlledField } from '@/components/ui/ControlledField';
import { FormSubmitBar } from '@/components/ui/FormSubmitBar';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { nonEmpty } from '@/lib/forms/zod';

export const categorizedSchema = z.object({
  code: nonEmpty(),
  name: nonEmpty(),
  category: z.string().trim(),
  description: z.string().trim(),
  is_active: z.boolean(),
});

export type CategorizedFormValues = z.infer<typeof categorizedSchema>;

interface Props {
  initial?: Partial<CategorizedFormValues>;
  mode: 'create' | 'edit';
  entityLabel: string;
  onSubmit: (values: CategorizedFormValues) => void;
  onCancel?: () => void;
  onDelete?: () => void;
  submitting?: boolean;
}

export function CategorizedCodeNameForm({
  initial,
  mode,
  entityLabel,
  onSubmit,
  onCancel,
  onDelete,
  submitting,
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const { control, handleSubmit, formState: { isValid } } = useForm<CategorizedFormValues>({
    resolver: zodResolver(categorizedSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      category: initial?.category ?? '',
      description: initial?.description ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <View style={[styles.note, { backgroundColor: palette.surfaceAlt }]}>
        <Mono size={11} color={palette.textMuted} letterSpacing={0.3}>
          Same shape used for Anomaly reason · Cost source · Company · Subassembly · LOT sequence — code, name, optional fields, active toggle.
        </Mono>
      </View>

      <Card style={{ gap: 12 }}>
        <SectionLabel>{entityLabel}</SectionLabel>
        <View style={styles.codeNameRow}>
          <View style={{ width: 120 }}>
            <ControlledField
              control={control}
              name="code"
              label="CODE"
              required
              mono
              autoCapitalize="characters"
              autoCorrect={false}
            />
          </View>
          <View style={{ flex: 1 }}>
            <ControlledField control={control} name="name" label="NAME" required />
          </View>
        </View>
        <ControlledField
          control={control}
          name="category"
          label="CATEGORY"
          placeholder="e.g. quality, process"
        />
        <ControlledField
          control={control}
          name="description"
          label="DESCRIPTION"
          multiline
          numberOfLines={3}
          style={{ minHeight: 80, textAlignVertical: 'top', paddingTop: 12 }}
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <FormSubmitBar
        primary={mode === 'create' ? `Create ${entityLabel.toLowerCase()}` : 'Save changes'}
        secondary={onCancel ? 'Cancel' : undefined}
        onPrimary={handleSubmit(onSubmit)}
        onSecondary={onCancel}
        onDestructive={mode === 'edit' ? onDelete : undefined}
        loading={!!submitting}
        disabled={!isValid}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  note: { padding: 12, borderRadius: 10 },
  codeNameRow: { flexDirection: 'row', gap: 10 },
});
