/** /connectivity/mappings list lives on the admin Topic Mappings page. */
import { Redirect } from 'expo-router';

export default function MappingsIndexRedirect() {
  return <Redirect href="/admin/connectivity-mappings" />;
}
