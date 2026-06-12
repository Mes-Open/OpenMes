/**
 * @openmes/ui/native — React Native-only patterns (no web twin).
 * Root-level shim so the subpath resolves without an `exports` map
 * (an exports map would defeat Metro/Vite platform-extension resolution).
 */
export * from './src/native';
