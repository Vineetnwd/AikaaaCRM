import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Alert,
  StatusBar,
  Image,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../../context/AuthContext';
import { Colors, Spacing, Radius } from '../../constants/theme';

export default function LoginScreen() {
  const insets = useSafeAreaInsets();
  const { login } = useAuth();

  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Dynamic safe top padding
  const androidBarHeight = StatusBar.currentHeight || 24;
  const topPadding = Math.max(insets.top, Platform.OS === 'android' ? androidBarHeight : 20);
  const bottomPadding = Platform.OS === 'android' ? 24 : Math.max(insets.bottom, 16);

  const normalizeIdentifier = (raw: string): string => {
    const trimmed = raw.trim();
    if (trimmed.includes('@')) {
      return trimmed.toLowerCase();
    }
    // Clean spaces, hyphens, and non-digits
    const digitsOnly = trimmed.replace(/\D/g, '');
    if (digitsOnly.length === 12 && digitsOnly.startsWith('91')) {
      return digitsOnly.substring(2);
    }
    if (digitsOnly.length === 11 && digitsOnly.startsWith('0')) {
      return digitsOnly.substring(1);
    }
    if (digitsOnly.length === 10) {
      return digitsOnly;
    }
    return trimmed;
  };

  const handleLogin = async () => {
    const rawId = identifier.trim();
    const cleanPass = password.trim();

    if (!rawId || !cleanPass) {
      Alert.alert('Required Fields', 'Please enter your mobile/email and password.');
      return;
    }

    setErrorMessage(null);
    setLoading(true);

    const normalized = normalizeIdentifier(rawId);

    // Attempt 1: Normalized (clean 10-digit mobile or lowercase email)
    let result = await login(normalized, password);

    // Attempt 2: If normalized differed and failed, try exact raw input
    if (!result.success && normalized !== rawId) {
      const fallbackResult = await login(rawId, password);
      if (fallbackResult.success) {
        result = fallbackResult;
      }
    }

    setLoading(false);

    if (!result.success) {
      const errorText = result.error || 'Invalid credentials or inactive account.';
      setErrorMessage(errorText);
      Alert.alert('Login Failed', errorText);
    }
  };

  return (
    <View style={styles.container}>
      {/* Top Header Bar with Brand */}
      <View style={[styles.topBar, { paddingTop: topPadding + 6 }]}>
        <View style={styles.brandPill}>
          <Image
            source={require('../../../assets/images/logo.png')}
            style={styles.brandPillLogo}
            resizeMode="contain"
          />
          <Text style={styles.brandPillText}>AIKAAA</Text>
        </View>
      </View>

      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={{ flex: 1 }}>
        <ScrollView
          contentContainerStyle={[
            styles.scrollContent,
            { paddingBottom: bottomPadding + 32 },
          ]}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}>

          {/* Hero Branding */}
          <View style={styles.heroSection}>
            <View style={styles.logoBadgeContainer}>
              <View style={styles.logoBadgeGlow} />
              <View style={styles.logoCard}>
                <Image
                  source={require('../../../assets/images/logo.png')}
                  style={styles.logoImage}
                  resizeMode="contain"
                />
              </View>
            </View>
            <Text style={styles.appName}>AIKAAA CRM</Text>
            <Text style={styles.appTagline}>
              Next-Gen Enterprise Lead & Task Platform
            </Text>
          </View>

          {/* Main Card */}
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.cardTitle}>Sign In</Text>
              <Text style={styles.cardSubtitle}>
                Access your leads, tasks, and accounts
              </Text>
            </View>

            {/* Error Banner */}
            {errorMessage && (
              <View style={styles.errorBanner}>
                <Ionicons name="alert-circle" size={18} color="#ef4444" style={{ marginTop: 1 }} />
                <View style={{ flex: 1 }}>
                  <Text style={styles.errorBannerTitle}>Authentication Failed</Text>
                  <Text style={styles.errorBannerText}>{errorMessage}</Text>
                  {errorMessage.toLowerCase().includes('inactive') && (
                    <Text style={styles.errorBannerSubText}>
                      Tip: Employee accounts must be marked active by company administrator before signing in.
                    </Text>
                  )}
                </View>
              </View>
            )}

            {/* Identifier Input */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>Mobile Number or Email</Text>
              <View style={styles.inputWrapper}>
                <View style={styles.inputIconBox}>
                  <Ionicons
                    name="person-outline"
                    size={17}
                    color="#6366f1"
                  />
                </View>
                <TextInput
                  style={styles.input}
                  placeholder="e.g. admin@example.com"
                  placeholderTextColor="#94a3b8"
                  value={identifier}
                  onChangeText={setIdentifier}
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="email-address"
                />
                {identifier.length > 0 && (
                  <TouchableOpacity onPress={() => setIdentifier('')} style={{ padding: 4 }}>
                    <Ionicons name="close-circle" size={16} color="#cbd5e1" />
                  </TouchableOpacity>
                )}
              </View>
            </View>

            {/* Password Input */}
            <View style={styles.inputGroup}>
              <View style={styles.passwordLabelRow}>
                <Text style={styles.label}>Password</Text>
                <TouchableOpacity
                  onPress={() =>
                    Alert.alert(
                      'Forgot Password?',
                      'Please contact your CRM administrator or company owner to reset your password.'
                    )
                  }>
                  <Text style={styles.forgotPassText}>Forgot?</Text>
                </TouchableOpacity>
              </View>

              <View style={styles.inputWrapper}>
                <View style={styles.inputIconBox}>
                  <Ionicons
                    name="lock-closed-outline"
                    size={17}
                    color="#6366f1"
                  />
                </View>
                <TextInput
                  style={[styles.input, { flex: 1 }]}
                  placeholder="Enter your password"
                  placeholderTextColor="#94a3b8"
                  value={password}
                  onChangeText={setPassword}
                  secureTextEntry={!showPassword}
                  autoCapitalize="none"
                />
                <TouchableOpacity
                  onPress={() => setShowPassword(!showPassword)}
                  style={styles.eyeBtn}>
                  <Ionicons
                    name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                    size={18}
                    color="#94a3b8"
                  />
                </TouchableOpacity>
              </View>
            </View>

            {/* Submit Button */}
            <TouchableOpacity
              style={[styles.loginBtn, loading && styles.loginBtnDisabled]}
              onPress={handleLogin}
              disabled={loading}
              activeOpacity={0.85}>
              {loading ? (
                <View style={styles.loadingRow}>
                  <ActivityIndicator color="#ffffff" size="small" />
                  <Text style={styles.loginBtnText}>Signing In...</Text>
                </View>
              ) : (
                <>
                  <Text style={styles.loginBtnText}>Sign In to Dashboard</Text>
                  <Ionicons name="arrow-forward" size={18} color="#ffffff" />
                </>
              )}
            </TouchableOpacity>
          </View>

          {/* Footer note */}
          <View style={styles.footer}>
            <View style={styles.footerRow}>
              <Ionicons name="lock-closed" size={12} color="#94a3b8" />
              <Text style={styles.footerText}>
                Secured Enterprise Access • Aikaa CRM v1.0
              </Text>
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>

    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: Spacing.lg,
    paddingBottom: 8,
  },
  brandPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    backgroundColor: '#e0e7ff',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: Radius.full,
  },
  brandPillLogo: {
    width: 18,
    height: 18,
  },
  brandPillText: {
    fontSize: 11,
    fontWeight: '800',
    color: '#4338ca',
    letterSpacing: 0.5,
  },
  scrollContent: {
    paddingHorizontal: Spacing.xl,
    paddingTop: Spacing.md,
  },
  heroSection: {
    alignItems: 'center',
    marginBottom: Spacing.xl,
    marginTop: Spacing.sm,
  },
  logoBadgeContainer: {
    position: 'relative',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: Spacing.md,
  },
  logoBadgeGlow: {
    position: 'absolute',
    width: 90,
    height: 90,
    borderRadius: 28,
    backgroundColor: '#6366f1',
    opacity: 0.15,
    transform: [{ scale: 1.15 }],
  },
  logoCard: {
    width: 86,
    height: 86,
    borderRadius: 24,
    backgroundColor: '#ffffff',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 10,
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.12,
    shadowRadius: 12,
    elevation: 6,
    borderWidth: 1.5,
    borderColor: '#e0e7ff',
  },
  logoImage: {
    width: '100%',
    height: '100%',
  },
  appName: {
    fontSize: 26,
    fontWeight: '800',
    color: '#0f172a',
    letterSpacing: -0.5,
  },
  appTagline: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 4,
    textAlign: 'center',
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.xl,
    padding: Spacing.xl,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 12,
    elevation: 3,
  },
  cardHeader: {
    marginBottom: Spacing.lg,
  },
  cardTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0f172a',
    letterSpacing: -0.3,
  },
  cardSubtitle: {
    fontSize: 13,
    color: '#64748b',
    marginTop: 3,
  },
  errorBanner: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    backgroundColor: '#fef2f2',
    borderColor: '#fca5a5',
    borderWidth: 1,
    borderRadius: Radius.md,
    padding: 12,
    marginBottom: Spacing.lg,
  },
  errorBannerTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#991b1b',
  },
  errorBannerText: {
    fontSize: 12,
    color: '#b91c1c',
    marginTop: 2,
    lineHeight: 16,
  },
  errorBannerSubText: {
    fontSize: 11,
    color: '#7f1d1d',
    marginTop: 6,
    lineHeight: 15,
    fontStyle: 'italic',
  },
  inputGroup: {
    marginBottom: Spacing.lg,
  },
  passwordLabelRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  label: {
    fontSize: 11,
    fontWeight: '700',
    color: '#475569',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginBottom: 6,
  },
  forgotPassText: {
    fontSize: 12,
    color: Colors.primary,
    fontWeight: '600',
  },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    borderWidth: 1.5,
    borderColor: '#cbd5e1',
    borderRadius: Radius.lg,
    paddingHorizontal: 12,
    height: 52,
  },
  inputIconBox: {
    width: 32,
    height: 32,
    borderRadius: Radius.sm,
    backgroundColor: '#eef2ff',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  input: {
    flex: 1,
    height: 50,
    fontSize: 14,
    color: '#0f172a',
  },
  eyeBtn: {
    padding: 8,
  },
  loginBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#4f46e5',
    height: 50,
    borderRadius: Radius.lg,
    marginTop: Spacing.sm,
    shadowColor: '#4f46e5',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 4,
  },
  loginBtnDisabled: {
    opacity: 0.7,
  },
  loadingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  loginBtnText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0.2,
  },
  footer: {
    alignItems: 'center',
    marginTop: Spacing.xxl,
  },
  footerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  footerText: {
    fontSize: 11,
    color: '#94a3b8',
    fontWeight: '500',
  },
});
