/**
 * Edit account — mirrors the web admin/users edit page: the UserForm (with its
 * own delete action) plus a reset-password section. Keeps all REST update /
 * delete / reset-password mutations and the self-account guard unchanged.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { UserForm } from '@/components/admin/UserForm';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useUser } from '@/hooks/queries/useUsers';
import {
  useDeleteUser,
  useResetUserPassword,
  useUpdateUser,
} from '@/hooks/mutations/users';
import { useAuthStore } from '@/stores/authStore';

export function UserDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const meId = useAuthStore((s) => s.user?.id);
  const userQuery = useUser(numericId);
  const updateMutation = useUpdateUser();
  const deleteMutation = useDeleteUser();
  const resetMutation = useResetUserPassword();

  const [newPassword, setNewPassword] = useState('');

  if (userQuery.isLoading) return <LoadingState />;
  if (userQuery.isError || !userQuery.data) {
    return <ErrorState error={userQuery.error} onRetry={userQuery.refetch} />;
  }

  const user = userQuery.data;
  const isSelf = meId === user.id;

  const onResetPassword = () => {
    if (newPassword.length < 8) return;
    resetMutation.mutate(
      { id: user.id, password: newPassword, force_password_change: true },
      {
        onSuccess: () => {
          setNewPassword('');
          Alert.alert(t('Password reset'), t('The user must change their password on next login.'));
        },
        onError: (e: Error) => Alert.alert(t('Could not reset password'), e.message),
      },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit account')}</Text>

      <UserForm
        mode="edit"
        initial={{
          name: user.name ?? '',
          username: user.username,
          email: user.email ?? '',
          account_type: user.account_type,
          force_password_change: user.force_password_change ?? false,
          line_ids: user.lines?.map((l) => l.id) ?? [],
          lines: user.lines,
          roles: user.roles,
        }}
        submitting={updateMutation.isPending}
        onSubmit={(values) =>
          updateMutation.mutate(
            {
              id: user.id,
              payload: {
                name: values.name,
                username: values.username,
                email: values.email,
                account_type: values.account_type,
                role: values.account_type === 'user' ? values.role : undefined,
                force_password_change: values.force_password_change,
                line_ids: values.line_ids,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
          )
        }
        onDelete={
          isSelf
            ? undefined
            : () =>
                deleteMutation.mutate(user.id, {
                  onSuccess: () => router.back(),
                  onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
                })
        }
      />

      <View style={styles.box}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Reset password').toUpperCase()}</Mono>
        <Mono size={9} color={colors.faint}>{t('Sets a new password and revokes all active sessions').toUpperCase()}</Mono>
        <Field
          label="New password"
          value={newPassword}
          onChangeText={setNewPassword}
          secureTextEntry
          placeholder="At least 8 characters"
        />
        <Button
          title={t('Reset password')}
          variant="outline"
          onPress={onResetPassword}
          loading={resetMutation.isPending}
          disabled={newPassword.length < 8}
        />
      </View>

      {isSelf ? (
        <View style={styles.warning}>
          <Text style={styles.warningText}>{t('You cannot delete your own account.')}</Text>
        </View>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  box: {
    gap: 10,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 14,
  },
  warning: {
    padding: 14,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.panel,
  },
  warningText: { color: colors.muted, fontSize: 13, textAlign: 'center', fontFamily: fonts.sans.native.regular },
});
