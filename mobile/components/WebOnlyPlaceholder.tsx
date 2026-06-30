import { FontAwesome } from '@expo/vector-icons';
import * as WebBrowser from 'expo-web-browser';
import { StyleSheet, Text, View } from 'react-native';

import { Button } from '@/components/ui/Button';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useSettingsStore } from '@/stores/settingsStore';

interface Props {
  title: string;
  description?: string;
  webPath?: string;
}

export function WebOnlyPlaceholder({ title, description, webPath }: Props) {
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  const onOpen = () => {
    const url = webPath ? `${serverUrl}${webPath}` : serverUrl;
    WebBrowser.openBrowserAsync(url);
  };

  return (
    <View style={[styles.container, { backgroundColor: palette.background }]}>
      <View style={[styles.icon, { backgroundColor: palette.surfaceAlt }]}>
        <FontAwesome name="desktop" size={32} color={palette.tint} />
      </View>
      <Text style={[styles.title, { color: palette.text }]}>{title}</Text>
      <Text style={[styles.subtitle, { color: palette.textMuted }]}>
        {description ?? 'This area is available in the OpenMes web app. Open it in your browser to manage settings, configurations, and admin tools.'}
      </Text>
      <Button title="Open on web" onPress={onOpen} variant="primary" style={{ marginTop: 8 }} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32, gap: 14 },
  icon: { width: 80, height: 80, borderRadius: 40, alignItems: 'center', justifyContent: 'center', marginBottom: 8 },
  title: { fontSize: 22, fontWeight: '800', textAlign: 'center' },
  subtitle: { fontSize: 15, textAlign: 'center', lineHeight: 22, maxWidth: 320 },
});
