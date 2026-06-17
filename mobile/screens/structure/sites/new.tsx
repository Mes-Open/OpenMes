import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SiteForm } from '@/components/admin/SiteForm';
import { DetailScreen } from '@/components/ui/Detail';
import { LoadingState } from '@/components/ui/StateViews';
import { useCompanies } from '@/hooks/queries/useOps';
import { useCreateSite } from '@/hooks/queries/useStructureIsa95';

export function NewSiteScreen() {
  const router = useRouter();
  const companiesQ = useCompanies({});
  const m = useCreateSite();

  if (companiesQ.isLoading) return <LoadingState />;

  return (
    <DetailScreen>
      <SiteForm
        mode="create"
        companies={(companiesQ.data ?? []).map((c) => ({ id: c.id, name: c.name }))}
        submitting={m.isPending}
        onSubmit={(input) =>
          m.mutate(input, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create site', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
