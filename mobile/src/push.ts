import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';
import { registerDeviceToken, unregisterDeviceToken } from './api';

let currentToken: string | null = null;

// Show a heads-up alert when a push arrives while the app is foregrounded.
// Guarded so web (no native notifications module) never throws at import.
if (Platform.OS !== 'web') {
  try {
    Notifications.setNotificationHandler({
      handleNotification: async () => ({
        shouldShowAlert: true,
        shouldPlaySound: true,
        shouldSetBadge: false,
        shouldShowBanner: true,
        shouldShowList: true,
      }),
    });
  } catch {
    // no-op
  }
}

/**
 * Best-effort push registration. Expo push tokens require a physical device and
 * granted permission; on web, simulators, or when denied it resolves to null
 * without throwing so sign-in is never blocked.
 */
export async function registerForPush(): Promise<string | null> {
  try {
    if (Platform.OS === 'web' || !Device.isDevice) {
      return null;
    }

    let status = (await Notifications.getPermissionsAsync()).status;
    if (status !== 'granted') {
      status = (await Notifications.requestPermissionsAsync()).status;
    }
    if (status !== 'granted') {
      return null;
    }

    const { data } = await Notifications.getExpoPushTokenAsync();
    currentToken = data;
    await registerDeviceToken(data, Platform.OS === 'ios' ? 'ios' : 'android');
    return data;
  } catch {
    return null;
  }
}

/** Remove the current device token (call on sign-out / role switch). */
export async function unregisterPush(): Promise<void> {
  if (!currentToken) {
    return;
  }
  try {
    await unregisterDeviceToken(currentToken);
  } catch {
    // best-effort
  }
  currentToken = null;
}
