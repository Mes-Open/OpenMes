import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ProductTypeForm } from '@/components/admin/ProductTypeForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateProductType } from '@/hooks/mutations/productTypes';

export function NewProductTypeScreen() {
  const router = useRouter();
  const createMutation = useCreateProductType();

  return (
    <DetailScreen>
      <ProductTypeForm
        mode="create"
        submitting={createMutation.isPending}
        onSubmit={(values) =>
          createMutation.mutate(
            {
              code: values.code,
              name: values.name,
              description: values.description || undefined,
              unit_of_measure: values.unit_of_measure || undefined,
              is_active: values.is_active,
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not create', e.message) },
          )
        }
      />
    </DetailScreen>
  );
}
