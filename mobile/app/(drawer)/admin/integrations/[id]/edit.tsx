import { useLocalSearchParams } from 'expo-router';

import { IntegrationFormScreen } from '@/screens/(drawer)/admin/integrations/form';

export default function EditIntegrationRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <IntegrationFormScreen id={Number(id)} />;
}
