import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { LineForm } from '@/components/admin/LineForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateLine } from '@/hooks/mutations/lines';

export function NewLineScreen() {
  const router = useRouter();
  const createMutation = useCreateLine();

  return (
    <DetailScreen>
      <LineForm
        mode="create"
        submitting={createMutation.isPending}
        onSubmit={(values) =>
          createMutation.mutate(
            {
              code: values.code,
              name: values.name,
              description: values.description || undefined,
              is_active: values.is_active,
            },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Could not create line', e.message),
            },
          )
        }
      />
    </DetailScreen>
  );
}
