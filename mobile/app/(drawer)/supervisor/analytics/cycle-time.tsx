import { MissingScreen } from '@/components/ui/MissingScreen';

export default function CycleTimePage() {
  return (
    <MissingScreen
      title="Cycle time"
      subtitle="SUPERVISOR · ANALYTICS"
      endpoint="GET /api/v1/analytics/cycle-time"
    />
  );
}
