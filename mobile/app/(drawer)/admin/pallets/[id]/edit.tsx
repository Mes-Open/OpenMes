import { useLocalSearchParams } from 'expo-router';

import { PalletFormScreen } from '@/screens/(drawer)/admin/pallets/form';

export default function EditPalletRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <PalletFormScreen id={Number(id)} />;
}
