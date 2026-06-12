import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SimpleCodeNameForm } from '@/components/admin/SimpleCodeNameForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateFactory } from '@/hooks/queries/useOrgStructure';

export function NewFactoryScreen() {
  const router = useRouter();
  const m = useCreateFactory();

  return (
    <DetailScreen>
      <SimpleCodeNameForm
        mode="create"
        entityLabel="factory"
        submitting={m.isPending}
        onSubmit={(values) =>
          m.mutate(
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
