import { FontAwesome } from '@expo/vector-icons';
import { useEffect, useMemo } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { z } from 'zod';

import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { FormSubmitBar } from '@/components/ui/FormSubmitBar';
import { Switch } from '@/components/ui/Switch';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useLines, useRoles } from '@/hooks/queries/useUsers';
import { nonEmpty } from '@/lib/forms/zod';
import type { User } from '@/types/api';

const makeSchema = (mode: 'create' | 'edit') =>
  z.object({
    name: nonEmpty(),
    username: nonEmpty(),
    email: nonEmpty(),
    account_type: z.enum(['user', 'workstation']),
    role: z.string(),
    force_password_change: z.boolean(),
    line_ids: z.array(z.number()),
    password: mode === 'create' ? z.string().min(8, 'At least 8 characters') : z.string(),
  });

export type UserFormValues = z.infer<ReturnType<typeof makeSchema>>;

interface Props {
  initial?: Partial<UserFormValues> & Partial<Pick<User, 'lines' | 'roles' | 'account_type'>>;
  mode: 'create' | 'edit';
  onSubmit: (values: UserFormValues) => void;
  onCancel?: () => void;
  onDelete?: () => void;
  submitting?: boolean;
}

const ROLE_DESCRIPTIONS: Record<string, string> = {
  Operator: 'Run batches',
  Supervisor: 'Triage',
  Admin: 'Full access',
};

