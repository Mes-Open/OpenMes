import { colors } from '@openmes/ui';
import { Stack, useRouter } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { BrandLogo } from '@/components/ui/Brand';
import { Mono } from '@/components/ui/Mono';
import { getRole, useAuthStore } from '@/stores/authStore';

export function NotFoundScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const user = useAuthStore((s) => s.user);

  // "/" has no screen of its own — send the user to their role landing (or
  // login), and prefer popping history when there is somewhere to go back to.
  const goHome = () => {
    if (router.canGoBack()) {
      router.back();
      return;
    }
    const role = user ? getRole(user) : null;
    const home =
      role === 'Admin'
        ? '/admin/dashboard'
        : role === 'Supervisor'
          ? '/supervisor'
          : role === 'Operator'
            ? '/operator/queue'
            : '/login';
    router.replace(home as never);
  };

  return (
    <>
      <Stack.Screen options={{ title: t('Not found'), headerShown: false }} />
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <View style={styles.brandRow}>
          <BrandLogo size={16} color={colors.ink} />
        </View>

        <View style={styles.body}>
          <View style={[styles.iconBadge, { backgroundColor: '#FAF0DD' }]}>
            <FontAwesome name="compass" size={28} color={colors.accent} />
          </View>
          <Mono size={11} color={colors.faint} letterSpacing={0.8}>
            {t('Error').toUpperCase()} · 404
          </Mono>
          <Text style={[styles.title, { color: colors.ink }]}>{t("This screen doesn't exist.")}</Text>
          <Text style={[styles.subtitle, { color: colors.muted }]}>
            {t("The page you're looking for has been moved, removed, or never existed.")}
          </Text>

          <Pressable onPress={goHome} style={({ pressed }) => [styles.link, { backgroundColor: colors.accent, opacity: pressed ? 0.8 : 1 }]}>
            <Text style={styles.linkText}>{t('Back to home')}</Text>
          </Pressable>
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
