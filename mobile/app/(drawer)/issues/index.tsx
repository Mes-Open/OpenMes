/** /issues has detail/new routes but no list of its own — land on admin Issues. */
import { Redirect } from 'expo-router';

export default function IssuesIndexRedirect() {
  return <Redirect href="/admin/issues" />;
}
