import React, { useEffect } from 'react';
import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { ActivityIndicator, View, StyleSheet } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider, useAuth } from '../context/AuthContext';
import { Colors } from '../constants/theme';

function RootNavigation() {
  const { isAuthenticated, isLoading } = useAuth();
  const segments = useSegments();
  const router = useRouter();

  useEffect(() => {
    if (isLoading) return;

    const inAuthGroup = segments[0] === '(auth)';

    if (!isAuthenticated && !inAuthGroup) {
      router.replace('/(auth)/login');
    } else if (isAuthenticated && inAuthGroup) {
      router.replace('/(tabs)');
    }
  }, [isAuthenticated, isLoading, segments]);

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={Colors.primary} />
      </View>
    );
  }

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="(auth)" options={{ headerShown: false }} />
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen
        name="lead/[id]"
        options={{
          headerShown: true,
          title: 'Lead Details',
          headerBackTitle: 'Back',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="lead/add"
        options={{
          headerShown: true,
          title: 'Add Opportunity',
          presentation: 'modal',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="invoice/[id]"
        options={{
          headerShown: true,
          title: 'Invoice Details',
          headerBackTitle: 'Back',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="webview"
        options={{
          headerShown: true,
          title: 'Document',
          headerBackTitle: 'Back',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="invoice/add"
        options={{
          headerShown: true,
          title: 'Create Invoice',
          presentation: 'modal',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="quotation/[id]"
        options={{
          headerShown: true,
          title: 'Quotation Details',
          headerBackTitle: 'Back',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="quotation/add"
        options={{
          headerShown: true,
          title: 'Create Quotation',
          presentation: 'modal',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="customer/[id]"
        options={{
          headerShown: true,
          title: 'Customer Profile',
          headerBackTitle: 'Back',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="attendance"
        options={{
          headerShown: true,
          title: 'Attendance',
          headerBackTitle: 'Back',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="commissions"
        options={{
          headerShown: true,
          title: 'Commissions & Performance',
          headerBackTitle: 'Back',
          headerTintColor: Colors.primary,
        }}
      />
      <Stack.Screen
        name="settings"
        options={{
          headerShown: true,
          title: 'Settings & Config',
          headerBackTitle: 'Back',
          headerTintColor: Colors.primary,
        }}
      />
    </Stack>
  );
}

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <AuthProvider>
        <StatusBar style="dark" />
        <RootNavigation />
      </AuthProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#ffffff',
  },
});
