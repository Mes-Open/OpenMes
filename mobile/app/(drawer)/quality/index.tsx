/** /quality hub alias — inspections is the section's landing list. */
import { Redirect } from 'expo-router';

export default function QualityIndexRedirect() {
  return <Redirect href="/quality/inspections" />;
}
