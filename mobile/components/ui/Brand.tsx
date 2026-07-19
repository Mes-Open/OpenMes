// Official OpenMES brand lockup — the factory + gear + check mark with the
// "OPEN MES" wordmark, rendered from the brand asset (was previously a
// code-drawn split-square placeholder).
import { Image, type ImageStyle, type StyleProp } from 'react-native';

// eslint-disable-next-line @typescript-eslint/no-var-requires
const LOGO = require('../../assets/images/logo-wide.png');
const ASPECT = 744 / 390; // intrinsic logo-wide.png dimensions (w/h)

interface LogoProps {
  /**
   * Approximate cap height of the wordmark (px). The lockup scales around it so
   * it keeps its proportions at any size.
   */
  size?: number;
  /** Kept for API compatibility — the official mark has fixed colors. */
  color?: string;
  style?: StyleProp<ImageStyle>;
}

/**
 * OpenMES brand lockup — renders the official logo (assets/images/logo-wide.png).
 * `size` maps to the wordmark cap height; the image scales (keeping aspect)
 * around it. Drop-in for the old code-drawn mark (same size/color/style props).
 */
export function BrandLogo({ size = 18, style }: LogoProps) {
  const height = Math.round(size * 1.8);
  return (
    <Image
      source={LOGO}
      resizeMode="contain"
      accessibilityRole="image"
      accessibilityLabel="OpenMES"
      style={[{ height, width: Math.round(height * ASPECT) }, style]}
    />
  );
}
