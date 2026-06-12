import { FontAwesome } from '@expo/vector-icons';
import { StyleSheet, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  placeholder?: string;
  value?: string;
  onChangeText?: (v: string) => void;
}

/**
 * Mono-styled search bar matching the catalog list pattern from the design.
 * Placeholder auto-translates so call sites pass English keys.
 */
export function SearchBar({ placeholder = 'Search', value, onChangeText }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  return (
    <View style={[styles.wrap, { backgroundColor: palette.surfaceAlt }]}>
      <FontAwesome name="search" size={14} color={palette.textFaint} />
      <TextInput
        style={[styles.input, { color: palette.text, fontFamily: MONO }]}
        placeholder={t(placeholder)}
        placeholderTextColor={palette.textFaint}
        value={value}
        onChangeText={onChangeText}
        autoCapitalize="none"
        autoCorrect={false}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    height: 42,
    borderRadius: 10,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    gap: 10,
  },
  input: { flex: 1, fontSize: 12, paddingVertical: 0 },
});
