import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { AreaForm } from '@/components/admin/AreaForm';
import { DetailScreen } from '@/components/ui/Detail';
import { LoadingState } from '@/components/ui/StateViews';
import { useCreateArea, useSites } from '@/hooks/queries/useStructureIsa95';

export function NewAreaScreen() {
  const router = useRouter();
  // Optional ?site_id locks the picker — used when adding from a site detail.
  const { site_id } = useLocalSearchParams<{ site_id?: string }>();
  const lockedId = site_id ? Number(site_id) : undefined;

  const sitesQ = useSites();
  const m = useCreateArea();

  if (sitesQ.isLoading) return <LoadingState />;

  return (
    <DetailScreen>
      <AreaForm
        mode="create"
        sites={(sitesQ.data ?? []).map((s) => ({ id: s.id, name: s.name }))}
        lockedSiteId={lockedId}
        submitting={m.isPending}
        onSubmit={(input) =>
          m.mutate(input, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create area', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
