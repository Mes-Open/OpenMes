import { HomeTab } from '@/screens/(drawer)/(tabs)/index';

/**
 * Operator Today — same screen as the (tabs)/index landing, mounted under
 * /operator/today so the URL stays in operator context. Shared screen file;
 * only the URL differs.
 */
export default function OperatorTodayPage() {
  return <HomeTab />;
}
