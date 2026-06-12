import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { WorkstationForm } from '@/components/admin/WorkstationForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateWorkstation } from '@/hooks/mutations/lines';

export function NewWorkstationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const lineId = Number(id);
  const router = useRouter();
  const createMutation = useCreateWorkstation();

  return (
    <DetailScreen>
      <WorkstationForm
        mode="create"
        submitting={createMutation.isPending}
        onSubmit={(values) =>
          createMutation.mutate(
            {
              lineId,
              payload: {
                code: values.code,
                name: values.name,
                workstation_type: values.workstation_type || undefined,
                is_active: values.is_active,
              },
            },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Could not create workstation', e.message),
            },
          )
        }
      />
    </DetailScreen>
  );
}
