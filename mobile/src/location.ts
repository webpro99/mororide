import * as Location from 'expo-location';

export type Coord = { lat: number; lng: number };

/** One-shot current position (returns null if permission denied or web/no GPS). */
export async function getCurrentCoord(): Promise<Coord | null> {
  try {
    const { status } = await Location.requestForegroundPermissionsAsync();
    if (status !== 'granted') {
      return null;
    }
    const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
    return { lat: position.coords.latitude, lng: position.coords.longitude };
  } catch {
    return null;
  }
}

/**
 * Continuously watch the device position while online. Returns an unsubscribe
 * function. Foreground tracking works in Expo Go; true background tracking
 * needs a dev build with a TaskManager background task (see TESTING/README).
 */
export async function watchCoord(onChange: (coord: Coord) => void): Promise<() => void> {
  try {
    const { status } = await Location.requestForegroundPermissionsAsync();
    if (status !== 'granted') {
      return () => undefined;
    }
    const subscription = await Location.watchPositionAsync(
      { accuracy: Location.Accuracy.Balanced, timeInterval: 8000, distanceInterval: 25 },
      (position) => onChange({ lat: position.coords.latitude, lng: position.coords.longitude }),
    );
    return () => subscription.remove();
  } catch {
    return () => undefined;
  }
}
