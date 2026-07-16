import { useLocalSearchParams } from 'expo-router';

import { CustomFieldFormScreen } from '@/screens/(drawer)/admin/custom-fields/form';

export default function EditCustomFieldRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <CustomFieldFormScreen id={Number(id)} />;
}
