import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ToolForm } from '@/components/maintenance/ToolForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateTool } from '@/hooks/queries/useMaintenance';

export function NewToolScreen() {
  const router = useRouter();
  const m = useCreateTool();

  return (
    <DetailScreen>
      <ToolForm
        mode="create"
        submitting={m.isPending}
        onSubmit={(v) =>
          m.mutate(
            {
              code: v.code,
              name: v.name,
              description: v.description || undefined,
              status: v.status,
              next_service_at: v.next_service_at || undefined,
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not create', e.message) },
          )
        }
      />
    </DetailScreen>
  );
}
