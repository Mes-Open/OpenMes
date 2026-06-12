import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { CompanyForm } from '@/components/admin/CompanyForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateCompany } from '@/hooks/queries/useOps';

export function NewCompanyScreen() {
  const router = useRouter();
  const m = useCreateCompany();

  return (
    <DetailScreen>
      <CompanyForm
        mode="create"
        submitting={m.isPending}
        onSubmit={(v) =>
          m.mutate(
            {
              code: v.code,
              name: v.name,
              type: v.type,
              tax_id: v.tax_id || undefined,
              email: v.email || undefined,
              phone: v.phone || undefined,
              address: v.address || undefined,
              is_active: v.is_active,
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not create', e.message) },
          )
        }
      />
    </DetailScreen>
  );
}
