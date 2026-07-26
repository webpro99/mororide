import * as Device from 'expo-device';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import { Platform } from 'react-native';
import { registerDeviceToken, unregisterDeviceToken } from './api';

let currentToken: string | null = null;
let notificationHandlerReady = false;

export type IncomingCallNotice = {
  orderId: number;
  title: string;
  body: string;
  callerId?: number | null;
};

function incomingCallFromNotification(notification: any): IncomingCallNotice | null {
  const content = notification?.request?.content;
  const data = content?.data ?? {};
  if (data?.type !== 'incoming_voice_call') return null;

  const orderId = Number(data.order_id);
  if (!Number.isFinite(orderId)) return null;

  const callerId = Number(data.caller_id);
  return {
    orderId,
    title: typeof content.title === 'string' ? content.title : 'Incoming MoroRide call',
    body: typeof content.body === 'string' ? content.body : 'Answer the private ride call.',
    callerId: Number.isFinite(callerId) ? callerId : null,
  };
}

/**
 * Best-effort push registration. Expo push tokens require a physical device and
 * granted permission; on web, simulators, or when denied it resolves to null
 * without throwing so sign-in is never blocked.
 */
export async function registerForPush(): Promise<string | null> {
  try {
    if (
      Platform.OS === 'web' ||
      !Device.isDevice ||
      Constants.executionEnvironment === ExecutionEnvironment.StoreClient
    ) {
      return null;
    }

    const Notifications = await import('expo-notifications');
    if (!notificationHandlerReady) {
      Notifications.setNotificationHandler({
        handleNotification: async () => ({
          shouldShowAlert: true,
          shouldPlaySound: true,
          shouldSetBadge: false,
          shouldShowBanner: true,
          shouldShowList: true,
        }),
      });
      notificationHandlerReady = true;
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

export function subscribeToIncomingCalls(onIncomingCall: (notice: IncomingCallNotice) => void): () => void {
  if (Platform.OS === 'web') return () => undefined;

  let disposed = false;
  let receivedSubscription: { remove: () => void } | null = null;
  let responseSubscription: { remove: () => void } | null = null;

  void (async () => {
    try {
      const Notifications = await import('expo-notifications');
      if (disposed) return;

      receivedSubscription = Notifications.addNotificationReceivedListener((notification) => {
        const notice = incomingCallFromNotification(notification);
        if (notice) onIncomingCall(notice);
      });

      responseSubscription = Notifications.addNotificationResponseReceivedListener((response) => {
        const notice = incomingCallFromNotification(response.notification);
        if (notice) onIncomingCall(notice);
      });
    } catch {
      // Notification listeners are best effort; polling still catches calls.
    }
  })();

  return () => {
    disposed = true;
    receivedSubscription?.remove();
    responseSubscription?.remove();
  };
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
