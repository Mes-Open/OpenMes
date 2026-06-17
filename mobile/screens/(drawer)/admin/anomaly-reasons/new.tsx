import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { CategorizedCodeNameForm } from '@/components/admin/CategorizedCodeNameForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateAnomalyReason } from '@/hooks/queries/useOps';

export function NewAnomalyReasonScreen() {
  const router = useRouter();
  const m = useCreateAnomalyReason();

  return (
    <DetailScreen>
      <CategorizedCodeNameForm
        mode="create"
        entityLabel="reason"
        submitting={m.isPending}
        onSubmit={(v) =>
          m.mutate(
            {
              code: v.code,
              name: v.name,
              category: v.category || undefined,
              description: v.description || undefined,
              is_active: v.is_active,
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not create', e.message) },
          )
        }
      />
    </DetailScreen>
  );
}
