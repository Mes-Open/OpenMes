import { useLocalSearchParams } from 'expo-router';

import { WebhookFormScreen } from '@/screens/(drawer)/admin/webhooks/form';

export default function EditWebhookRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <WebhookFormScreen id={Number(id)} />;
}
