import { Image, type ImageStyle } from "react-native";

const LOGO_WIDE = require("@/assets/images/logo-wide.png");
const LOGO_ASPECT = 744 / 390; // intrinsic dimensions of logo-wide.png

interface LogoProps {
  /**
   * Approximate cap height of the wordmark (px). The logo image preserves its
   * intrinsic aspect ratio, so width follows from `size * 1.7 * LOGO_ASPECT`.
   */
  size?: number;
  /** Kept for API compatibility — the asset has fixed colors and ignores it. */
  color?: string;
  style?: ImageStyle;
}

/**
 * Official OpenMES wordmark + gear-and-factory mark, rendered from
 * `assets/images/logo-wide.png`. The image carries both the icon and the
 * wordmark in one asset and never re-tints based on theme — the gear stays
 * orange and the wordmark stays navy/orange regardless of light/dark mode.
 */
export function BrandLogo({ size = 18, style }: LogoProps) {
  const height = Math.round(size * 1.7);
  const width = Math.round(height * LOGO_ASPECT);
  return (
    <Image
      source={LOGO_WIDE}
      resizeMode="contain"
      style={[{ width, height }, style]}
    />
  );
}
