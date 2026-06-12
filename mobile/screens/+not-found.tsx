import { Link, Stack } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { BrandLogo } from '@/components/ui/Brand';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

export function NotFoundScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  return (
    <>
      <Stack.Screen options={{ title: 'Not found', headerShown: false }} />
      <View style={[styles.container, { backgroundColor: palette.background }]}>
        <View style={styles.brandRow}>
          <BrandLogo size={16} color={palette.text} />
        </View>

        <View style={styles.body}>
          <View style={[styles.iconBadge, { backgroundColor: '#FAF0DD' }]}>
            <FontAwesome name="compass" size={28} color={BRAND.amber} />
          </View>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
            ERROR · 404
          </Mono>
          <Text style={[styles.title, { color: palette.text }]}>This screen doesn't exist.</Text>
          <Text style={[styles.subtitle, { color: palette.textMuted }]}>
            The page you're looking for has been moved, removed, or never existed.
          </Text>

          <Link href="/" style={[styles.link, { backgroundColor: BRAND.amber }]}>
            <Text style={styles.linkText}>Back to home</Text>
          </Link>
        </View>
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24 },
  brandRow: { paddingTop: 12 },
  body: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 8, paddingHorizontal: 12 },
  iconBadge: {
    width: 72,
    height: 72,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  title: { fontSize: 22, fontWeight: '600', letterSpacing: -0.4, textAlign: 'center', marginTop: 8 },
  subtitle: { fontSize: 14, textAlign: 'center', lineHeight: 21, marginTop: 4, maxWidth: 320 },
  link: {
    marginTop: 24,
    paddingVertical: 14,
    paddingHorizontal: 28,
    borderRadius: 12,
  },
  linkText: { color: '#1a1208', fontSize: 15, fontWeight: '600', letterSpacing: 0.2 },
});
