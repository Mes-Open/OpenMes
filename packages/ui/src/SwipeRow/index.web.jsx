/**
 * Web twin for the native-only SwipeRow — reuses the native implementation
 * under react-native-web (Expo web). The row uses Animated + PanResponder,
 * both supported by react-native-web. See ActionSheet/index.web.jsx for the
 * rationale.
 */
export * from './index.native';
