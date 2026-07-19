import { useLocalSearchParams } from 'expo-router';

import { LabelTemplateFormScreen } from '@/screens/(drawer)/packaging/label-templates/form';

export default function EditLabelTemplateRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <LabelTemplateFormScreen id={Number(id)} />;
}
