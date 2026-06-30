import { FontAwesome } from '@expo/vector-icons';
import * as WebBrowser from 'expo-web-browser';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Mono } from '@/components/ui/Mono';
import { BRAND, MONO } from '@/constants/Colors';
import { useUpdateCheck } from '@/hooks/queries/useSystem';
import { useSettingsStore } from '@/stores/settingsStore';

/**
 * Update banner — surfaces on every authenticated screen when
 * /system/update-check reports `update_available`. The CTA opens the
 * release notes URL in a web browser so the user can read changelog
 * before updating; the actual apply flow lives on the web admin.
 */
export function UpdateBanner() {
  const { t } = useTranslation();
  const { data } = useUpdateCheck();
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  if (!data || !data.update_available) return null;

  return (
    <View style={styles.wrap}>
      <View style={styles.row}>
        <View style={styles.iconWrap}>
          <FontAwesome name="rocket" size={22} color={BRAND.amber} />
        </View>
        <View style={{ flex: 1 }}>
          <Mono size={9.5} color={BRAND.amber} weight="700" letterSpacing={0.8}>
            {t('UPDATE AVAILABLE').toUpperCase()}
          </Mono>
          <Text style={styles.versionLine}>
            OpenMES v{data.latest_version}
          </Text>
          <Text style={styles.notes}>
            {t('Tap to view changelog and apply on web.')}
          </Text>
          <View style={styles.actions}>
            <Pressable
              onPress={() => {
                const url =
                  data.release_notes_url ?? `${serverUrl}/admin/system/updates`;
                WebBrowser.openBrowserAsync(url);
              }}
              style={({ pressed }) => [
                styles.primary,
                { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
              ]}>
              <Mono size={10.5} color="#1a1208" weight="700" letterSpacing={0.5}>
                {t('VIEW CHANGELOG').toUpperCase()}
              </Mono>
            </Pressable>
          </View>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    borderRadius: 14,
    padding: 16,
    backgroundColor: '#1f2547',
  },
  row: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  iconWrap: {
    width: 42,
    height: 42,
    borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.12)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  versionLine: { color: '#fff', fontSize: 15, fontWeight: '700', marginTop: 4 },
  notes: {
    color: '#c5c8d6',
    fontSize: 12,
    lineHeight: 18,
    marginTop: 6,
    fontFamily: MONO,
    letterSpacing: 0.3,
  },
  actions: { flexDirection: 'row', gap: 8, marginTop: 12 },
  primary: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 8,
  },
});
