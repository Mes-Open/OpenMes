import { Platform } from "react-native";

export const DEFAULT_API_URL = "https://demo.getopenmes.com";

/**
 * Local development server. Android emulators can't reach the host's
 * `localhost` — Google's emulator exposes the host loopback as 10.0.2.2.
 */
export const LOCAL_API_URL =
  Platform.OS === "android" ? "http://10.0.2.2:8080" : "http://localhost:8080";

export const API_PREFIX = "/api";
export const API_V1_PREFIX = "/api/v1";
