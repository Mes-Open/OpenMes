import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ShiftForm } from '@/components/admin/ShiftForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateShift } from '@/hooks/queries/useOps';

export function NewShiftScreen() {
  const router = useRouter();
  const m = useCreateShift();

  return (
    <DetailScreen>
      <ShiftForm
        mode="create"
        submitting={m.isPending}
        onSubmit={(v) =>
          m.mutate(
            {
              name: v.name,
              start_time: v.start_time,
              end_time: v.end_time,
              days_of_week: v.days_of_week.length ? v.days_of_week : undefined,
              line_id: v.line_id ?? undefined,
              is_active: v.is_active,
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not create', e.message) },
          )
        }
      />
    </DetailScreen>
  );
}
