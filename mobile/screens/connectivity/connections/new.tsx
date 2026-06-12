import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ConnectionForm } from '@/components/admin/ConnectionForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateConnection } from '@/hooks/queries/useConnectivity';

export function NewConnectionScreen() {
  const router = useRouter();
  const m = useCreateConnection();

  return (
    <DetailScreen>
      <ConnectionForm
        mode="create"
        submitting={m.isPending}
        onSubmit={(input) =>
          m.mutate(input, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create connection', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
