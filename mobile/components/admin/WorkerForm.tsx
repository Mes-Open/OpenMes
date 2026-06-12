import { FontAwesome } from '@expo/vector-icons';
import { useEffect, useMemo, useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { z } from 'zod';

import { Card } from '@/components/ui/Card';
import { ControlledField } from '@/components/ui/ControlledField';
import { Field } from '@/components/ui/Field';
import { FormSubmitBar } from '@/components/ui/FormSubmitBar';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCrews, useSkills, useWageGroups } from '@/hooks/queries/useHr';
import { nonEmpty } from '@/lib/forms/zod';
import type { Worker } from '@/api/hr';

export const workerSchema = z.object({
  code: nonEmpty(),
  first_name: nonEmpty(),
  last_name: nonEmpty(),
  email: z.string().trim(),
  phone: z.string().trim(),
  crew_id: z.number().nullable(),
  wage_group_id: z.number().nullable(),
  is_active: z.boolean(),
  /** Skill IDs only — design dropped per-skill level. We keep level=3 default
   * server-side until the Skills table re-introduces a level UI. */
  skill_ids: z.array(z.number()),
});

type FormValues = z.infer<typeof workerSchema>;

/** Submit shape exposed to callers — preserves the previous {id, level} array
 * for skills so existing screens keep working. */
export interface WorkerFormValues {
  code: string;
  name: string;
  email: string;
  phone: string;
  crew_id: number | null;
  wage_group_id: number | null;
  is_active: boolean;
  skills: { id: number; level: number }[];
}

interface Props {
  initial?: Partial<Worker>;
  mode: 'create' | 'edit';
  onSubmit: (values: WorkerFormValues) => void;
  onCancel?: () => void;
  onDelete?: () => void;
  submitting?: boolean;
}

function splitName(full: string): { first: string; last: string } {
  const trimmed = full.trim();
  if (!trimmed) return { first: '', last: '' };
  const idx = trimmed.indexOf(' ');
  if (idx === -1) return { first: trimmed, last: '' };
  return { first: trimmed.slice(0, idx), last: trimmed.slice(idx + 1).trim() };
}