export function UserForm({ initial, mode, onSubmit, onCancel, onDelete, submitting }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const roles = useRoles();
  const lines = useLines();

  const initialRole = useMemo(
    () => initial?.role ?? initial?.roles?.[0]?.name ?? 'Operator',
    [initial],
  );
  const initialLines = useMemo(
    () => initial?.line_ids ?? initial?.lines?.map((l) => l.id) ?? [],
    [initial],
  );

  const schema = useMemo(() => makeSchema(mode), [mode]);

  const { control, handleSubmit, setValue, watch, formState: { isValid } } = useForm<UserFormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      name: initial?.name ?? '',
      username: initial?.username ?? '',
      email: initial?.email ?? '',
      password: '',
      account_type: initial?.account_type ?? 'user',
      role: initialRole,
      force_password_change: initial?.force_password_change ?? false,
      line_ids: initialLines,
    },
  });

  useEffect(() => {
    setValue('line_ids', initialLines);
  }, [initialLines, setValue]);

  const accountType = watch('account_type');

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Identity</SectionLabel>
        <ControlledField control={control} name="name" label="DISPLAY_NAME" required placeholder="Karolina Wójcik" />
        <ControlledField
          control={control}
          name="username"
          label="USERNAME"
          required
          mono
          autoCapitalize="none"
          autoCorrect={false}
          hint="Lowercase, no spaces. Must be unique."
        />
        <ControlledField
          control={control}
          name="email"
          label="EMAIL"
          required
          mono
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Account type</SectionLabel>
        <Controller
          control={control}
          name="account_type"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {(['user', 'workstation'] as const).map((t) => (
                <SelectionChip
                  key={t}
                  label={t === 'user' ? 'Personal account' : 'Workstation kiosk'}
                  active={t === value}
                  onPress={() => onChange(t)}
                />
              ))}
            </ChipRow>
          )}
        />

        {accountType === 'user' ? (
          <View style={{ gap: 8 }}>
            <SectionLabel>Role</SectionLabel>
            <Controller
              control={control}
              name="role"
              render={({ field: { value, onChange } }) => (
                <View style={styles.roleGrid}>
                  {(roles.data ?? []).map((r) => {
                    const active = r.name === value;
                    const sub =
                      ROLE_DESCRIPTIONS[r.name] ?? (r.name === 'Admin' ? 'Full access' : '');
                    return (
                      <Pressable
                        key={r.id}
                        onPress={() => onChange(r.name)}
                        style={[
                          styles.roleTile,
                          {
                            backgroundColor: active ? '#1d1d22' : palette.surface,
                            borderColor: active ? palette.text : palette.border,
                          },
                        ]}>
                        <Mono
                          size={12}
                          weight="700"
                          letterSpacing={0.4}
                          color={active ? '#ffffff' : palette.text}>
                          {r.name.toUpperCase()}
                        </Mono>
                        {sub ? (
                          <Mono
                            size={9.5}
                            color={active ? '#9a9aa2' : palette.textFaint}
                            letterSpacing={0.5}
                            style={{ marginTop: 4 }}>
                            {sub}
                          </Mono>
                        ) : null}
                      </Pressable>
                    );
                  })}
                </View>
              )}
            />
          </View>
        ) : null}
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel
          right={
            <Mono size={11} color={palette.textFaint}>
              {(lines.data?.length ?? 0)} AVAILABLE
            </Mono>
          }>
          Line assignments
        </SectionLabel>
        {lines.isLoading ? (
          <Mono size={11} color={palette.textFaint}>LOADING LINES…</Mono>
        ) : (lines.data ?? []).length === 0 ? (
          <Mono size={11} color={palette.textFaint}>NO LINES EXIST YET</Mono>
        ) : (
          <Controller
            control={control}
            name="line_ids"
            render={({ field: { value, onChange } }) => (
              <ChipRow>
                {(lines.data ?? []).map((l) => {
                  const active = value.includes(l.id);
                  return (
                    <SelectionChip
                      key={l.id}
                      label={l.name}
                      active={active}
                      onPress={() =>
                        onChange(active ? value.filter((x) => x !== l.id) : [...value, l.id])
                      }
                    />
                  );
                })}
              </ChipRow>
            )}
          />
        )}
      </Card>

      {mode === 'create' ? (
        <>
          <SectionLabel>Credentials</SectionLabel>
          <View style={[styles.tempPwCard, { backgroundColor: '#fbe9c8', borderColor: '#e8c179' }]}>
            <FontAwesome name="key" size={16} color="#a8650a" style={{ marginTop: 2 }} />
            <View style={{ flex: 1 }}>
              <Text style={styles.tempPwBody}>
                Server will generate a temp password and force change on first login.
              </Text>
              <ControlledField
                control={control}
                name="password"
                label="TEMP_PASSWORD"
                mono
                secureTextEntry
                placeholder="At least 8 characters"
              />
            </View>
          </View>
          <Card>
            <View style={styles.toggleRow}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.toggleTitle, { color: palette.text }]}>Send setup email</Text>
                <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
                  EMAIL PASSWORD + LOGIN URL ON CREATE
                </Mono>
              </View>
              <Controller
                control={control}
                name="force_password_change"
                render={({ field: { value, onChange } }) => (
                  <Switch value={value} onValueChange={onChange} />
                )}
              />
            </View>
          </Card>
        </>
      ) : (
        <Card>
          <View style={styles.toggleRow}>
            <View style={{ flex: 1 }}>
              <Text style={[styles.toggleTitle, { color: palette.text }]}>
                Force password change
              </Text>
              <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
                USER MUST CHANGE PASSWORD ON NEXT LOGIN
              </Mono>
            </View>
            <Controller
              control={control}
              name="force_password_change"
              render={({ field: { value, onChange } }) => (
                <Switch value={value} onValueChange={onChange} />
              )}
            />
          </View>
        </Card>
      )}

      <FormSubmitBar
        primary={mode === 'create' ? 'Create user' : 'Save changes'}
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
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  toggleTitle: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  roleGrid: { flexDirection: 'row', gap: 6, flexWrap: 'wrap' },
  roleTile: {
    flexGrow: 1,
    flexBasis: '30%',
    minHeight: 64,
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
  tempPwCard: {
    padding: 14,
    borderRadius: 10,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 10,
  },
  tempPwBody: { fontSize: 12, color: '#7a4906', lineHeight: 18, marginBottom: 8 },
});
