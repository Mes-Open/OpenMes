/**
 * Web twin for the native-only ActionSheet.
 *
 * The "native-only" components are built from core react-native primitives
 * (Animated, Modal, PanResponder, TextInput) that react-native-web renders
 * faithfully. Expo web (react-native-web) is the *only* web consumer of
 * `@openmes/ui/native` — the Vite/DOM web app never imports it — so the native
 * implementation is reused verbatim rather than maintaining a divergent DOM
 * port. Metro resolves this `.web.jsx` on web; native keeps `index.native.tsx`.
 */
export * from './index.native';
