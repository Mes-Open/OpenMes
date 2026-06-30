import { MissingScreen } from '@/components/ui/MissingScreen';

export default function ProductionByLinePage() {
  return (
    <MissingScreen
      title="Production by line"
      subtitle="SUPERVISOR · ANALYTICS"
      endpoint="GET /api/v1/analytics/production-by-line"
    />
  );
}
