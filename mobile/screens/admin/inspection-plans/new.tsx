import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { InspectionPlanForm } from '@/components/admin/InspectionPlanForm';
import { DetailScreen } from '@/components/ui/Detail';
import { LoadingState } from '@/components/ui/StateViews';
import { useMaterialTypes, useMaterials } from '@/hooks/queries/useBom';
import { useCreateInspectionPlan } from '@/hooks/queries/useInspections';

export function NewInspectionPlanScreen() {
  const router = useRouter();
  const matsQ = useMaterials({});
  const typesQ = useMaterialTypes();
  const m = useCreateInspectionPlan();

  if (matsQ.isLoading || typesQ.isLoading) return <LoadingState />;

  return (
    <DetailScreen>
      <InspectionPlanForm
        mode="create"
        materials={(matsQ.data ?? []).map((x) => ({ id: x.id, name: x.name }))}
        materialTypes={(typesQ.data ?? []).map((x) => ({ id: x.id, name: x.name }))}
        submitting={m.isPending}
        onSubmit={(payload) =>
          m.mutate(payload, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create plan', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
