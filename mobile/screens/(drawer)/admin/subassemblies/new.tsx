import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SimpleCodeNameForm } from '@/components/admin/SimpleCodeNameForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateSubassembly } from '@/hooks/queries/useOps';

export function NewSubassemblyScreen() {
  const router = useRouter();
  const m = useCreateSubassembly();

  return (
    <DetailScreen>
      <SimpleCodeNameForm
        mode="create"
        entityLabel="subassembly"
        submitting={m.isPending}
        onSubmit={(v) =>
          m.mutate(
            { code: v.code, name: v.name, description: v.description || undefined, is_active: v.is_active },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not create', e.message) },
          )
        }
      />
    </DetailScreen>
  );
}
