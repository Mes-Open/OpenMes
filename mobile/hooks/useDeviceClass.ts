import { useWindowDimensions } from 'react-native';

export type DeviceClass = 'phone' | 'tablet';

/** Tablet threshold uses the *short* side so an orientation flip doesn't
 * reclassify a phone-on-its-side as a tablet. 600dp is the conventional
 * Material/Android cutoff. */
const TABLET_MIN_SHORT_DIM = 600;

/** Below this current width the multi-pane tablet layouts get too cramped
 * (truncated titles, vertical text-wrapping). Drop back to phone layout. */
const TABLET_LAYOUT_MIN_WIDTH = 900;

export interface DeviceInfo {
  deviceClass: DeviceClass;
  isTablet: boolean;
  isPhone: boolean;
  /** Current window width in dp. */
  width: number;
  /** Current window height in dp. */
  height: number;
  /** True when width > height. Tablets in landscape (the design canvas) are wide. */
  isLandscape: boolean;
  /**
   * True when the tablet design layouts should be rendered. Requires a tablet
   * device *and* enough current width to fit the 3-column grids designed at
   * 1280×800. A tablet in portrait or a multitasked Slide Over window falls
   * back to phone layouts, which adapt to any aspect ratio.
   */
  useTabletLayout: boolean;
}

/**
 * Single source of truth for device-class branching. Components should consult
 * this rather than reading dimensions directly so we have one place to tune
 * the breakpoint when the tablet design lands.
 */
export function useDeviceClass(): DeviceInfo {
  const { width, height } = useWindowDimensions();
  const shortDim = Math.min(width, height);
  const isTablet = shortDim >= TABLET_MIN_SHORT_DIM;
  const isLandscape = width > height;
  return {
    deviceClass: isTablet ? 'tablet' : 'phone',
    isTablet,
    isPhone: !isTablet,
    width,
    height,
    isLandscape,
    useTabletLayout: isTablet && width >= TABLET_LAYOUT_MIN_WIDTH,
  };
}
