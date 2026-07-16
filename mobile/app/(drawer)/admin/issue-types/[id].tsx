import { useLocalSearchParams } from 'expo-router';

import { IssueTypeFormScreen } from '@/screens/(drawer)/admin/issue-types/form';

export default function EditIssueTypeRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <IssueTypeFormScreen id={Number(id)} />;
}
