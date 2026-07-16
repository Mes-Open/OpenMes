import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { ScrollView, StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { ActiveToggleCard } from '@/components/ui/ActiveToggleCard';
import { Card } from '@/components/ui/Card';
import { ControlledField } from '@/components/ui/ControlledField';
import { FormSubmitBar } from '@/components/ui/FormSubmitBar';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { nonEmpty } from '@/lib/forms/zod';
import type { Line } from '@/types/api';

export const lineSchema = z.object({
  code: nonEmpty(),
  name: nonEmpty(),
  description: z.string().trim(),
  is_active: z.boolean(),
});

export type LineFormValues = z.infer<typeof lineSchema>;

interface Props {
  initial?: Partial<Line>;
  mode: 'create' | 'edit';
  onSubmit: (values: LineFormValues) => void;
  onCancel?: () => void;
  onDelete?: () => void;
  submitting?: boolean;
  /** Counts shown above the active toggle in edit mode. */
  counts?: { workstations?: number; workers?: number; workOrders?: number };
}

const TABS = ['Details', 'Members', 'Products', 'Statuses', 'WS'];

export function LineForm({ initial, mode, onSubmit, onCancel, onDelete, submitting, counts }: Props) {
  const { t } = useTranslation();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const { control, handleSubmit, formState: { isValid } } = useForm<LineFormValues>({
    resolver: zodResolver(lineSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      {mode === 'edit' ? (
        // Tabs are visual placeholders for now — only Details is wired.
        // TODO(line-edit): wire Members / Products / Statuses / WS sub-screens.
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          style={[styles.tabsTrack, { backgroundColor: palette.surfaceAlt }]}
          contentContainerStyle={{ padding: 4, gap: 4 }}>
          {TABS.map((tab, i) => (
            <View
              key={tab}
              style={[
                styles.tab,
                i === 0 && {
                  backgroundColor: palette.surface,
                  boxShadow: '0px 1px 2px rgba(0, 0, 0, 0.06)',
                },
              ]}>
              <Mono
                size={10.5}
                color={i === 0 ? palette.text : palette.textMuted}
                weight="600"
                letterSpacing={0.6}>
                {t(tab).toUpperCase()}
              </Mono>
            </View>
          ))}
        </ScrollView>
      ) : null}

      <Card style={{ gap: 12 }}>
        <SectionLabel>Production line</SectionLabel>
        <View style={styles.codeNameRow}>
          <View style={{ width: 110 }}>
            <ControlledField
              control={control}
              name="code"
              label="CODE"
              required
              mono
              autoCapitalize="characters"
              autoCorrect={false}
              placeholder="L-04"
            />
          </View>
          <View style={{ flex: 1 }}>
            <ControlledField
              control={control}
              name="name"
              label="NAME"
              required
              placeholder="Pack & Ship"
            />
          </View>
        </View>
        <ControlledField
          control={control}
          name="description"
          label="DESCRIPTION"
          multiline
          numberOfLines={3}
          style={{ minHeight: 80, textAlignVertical: 'top', paddingTop: 12 }}
        />
      </Card>

      {mode === 'edit' && counts ? (
        <View style={styles.countsGrid}>
          {[
            { label: 'WORKSTATIONS', value: counts.workstations ?? 0 },
            { label: 'WORKERS', value: counts.workers ?? 0 },
            { label: 'WORK ORDERS', value: counts.workOrders ?? 0 },
          ].map((m) => (
            <View
              key={m.label}
              style={[
                styles.countTile,
                { backgroundColor: palette.surface, borderColor: palette.border },
              ]}>
              <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>
                {t(m.label)}
              </Mono>
              <Mono
                size={22}
                weight="600"
                color={palette.text}
                letterSpacing={-0.5}
                style={{ marginTop: 4, fontFamily: MONO }}>
                {String(m.value)}
              </Mono>
            </View>
          ))}
        </View>
      ) : null}

      <ActiveToggleCard
        control={control}
        name="is_active"
        description={
          counts?.workOrders
            ? `OPERATORS SEE THIS LINE · ${counts.workOrders} WO${counts.workOrders === 1 ? '' : 'S'} HIDDEN IF DISABLED`
            : "INACTIVE LINES ARE HIDDEN AND CAN'T ACCEPT NEW WORK ORDERS"
        }
      />

      <FormSubmitBar
        primary={mode === 'create' ? 'Create line' : 'Save changes'}
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
  tabsTrack: { borderRadius: 10, flexGrow: 0 },
  tab: { paddingVertical: 8, paddingHorizontal: 12, borderRadius: 7, alignItems: 'center' },
  codeNameRow: { flexDirection: 'row', gap: 10 },
  countsGrid: { flexDirection: 'row', gap: 8 },
  countTile: { flex: 1, padding: 12, borderRadius: 10, borderWidth: 1 },
});
