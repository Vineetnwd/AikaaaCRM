import { Platform } from 'react-native';

export const Colors = {
  primary: '#4f46e5',
  primaryDark: '#4338ca',
  primaryLight: '#e0e7ff',
  success: '#10b981',
  successLight: '#d1fae5',
  warning: '#f59e0b',
  warningLight: '#fef3c7',
  danger: '#ef4444',
  dangerLight: '#fee2e2',
  info: '#3b82f6',
  infoLight: '#dbeafe',
  purple: '#8b5cf6',
  purpleLight: '#ede9fe',

  light: {
    text: '#0f172a',
    textSecondary: '#64748b',
    textMuted: '#94a3b8',
    background: '#f8fafc',
    backgroundElement: '#f1f5f9',
    backgroundSelected: '#e2e8f0',
    card: '#ffffff',
    border: '#e2e8f0',
    inputBg: '#ffffff',
    tint: '#4f46e5',
  },
  dark: {
    text: '#f8fafc',
    textSecondary: '#94a3b8',
    textMuted: '#64748b',
    background: '#090d16',
    backgroundElement: '#131b2e',
    backgroundSelected: '#1e293b',
    card: '#131b2e',
    border: '#1e293b',
    inputBg: '#1e293b',
    tint: '#6366f1',
  },
};

export type ThemeColor = 'text' | 'textSecondary' | 'textMuted' | 'background' | 'backgroundElement' | 'backgroundSelected' | 'card' | 'border' | 'inputBg' | 'tint';

export const Spacing = {
  half: 2,
  one: 4,
  two: 8,
  three: 16,
  four: 24,
  five: 32,
  six: 64,
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
  xxxl: 32,
} as const;

export const Radius = {
  sm: 6,
  md: 10,
  lg: 14,
  xl: 18,
  full: 9999,
};

export const Fonts = Platform.select({
  ios: {
    sans: 'system-ui',
    serif: 'ui-serif',
    rounded: 'ui-rounded',
    mono: 'ui-monospace',
  },
  default: {
    sans: 'normal',
    serif: 'serif',
    rounded: 'normal',
    mono: 'monospace',
  },
  web: {
    sans: 'var(--font-display)',
    serif: 'var(--font-serif)',
    rounded: 'var(--font-rounded)',
    mono: 'var(--font-mono)',
  },
});

export const BottomTabInset = Platform.select({ ios: 50, android: 80 }) ?? 0;
export const MaxContentWidth = 800;
