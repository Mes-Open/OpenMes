import { useLocalSearchParams } from 'expo-router';

import { ViewTemplateFormScreen } from '@/screens/(drawer)/admin/view-templates/form';

export default function EditViewTemplateRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <ViewTemplateFormScreen id={Number(id)} />;
}
