import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SiteForm } from '@/components/admin/SiteForm';
import { DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCompanies } from '@/hooks/queries/useOps';
import { useSite, useUpdateSite } from '@/hooks/queries/useStructureIsa95';

export function EditSiteScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useSite(Number.isFinite(id) ? id : undefined);
  const companiesQ = useCompanies({});
  const m = useUpdateSite();

  if (q.isLoading || companiesQ.isLoading) return <LoadingState />;
  if (q.isError || !q.data)
    return <ErrorState error={q.error ?? new Error('Site not found')} onRetry={q.refetch} />;

  return (
    <DetailScreen>
      <SiteForm
        mode="edit"
        initial={q.data}
        companies={(companiesQ.data ?? []).map((c) => ({ id: c.id, name: c.name }))}
        submitting={m.isPending}
        onSubmit={(input) =>
          m.mutate(
            { id, input },
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
