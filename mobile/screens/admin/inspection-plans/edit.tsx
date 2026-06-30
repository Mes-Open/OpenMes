import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { InspectionPlanForm } from '@/components/admin/InspectionPlanForm';
import { DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useMaterialTypes, useMaterials } from '@/hooks/queries/useBom';
import {
  useInspectionPlan,
  useUpdateInspectionPlan,
} from '@/hooks/queries/useInspections';

export function EditInspectionPlanScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useInspectionPlan(Number.isFinite(id) ? id : undefined);
  const matsQ = useMaterials({});
  const typesQ = useMaterialTypes();
  const m = useUpdateInspectionPlan();

  if (q.isLoading || matsQ.isLoading || typesQ.isLoading) return <LoadingState />;
  if (q.isError || !q.data)
    return <ErrorState error={q.error ?? new Error('Plan not found')} onRetry={q.refetch} />;

  return (
    <DetailScreen>
      <InspectionPlanForm
        mode="edit"
        initial={q.data}
        materials={(matsQ.data ?? []).map((x) => ({ id: x.id, name: x.name }))}
        materialTypes={(typesQ.data ?? []).map((x) => ({ id: x.id, name: x.name }))}
        submitting={m.isPending}
        onSubmit={(payload) =>
          m.mutate(
            { id, payload },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Could not save changes', e.message),
            },
          )
        }
      />
    </DetailScreen>
  );
}
