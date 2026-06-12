import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';

import { UserForm } from '@/components/admin/UserForm';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { DetailScreen } from '@/components/ui/Detail';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

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
          Alert.alert('Password reset', 'The user must change their password on next login.');
        },
        onError: (e: Error) => Alert.alert('Could not reset password', e.message),
      },
    );
  };

  return (
    <DetailScreen>
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
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
        onDelete={
          isSelf
            ? undefined
            : () =>
                deleteMutation.mutate(user.id, {
                  onSuccess: () => router.back(),
                  onError: (e: Error) => Alert.alert('Could not delete', e.message),
                })
        }
      />

      <Card style={{ gap: 12 }}>
        <SectionLabel>Reset password</SectionLabel>
        <Mono size={11} color={palette.textFaint}>
          SETS A NEW PASSWORD AND REVOKES ALL ACTIVE SESSIONS
        </Mono>
        <Field
          label="New password"
          value={newPassword}
          onChangeText={setNewPassword}
          secureTextEntry
          placeholder="At least 8 characters"
        />
        <Button
          title="Reset password"
          variant="outline"
          onPress={onResetPassword}
          loading={resetMutation.isPending}
          disabled={newPassword.length < 8}
        />
      </Card>

      {isSelf ? (
        <View
          style={[styles.warning, { backgroundColor: palette.surfaceAlt, borderColor: palette.border }]}>
          <Text style={{ color: palette.textMuted, fontSize: 13, textAlign: 'center' }}>
            You cannot delete your own account.
          </Text>
        </View>
      ) : null}
    </DetailScreen>
  );
}

const styles = StyleSheet.create({
  warning: { padding: 14, borderRadius: 12, borderWidth: 1 },
});
