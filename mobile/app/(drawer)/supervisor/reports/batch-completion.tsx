import { MissingScreen } from '@/components/ui/MissingScreen';

export default function BatchCompletionPage() {
  return (
    <MissingScreen
      title="Batch completion report"
      subtitle="SUPERVISOR · REPORTS"
      endpoint="GET /api/v1/reports/batch-completion"
    />
  );
}
