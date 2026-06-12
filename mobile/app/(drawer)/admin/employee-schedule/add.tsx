import { useLocalSearchParams } from 'expo-router';

import { AddActivityScreen } from '@/screens/(drawer)/employee-schedule/AddActivityScreen';

export default function AddActivityPage() {
  const params = useLocalSearchParams<{ worker_id?: string; date?: string }>();
  const workerId = Number(params.worker_id);
  if (!Number.isFinite(workerId)) {
    // Without a worker we can't render the form; expo-router will keep the
    // route mounted, so show a no-op (caller routes here with both params).
    return null;
  }
  const initialDate = params.date ? new Date(params.date) : new Date();
  return <AddActivityScreen workerId={workerId} initialDate={initialDate} />;
}
