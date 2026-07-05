/**
 * Web twin for the native-only BottomSheet — reuses the native implementation
 * under react-native-web (Expo web). See ActionSheet/index.web.jsx for the
 * rationale: core RN primitives render faithfully on web and the DOM web app
 * does not consume @openmes/ui/native.
 */
export * from './index.native';
