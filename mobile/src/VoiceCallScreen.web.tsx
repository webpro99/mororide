import { Pressable, StyleSheet, Text, View } from 'react-native';

export function VoiceCallScreen({ onEnd }: { orderId: number; peerLabel: string; notify?: boolean; onEnd: () => void }) {
  return <View style={styles.page}><Text style={styles.title}>Voice calls require the MoroRide native app.</Text><Pressable onPress={onEnd} style={styles.button}><Text style={styles.buttonText}>Back</Text></Pressable></View>;
}

const styles = StyleSheet.create({
  page: { alignItems: 'center', backgroundColor: '#061f38', flex: 1, justifyContent: 'center', padding: 24 },
  title: { color: '#fff', fontSize: 18, fontWeight: '800', textAlign: 'center' },
  button: { backgroundColor: '#d49a55', borderRadius: 14, marginTop: 20, paddingHorizontal: 24, paddingVertical: 13 },
  buttonText: { color: '#fff', fontWeight: '900' },
});
