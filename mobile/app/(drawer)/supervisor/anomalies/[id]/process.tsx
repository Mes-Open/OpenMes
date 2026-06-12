import { MissingScreen } from '@/components/ui/MissingScreen';

export default function ProcessAnomalyPage() {
  return (
    <MissingScreen
      title="Process anomaly"
      subtitle="SUPERVISOR · QUALITY"
      endpoint="POST /api/v1/production-anomalies/{id}/process"
      note="Supervisor-level decision on an operator-reported anomaly — approve / reject / require rework. Requires the anomaly ID; today operators can only file the report."
    />
  );
}
