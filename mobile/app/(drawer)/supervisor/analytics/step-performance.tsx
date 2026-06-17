import { MissingScreen } from '@/components/ui/MissingScreen';

export default function StepPerformancePage() {
  return (
    <MissingScreen
      title="Step performance"
      subtitle="SUPERVISOR · ANALYTICS"
      endpoint="GET /api/v1/analytics/step-performance"
    />
  );
}
