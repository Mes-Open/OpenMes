import { MissingScreen } from '@/components/ui/MissingScreen';

export default function AnalyticsOverviewPage() {
  return (
    <MissingScreen
      title="Analytics overview"
      subtitle="SUPERVISOR · KPIs"
      endpoint="GET /api/v1/analytics/overview"
    />
  );
}
