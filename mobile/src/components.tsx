import { FontAwesome5, Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import { ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, radius, shadow } from './theme';
import { Order } from './types';

type ButtonProps = {
  label: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  icon?: ReactNode;
  disabled?: boolean;
};

export function ActionButton({ label, onPress, variant = 'primary', icon, disabled }: ButtonProps) {
  return (
    <Pressable
      accessibilityRole="button"
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => [
        styles.button,
        styles[variant],
        disabled && styles.disabled,
        pressed && !disabled ? styles.pressed : null,
      ]}
    >
      {icon}
      <Text style={[styles.buttonText, variant === 'secondary' || variant === 'ghost' ? styles.darkText : null]}>
        {label}
      </Text>
    </Pressable>
  );
}

export function Panel({ children }: { children: ReactNode }) {
  return <View style={styles.panel}>{children}</View>;
}

export function SectionTitle({ eyebrow, title }: { eyebrow?: string; title: string }) {
  return (
    <View style={styles.titleBlock}>
      {eyebrow ? <Text style={styles.eyebrow}>{eyebrow}</Text> : null}
      <Text style={styles.sectionTitle}>{title}</Text>
    </View>
  );
}

export function Metric({ label, value, icon }: { label: string; value: string | number; icon: ReactNode }) {
  return (
    <View style={styles.metric}>
      <View style={styles.metricIcon}>{icon}</View>
      <Text style={styles.metricValue}>{value}</Text>
      <Text style={styles.metricLabel}>{label}</Text>
    </View>
  );
}

export function Message({ text, tone }: { text: string | null; tone: 'success' | 'error' | 'info' }) {
  if (!text) return null;
  return (
    <View style={[styles.message, tone === 'error' ? styles.errorMessage : tone === 'success' ? styles.successMessage : null]}>
      <Text style={styles.messageText}>{text}</Text>
    </View>
  );
}

export function LoadingBlock() {
  return (
    <View style={styles.loading}>
      <ActivityIndicator color={colors.blue} />
      <Text style={styles.muted}>Loading latest data...</Text>
    </View>
  );
}

export function EmptyState({ title, body }: { title: string; body: string }) {
  return (
    <View style={styles.empty}>
      <MaterialCommunityIcons name="map-marker-path" size={28} color={colors.gold} />
      <Text style={styles.emptyTitle}>{title}</Text>
      <Text style={styles.muted}>{body}</Text>
    </View>
  );
}

export function OrderCard({ order, children }: { order: Order; children?: ReactNode }) {
  return (
    <View style={styles.orderCard}>
      <View style={styles.orderHeader}>
        <View style={styles.routeIcon}>
          <FontAwesome5 name="route" size={14} color={colors.white} />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={styles.routeText}>{order.pickup_address}</Text>
          <Text style={styles.routeSub}>to {order.dropoff_address}</Text>
        </View>
        <View style={styles.statusPill}>
          <Text style={styles.statusText}>{order.status}</Text>
        </View>
      </View>

      <View style={styles.orderStats}>
        <Text style={styles.statText}>{order.offered_fare ?? order.final_fare} MAD</Text>
        <Text style={styles.statText}>{order.distance_km} km</Text>
        <Text style={styles.statText}>{order.eta_min} min</Text>
        <Text style={styles.statText}>{order.pax} pax</Text>
      </View>

      {children ? <View style={styles.orderActions}>{children}</View> : null}
    </View>
  );
}

export function IconForRole({ role }: { role: string }) {
  if (role === 'driver') return <FontAwesome5 name="car-side" size={18} color={colors.white} />;
  if (role === 'admin') return <Ionicons name="analytics" size={19} color={colors.white} />;
  if (role === 'concierge') return <MaterialCommunityIcons name="bell-outline" size={20} color={colors.white} />;
  return <FontAwesome5 name="map-marked-alt" size={17} color={colors.white} />;
}

export const styles = StyleSheet.create({
  panel: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: radius.lg,
    borderWidth: 1,
    marginBottom: 16,
    padding: 16,
    ...shadow,
  },
  titleBlock: {
    gap: 4,
    marginBottom: 14,
  },
  eyebrow: {
    color: colors.rust,
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  sectionTitle: {
    color: colors.ink,
    fontSize: 24,
    fontWeight: '800',
    lineHeight: 29,
  },
  button: {
    alignItems: 'center',
    borderRadius: radius.sm,
    flexDirection: 'row',
    gap: 9,
    justifyContent: 'center',
    minHeight: 46,
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  primary: {
    backgroundColor: colors.navy,
  },
  secondary: {
    backgroundColor: colors.sand,
    borderColor: colors.line,
    borderWidth: 1,
  },
  ghost: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderWidth: 1,
  },
  danger: {
    backgroundColor: colors.danger,
  },
  disabled: {
    opacity: 0.45,
  },
  pressed: {
    transform: [{ scale: 0.98 }],
  },
  buttonText: {
    color: colors.white,
    fontSize: 15,
    fontWeight: '800',
  },
  darkText: {
    color: colors.ink,
  },
  metric: {
    backgroundColor: colors.cream,
    borderColor: colors.line,
    borderRadius: radius.md,
    borderWidth: 1,
    flex: 1,
    minWidth: 135,
    padding: 14,
  },
  metricIcon: {
    alignItems: 'center',
    backgroundColor: colors.blueSoft,
    borderRadius: 999,
    height: 34,
    justifyContent: 'center',
    marginBottom: 12,
    width: 34,
  },
  metricValue: {
    color: colors.ink,
    fontSize: 22,
    fontWeight: '900',
  },
  metricLabel: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '700',
    marginTop: 2,
  },
  message: {
    backgroundColor: colors.blueSoft,
    borderRadius: radius.md,
    marginBottom: 14,
    padding: 12,
  },
  successMessage: {
    backgroundColor: '#eaf7ee',
  },
  errorMessage: {
    backgroundColor: '#fff0ee',
  },
  messageText: {
    color: colors.ink,
    fontWeight: '700',
  },
  loading: {
    alignItems: 'center',
    gap: 8,
    padding: 20,
  },
  muted: {
    color: colors.muted,
    fontSize: 14,
    lineHeight: 20,
  },
  empty: {
    alignItems: 'center',
    gap: 8,
    padding: 22,
  },
  emptyTitle: {
    color: colors.ink,
    fontSize: 18,
    fontWeight: '800',
  },
  orderCard: {
    backgroundColor: colors.cream,
    borderColor: colors.line,
    borderRadius: radius.md,
    borderWidth: 1,
    marginBottom: 12,
    padding: 14,
  },
  orderHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 12,
  },
  routeIcon: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    borderRadius: 12,
    height: 38,
    justifyContent: 'center',
    width: 38,
  },
  routeText: {
    color: colors.ink,
    fontSize: 15,
    fontWeight: '900',
  },
  routeSub: {
    color: colors.muted,
    fontSize: 13,
    marginTop: 3,
  },
  statusPill: {
    backgroundColor: colors.blueSoft,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  statusText: {
    color: colors.blue,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'capitalize',
  },
  orderStats: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 14,
  },
  statText: {
    backgroundColor: colors.white,
    borderRadius: 999,
    color: colors.inkSoft,
    fontSize: 12,
    fontWeight: '800',
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  orderActions: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginTop: 14,
  },
});
