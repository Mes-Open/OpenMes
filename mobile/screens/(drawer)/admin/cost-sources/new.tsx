import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { CostSourceForm } from '@/components/admin/CostSourceForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateCostSource } from '@/hooks/queries/useOps';

export function NewCostSourceScreen() {
  const router = useRouter();
  const m = useCreateCostSource();

  return (
    <DetailScreen>
      <CostSourceForm
        mode="create"
        submitting={m.isPending}
        onSubmit={(v) =>
          m.mutate(
            {
              code: v.code,
              name: v.name,
              description: v.description || undefined,
              unit_cost: v.unit_cost ? Number(v.unit_cost) : undefined,
              unit: v.unit || undefined,
              currency: v.currency || undefined,
              is_active: v.is_active,
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not create', e.message) },
          )
        }
      />
    </DetailScreen>
  );
}
