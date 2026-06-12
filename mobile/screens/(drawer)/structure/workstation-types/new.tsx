import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SimpleCodeNameForm } from '@/components/admin/SimpleCodeNameForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateWorkstationType } from '@/hooks/mutations/workstationTypes';

export function NewWorkstationTypeScreen() {
  const router = useRouter();
  const createMutation = useCreateWorkstationType();

  return (
    <DetailScreen>
      <SimpleCodeNameForm
        mode="create"
        entityLabel="workstation type"
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
              onError: (e: Error) => Alert.alert('Could not create', e.message),
            },
          )
        }
      />
    </DetailScreen>
  );
}