export function WorkerForm({ initial, mode, onSubmit, onCancel, onDelete, submitting }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const crewsQuery = useCrews(false);
  const wageGroupsQuery = useWageGroups(false);
  const skillsQuery = useSkills();

  const initialNames = useMemo(() => splitName(initial?.name ?? ''), [initial?.name]);

  const { control, handleSubmit, watch, setValue, formState: { isValid } } = useForm<FormValues>({
    resolver: zodResolver(workerSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      first_name: initialNames.first,
      last_name: initialNames.last,
      email: initial?.email ?? '',
      phone: initial?.phone ?? '',
      crew_id: initial?.crew_id ?? null,
      wage_group_id: initial?.wage_group_id ?? null,
      is_active: initial?.is_active ?? true,
      skill_ids: initial?.skills?.map((s) => s.id) ?? [],
    },
  });

  useEffect(() => {
    if (initial?.skills) {
      setValue('skill_ids', initial.skills.map((s) => s.id));
    }
  }, [initial?.skills, setValue]);

  const crewId = watch('crew_id');
  const wageGroupId = watch('wage_group_id');
  const skillIds = watch('skill_ids');

  const selectedCrew = (crewsQuery.data ?? []).find((c) => c.id === crewId);
  const selectedWage = (wageGroupsQuery.data ?? []).find((w) => w.id === wageGroupId);

  const [crewPicker, setCrewPicker] = useState(false);
  const [wagePicker, setWagePicker] = useState(false);

  const submit = (v: FormValues) => {
    onSubmit({
      code: v.code,
      name: [v.first_name, v.last_name].filter(Boolean).join(' '),
      email: v.email,
      phone: v.phone,
      crew_id: v.crew_id,
      wage_group_id: v.wage_group_id,
      is_active: v.is_active,
      skills: v.skill_ids.map((id) => ({ id, level: 3 })),
    });
  };

  return (
    <View style={{ gap: 14 }}>
      {/* IDENTITY */}
      <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>IDENTITY</Mono>
      <ControlledField
        control={control}
        name="code"
        label="EMPLOYEE_NO"
        required
        mono
        autoCapitalize="characters"
        autoCorrect={false}
        hint="Auto-generated from LOT sequence."
      />
      <View style={styles.colRow}>
        <View style={{ flex: 1 }}>
          <ControlledField control={control} name="first_name" label="FIRST_NAME" required placeholder="Karolina" />
        </View>
        <View style={{ flex: 1 }}>
          <ControlledField control={control} name="last_name" label="LAST_NAME" required placeholder="Wójcik" />
        </View>
      </View>
      <ControlledField control={control} name="email" label="EMAIL" keyboardType="email-address" autoCapitalize="none" mono />
      <ControlledField control={control} name="phone" label="PHONE" keyboardType="phone-pad" mono />

      {/* ASSIGNMENT */}
      <Mono size={11} color={palette.textFaint} letterSpacing={0.8} style={{ marginTop: 4 }}>
        ASSIGNMENT
      </Mono>

      <PickerField
        label="CREW_ID"
        required
        value={selectedCrew?.name ?? '— None —'}
        hint={selectedCrew ? 'Tap to change' : 'Tap to assign a crew'}
        onPress={() => setCrewPicker(true)}
      />

      <PickerField
        label="WAGE_GROUP"
        value={
          selectedWage
            ? `${selectedWage.name}${selectedWage.base_hourly_rate ? ` · ${selectedWage.base_hourly_rate} ${selectedWage.currency ?? 'PLN'}/h` : ''}`
            : '— None —'
        }
        mono
        hint="Tap to change"
        onPress={() => setWagePicker(true)}
      />

      {/* SKILLS — flat chip wrap, dark-fill when picked */}
      <Field
        // Render-only — uses Field for label consistency.
        label="SKILLS"
        value=""
        editable={false}
        style={{ display: 'none' }}
      />
      <View style={styles.skillsWrap}>
        {(skillsQuery.data ?? []).map((sk) => {
          const picked = skillIds.includes(sk.id);
          return (
            <Pressable
              key={sk.id}
              onPress={() => {
                setValue(
                  'skill_ids',
                  picked ? skillIds.filter((id) => id !== sk.id) : [...skillIds, sk.id],
                  { shouldValidate: true },
                );
              }}
              style={[
                styles.skillChip,
                {
                  backgroundColor: picked ? palette.text : palette.surface,
                  borderColor: picked ? palette.text : palette.border,
                },
              ]}>
              <Text
                style={{
                  fontFamily: MONO,
                  fontSize: 11,
                  fontWeight: '600',
                  letterSpacing: 0.4,
                  color: picked ? (scheme === 'dark' ? '#171715' : '#ffffff') : palette.text,
                }}>
                {sk.name}
              </Text>
            </Pressable>
          );
        })}
        {(skillsQuery.data ?? []).length === 0 ? (
          <Mono size={11} color={palette.textFaint}>NO SKILLS DEFINED YET</Mono>
        ) : null}
      </View>

      {/* Active toggle */}
      <Card>
        <View style={styles.toggleRow}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.toggleTitle, { color: palette.text }]}>Active</Text>
            <Text style={[styles.toggleSub, { color: palette.textMuted }]}>
              Show in operator dropdowns and crew rosters
            </Text>
          </View>
          <Controller
            control={control}
            name="is_active"
            render={({ field: { value, onChange } }) => (
              <Switch value={!!value} onValueChange={onChange} />
            )}
          />
        </View>
      </Card>

      <FormSubmitBar
        primary={mode === 'create' ? 'Create worker' : 'Save changes'}
        secondary={onCancel ? 'Cancel' : undefined}
        onPrimary={handleSubmit(submit)}
        onSecondary={onCancel}
        onDestructive={mode === 'edit' ? onDelete : undefined}
        loading={!!submitting}
        disabled={!isValid}
      />

      <PickerSheet
        title="Pick a crew"
        open={crewPicker}
        onClose={() => setCrewPicker(false)}
        items={[
          { id: null as number | null, label: '— None —' },
          ...(crewsQuery.data ?? []).map((c) => ({
            id: c.id,
            label: c.name,
            sub: c.code ?? undefined,
          })),
        ]}
        selectedId={crewId}
        onSelect={(id) => {
          setValue('crew_id', id, { shouldValidate: true });
          setCrewPicker(false);
        }}
      />

      <PickerSheet
        title="Pick a wage group"
        open={wagePicker}
        onClose={() => setWagePicker(false)}
        items={[
          { id: null as number | null, label: '— None —' },
          ...(wageGroupsQuery.data ?? []).map((w) => ({
            id: w.id,
            label: w.name,
            sub: w.base_hourly_rate ? `${w.base_hourly_rate} ${w.currency ?? 'PLN'}/h` : undefined,
          })),
        ]}
        selectedId={wageGroupId}
        onSelect={(id) => {
          setValue('wage_group_id', id, { shouldValidate: true });
          setWagePicker(false);
        }}
      />
    </View>
  );
}

