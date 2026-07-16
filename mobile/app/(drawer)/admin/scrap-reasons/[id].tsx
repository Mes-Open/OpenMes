import { useLocalSearchParams } from 'expo-router';

import { ScrapReasonFormScreen } from '@/screens/(drawer)/admin/scrap-reasons/form';

export default function EditScrapReasonRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <ScrapReasonFormScreen id={Number(id)} />;
}
