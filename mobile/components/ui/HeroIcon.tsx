/**
 * Sidebar/nav glyphs. We mirror the web admin sidebar's Heroicons-outline look
 * using the already-bundled `@expo/vector-icons` fonts (Feather + Material
 * Community Icons) — no extra SVG dependency. Each key maps to the closest
 * outline glyph for the matching web Heroicon.
 */
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';

type Glyph =
  | { set: 'feather'; name: React.ComponentProps<typeof Feather>['name'] }
  | { set: 'mci'; name: React.ComponentProps<typeof MaterialCommunityIcons>['name'] };

const GLYPHS = {
  dashboard: { set: 'feather', name: 'home' },
  bell: { set: 'feather', name: 'bell' },
  calendar: { set: 'feather', name: 'calendar' },
  users: { set: 'feather', name: 'users' },
  clipboard: { set: 'feather', name: 'clipboard' },
  beaker: { set: 'mci', name: 'flask-outline' },
  office: { set: 'mci', name: 'office-building-outline' },
  hr: { set: 'mci', name: 'account-group-outline' },
  cog: { set: 'feather', name: 'settings' },
  wifi: { set: 'feather', name: 'wifi' },
  shield: { set: 'feather', name: 'shield' },
  cube: { set: 'feather', name: 'box' },
  chart: { set: 'feather', name: 'bar-chart-2' },
  packaging: { set: 'feather', name: 'package' },
  webhook: { set: 'feather', name: 'zap' },
  settings: { set: 'feather', name: 'settings' },
  moon: { set: 'feather', name: 'moon' },
  sun: { set: 'feather', name: 'sun' },
  device: { set: 'feather', name: 'smartphone' },
  logout: { set: 'feather', name: 'log-out' },
  menu: { set: 'feather', name: 'menu' },
  switchLine: { set: 'feather', name: 'repeat' },
  chevronDouble: { set: 'feather', name: 'chevrons-left' },
  chevronUp: { set: 'feather', name: 'chevron-up' },
  chevronDown: { set: 'feather', name: 'chevron-down' },
  chevronLeft: { set: 'feather', name: 'chevron-left' },
  chevronRight: { set: 'feather', name: 'chevron-right' },
} satisfies Record<string, Glyph>;

export type HeroIconKey = keyof typeof GLYPHS;

interface HeroIconProps {
  name: HeroIconKey;
  size?: number;
  color?: string;
}

export function HeroIcon({ name, size = 22, color = '#000' }: HeroIconProps) {
  const g = GLYPHS[name];
  if (g.set === 'mci') {
    return <MaterialCommunityIcons name={g.name} size={size} color={color} />;
  }
  return <Feather name={g.name} size={size} color={color} />;
}
