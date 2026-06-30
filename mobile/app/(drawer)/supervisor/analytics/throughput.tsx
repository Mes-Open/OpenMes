import { MissingScreen } from '@/components/ui/MissingScreen';

export default function ThroughputPage() {
  return (
    <MissingScreen
      title="Throughput"
      subtitle="SUPERVISOR · ANALYTICS"
      endpoint="GET /api/v1/analytics/throughput"
    />
  );
}