function PickerField({
  label,
  value,
  hint,
  required,
  mono,
  onPress,
}: {
  label: string;
  value: string;
  hint?: string;
  required?: boolean;
  mono?: boolean;
  onPress: () => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View style={{ gap: 6 }}>
      <View style={styles.labelRow}>
        <Mono size={10.5} color={palette.textFaint} weight="600" letterSpacing={0.7}>
          {label}
          {required ? <Text style={{ color: palette.danger }}>{' *'}</Text> : null}
        </Mono>
      </View>
      <Pressable
        onPress={onPress}
        style={({ pressed }) => [
          styles.pickerInput,
          {
            backgroundColor: palette.surface,
            borderColor: palette.border,
            opacity: pressed ? 0.85 : 1,
          },
        ]}>
        <Text
          style={[
            styles.pickerValue,
            { color: palette.text, fontFamily: mono ? MONO : undefined },
          ]}
          numberOfLines={1}>
          {value}
        </Text>
        <FontAwesome name="chevron-right" size={11} color={palette.textFaint} />
      </Pressable>
      {hint ? (
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.3}>
          {hint}
        </Mono>
      ) : null}
    </View>
  );
}

interface PickerItem {
  id: number | null;
  label: string;
  sub?: string;
}

function PickerSheet({
  title,
  open,
  onClose,
  items,
  selectedId,
  onSelect,
}: {
  title: string;
  open: boolean;
  onClose: () => void;
  items: PickerItem[];
  selectedId: number | null;
  onSelect: (id: number | null) => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  return (
    <Modal animationType="slide" transparent visible={open} onRequestClose={onClose}>
      <Pressable style={styles.modalBackdrop} onPress={onClose}>
        <Pressable
          style={[styles.modalSheet, { backgroundColor: palette.background, borderColor: palette.border }]}
          onPress={(e) => e.stopPropagation?.()}>
          <View style={styles.modalHandle} />
          <Mono size={11} color={palette.textFaint} letterSpacing={0.8} style={{ marginBottom: 10 }}>
            {title.toUpperCase()}
          </Mono>
          <ScrollView style={{ maxHeight: 360 }}>
            {items.map((it) => {
              const sel = it.id === selectedId;
              return (
                <Pressable
                  key={String(it.id)}
                  onPress={() => onSelect(it.id)}
                  style={({ pressed }) => [
                    styles.pickerRow,
                    {
                      backgroundColor: sel ? palette.surfaceAlt : palette.surface,
                      borderColor: sel ? palette.text : palette.border,
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}>
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontSize: 14, fontWeight: '500', color: palette.text }}>
                      {it.label}
                    </Text>
                    {it.sub ? (
                      <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 2 }}>
                        {it.sub}
                      </Mono>
                    ) : null}
                  </View>
                  {sel ? (
                    <FontAwesome name="check" size={14} color={palette.text} />
                  ) : null}
                </Pressable>
              );
            })}
          </ScrollView>
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  colRow: { flexDirection: 'row', gap: 10 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  toggleTitle: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  toggleSub: { fontSize: 12, marginTop: 3, lineHeight: 16 },
  skillsWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  skillChip: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
  labelRow: { flexDirection: 'row', alignItems: 'baseline', justifyContent: 'space-between' },
  pickerInput: {
    minHeight: 48,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 10,
    borderWidth: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  pickerValue: { flex: 1, fontSize: 14 },
  modalBackdrop: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.4)',
  },
  modalSheet: {
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    padding: 16,
    paddingBottom: 28,
    borderTopWidth: 1,
    maxHeight: '80%',
  },
  modalHandle: {
    alignSelf: 'center',
    width: 36,
    height: 4,
    borderRadius: 2,
    backgroundColor: '#cfccc4',
    marginBottom: 12,
  },
  pickerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 10,
    borderWidth: 1,
    marginVertical: 4,
  },
});
