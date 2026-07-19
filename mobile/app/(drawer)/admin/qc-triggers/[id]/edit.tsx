import { useLocalSearchParams } from 'expo-router';

import { QcTriggerFormScreen } from '@/screens/(drawer)/admin/qc-triggers/form';

export default function EditQcTriggerRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <QcTriggerFormScreen id={Number(id)} />;
}
