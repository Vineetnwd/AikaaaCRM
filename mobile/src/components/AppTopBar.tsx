import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Platform,
  StatusBar,
  Image,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useAuth } from '../context/AuthContext';
import { Colors, Radius, Spacing } from '../constants/theme';

interface AppTopBarProps {
  title: string;
  subtitle?: string;
  showBack?: boolean;
  rightAction?: {
    icon: keyof typeof Ionicons.glyphMap;
    label?: string;
    onPress: () => void;
    color?: string;
  };
}

export const AppTopBar: React.FC<AppTopBarProps> = ({
  title,
  subtitle,
  showBack = false,
  rightAction,
}) => {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { user, company, isSuperAdmin, isImpersonating, revertImpersonation, baseUrl } = useAuth();
  const [logoError, setLogoError] = React.useState(false);

  // Responsive top padding: accurately accounts for Android punch-hole cameras, notches & iOS Dynamic Island
  const androidBarHeight = StatusBar.currentHeight || 24;
  const topPadding = Math.max(insets.top, Platform.OS === 'android' ? androidBarHeight : 20);

  const remoteLogoUrl = company?.logo_path
    ? (company.logo_path.startsWith('http')
        ? company.logo_path
        : `${baseUrl.replace(/\/+$/, '')}/public/${company.logo_path.replace(/^\/+/, '').replace(/^public\//, '')}`)
    : null;

  const logoSource = (!logoError && remoteLogoUrl)
    ? { uri: remoteLogoUrl }
    : require('../../assets/images/logo.png');

  return (
    <View style={[styles.wrapper, { paddingTop: topPadding }]}>
      {/* Impersonation Banner if active */}
      {isImpersonating && (
        <View style={styles.impersonateStrip}>
          <View style={styles.impersonateLeft}>
            <Ionicons name="shield-checkmark" size={14} color="#ffffff" />
            <Text style={styles.impersonateStripText} numberOfLines={1}>
              IMPERSONATING: {company?.name?.toUpperCase() || 'TENANT'}
            </Text>
          </View>
          <TouchableOpacity
            style={styles.impersonateExitBtn}
            onPress={() => revertImpersonation()}>
            <Ionicons name="exit-outline" size={12} color="#b91c1c" />
            <Text style={styles.impersonateExitText}>Exit</Text>
          </TouchableOpacity>
        </View>
      )}

      {/* Main Bar Content */}
      <View style={styles.contentRow}>
        <View style={styles.leftContainer}>
          {showBack ? (
            <TouchableOpacity style={styles.backBtn} onPress={() => router.back()}>
              <Ionicons name="chevron-back" size={24} color="#0f172a" />
            </TouchableOpacity>
          ) : (
            <Image
              source={logoSource}
              onError={() => setLogoError(true)}
              style={styles.logoMark}
              resizeMode="contain"
            />
          )}

          <View style={{ flex: 1 }}>
            <View style={styles.titleRow}>
              <Text style={styles.titleText} numberOfLines={1}>
                {title}
              </Text>

              {/* Tenant / Role Indicator */}
              {isSuperAdmin && !isImpersonating && (
                <View style={styles.superBadge}>
                  <Text style={styles.superBadgeText}>SUPER ADMIN</Text>
                </View>
              )}
            </View>

            {subtitle ? (
              <Text style={styles.subtitleText} numberOfLines={1}>
                {subtitle}
              </Text>
            ) : company?.name ? (
              <Text style={styles.companySubText} numberOfLines={1}>
                🏢 {company.name}
              </Text>
            ) : null}
          </View>
        </View>

        {/* Right Action or Profile Button */}
        <View style={styles.rightContainer}>
          {rightAction ? (
            <TouchableOpacity
              style={styles.rightActionBtn}
              onPress={rightAction.onPress}>
              <Ionicons
                name={rightAction.icon}
                size={20}
                color={rightAction.color || Colors.primary}
              />
              {rightAction.label ? (
                <Text
                  style={[
                    styles.rightActionLabel,
                    { color: rightAction.color || Colors.primary },
                  ]}>
                  {rightAction.label}
                </Text>
              ) : null}
            </TouchableOpacity>
          ) : (
            <TouchableOpacity
              style={styles.avatarBtn}
              onPress={() => router.push('/(tabs)/more')}>
              <Text style={styles.avatarLetter}>
                {(user?.name || 'A')[0].toUpperCase()}
              </Text>
            </TouchableOpacity>
          )}
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  wrapper: {
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 4,
    elevation: 3,
    zIndex: 100,
  },
  impersonateStrip: {
    backgroundColor: '#dc2626',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.md,
    paddingVertical: 6,
    gap: 8,
  },
  impersonateLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flex: 1,
  },
  impersonateStripText: {
    color: '#ffffff',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  impersonateExitBtn: {
    backgroundColor: '#ffffff',
    paddingVertical: 2,
    paddingHorizontal: 8,
    borderRadius: Radius.full,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
  },
  impersonateExitText: {
    color: '#b91c1c',
    fontSize: 10,
    fontWeight: '800',
  },
  contentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.lg,
    paddingVertical: 10,
    minHeight: 52,
  },
  leftContainer: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  logoMark: {
    width: 28,
    height: 28,
  },
  backBtn: {
    paddingRight: 4,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  titleText: {
    fontSize: 18,
    fontWeight: '800',
    color: '#0f172a',
    letterSpacing: -0.3,
  },
  subtitleText: {
    fontSize: 11,
    color: '#64748b',
    marginTop: 1,
    fontWeight: '500',
  },
  companySubText: {
    fontSize: 11,
    color: '#6366f1',
    marginTop: 1,
    fontWeight: '600',
  },
  superBadge: {
    backgroundColor: '#ede9fe',
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: Radius.sm,
    borderWidth: 1,
    borderColor: '#ddd6fe',
  },
  superBadgeText: {
    color: '#6d28d9',
    fontSize: 9,
    fontWeight: '800',
  },
  rightContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginLeft: 8,
  },
  rightActionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: Radius.md,
    backgroundColor: '#f1f5f9',
  },
  rightActionLabel: {
    fontSize: 12,
    fontWeight: '700',
  },
  avatarBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 3,
    elevation: 2,
  },
  avatarLetter: {
    fontSize: 14,
    fontWeight: '800',
    color: '#ffffff',
  },
});
