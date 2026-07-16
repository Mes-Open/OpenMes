import { useLocalSearchParams } from 'expo-router';

import { MaterialFormScreen } from '@/screens/(drawer)/admin/materials/form';

export default function EditMaterialRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <MaterialFormScreen id={Number(id)} />;
}
