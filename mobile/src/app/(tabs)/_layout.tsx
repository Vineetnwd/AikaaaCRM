import React from 'react';
import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../../constants/theme';
import { Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../../context/AuthContext';

export default function TabLayout() {
  const insets = useSafeAreaInsets();
  const { isSuperAdmin, isImpersonating } = useAuth();
  const isSuperAdminStandalone = isSuperAdmin && !isImpersonating;

  // Responsive bottom padding: adapts to Android 3-button navigation (~48px), gesture bar (~16-24px), or iOS bar
  const bottomInset = Math.max(insets.bottom, Platform.OS === 'ios' ? 24 : 10);
  const tabHeight = 54 + bottomInset;

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: Colors.primary,
        tabBarInactiveTintColor: '#94a3b8',
        headerShown: false,
        tabBarStyle: {
          backgroundColor: '#ffffff',
          borderTopColor: '#e2e8f0',
          borderTopWidth: 1,
          height: tabHeight,
          paddingBottom: bottomInset,
          paddingTop: 8,
          elevation: 10,
          shadowColor: '#000',
          shadowOffset: { width: 0, height: -3 },
          shadowOpacity: 0.06,
          shadowRadius: 5,
        },
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '700',
          marginTop: -2,
        },
      }}>
      <Tabs.Screen
        name="index"
        options={{
          title: isSuperAdminStandalone ? 'Companies' : 'Dashboard',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons
              name={
                isSuperAdminStandalone
                  ? focused
                    ? 'business'
                    : 'business-outline'
                  : focused
                  ? 'grid'
                  : 'grid-outline'
              }
              size={22}
              color={color}
            />
          ),
        }}
      />
      <Tabs.Screen
        name="leads"
        options={{
          href: isSuperAdminStandalone ? null : '/(tabs)/leads',
          title: 'Leads',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'people' : 'people-outline'} size={22} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="tasks"
        options={{
          href: isSuperAdminStandalone ? null : '/(tabs)/tasks',
          title: 'Tasks',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'checkbox' : 'checkbox-outline'} size={22} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="billing"
        options={{
          href: isSuperAdminStandalone ? null : '/(tabs)/billing',
          title: 'Billing',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'card' : 'card-outline'} size={22} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="more"
        options={{
          title: isSuperAdminStandalone ? 'System' : 'More',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons
              name={
                isSuperAdminStandalone
                  ? focused
                    ? 'shield-checkmark'
                    : 'shield-checkmark-outline'
                  : focused
                  ? 'ellipsis-horizontal-circle'
                  : 'ellipsis-horizontal-circle-outline'
              }
              size={22}
              color={color}
            />
          ),
        }}
      />
    </Tabs>
  );
}
