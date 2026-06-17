import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { UserForm } from '@/components/admin/UserForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateUser } from '@/hooks/mutations/users';

export function NewUserScreen() {
  const router = useRouter();
  const createMutation = useCreateUser();

  return (
    <DetailScreen>
      <UserForm
        mode="create"
        submitting={createMutation.isPending}
        onSubmit={(values) =>
          createMutation.mutate(
            {
              name: values.name,
              username: values.username,
              email: values.email,
              password: values.password,
              account_type: values.account_type,
              role: values.account_type === 'user' ? values.role : undefined,
              force_password_change: values.force_password_change,
              line_ids: values.line_ids,
            },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Could not create user', e.message),
            },
          )
        }
      />
    </DetailScreen>
  );
}
