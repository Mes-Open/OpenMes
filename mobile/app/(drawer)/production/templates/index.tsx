/** Templates are managed per product type — land on the Product Types list. */
import { Redirect } from 'expo-router';

export default function TemplatesIndexRedirect() {
  return <Redirect href="/production/product-types" />;
}
