import AsyncStorage from '@react-native-async-storage/async-storage';
import { Platform } from 'react-native';

const memoryStore: Record<string, string> = {};

export const SafeStorage = {
  async getItem(key: string): Promise<string | null> {
    try {
      if (Platform.OS === 'web' && typeof window !== 'undefined' && window.localStorage) {
        return window.localStorage.getItem(key) ?? memoryStore[key] ?? null;
      }
      return await AsyncStorage.getItem(key);
    } catch (e) {
      console.warn(`[SafeStorage] getItem('${key}') fallback to memory:`, e);
      return memoryStore[key] ?? null;
    }
  },

  async setItem(key: string, value: string): Promise<void> {
    memoryStore[key] = value;
    try {
      if (Platform.OS === 'web' && typeof window !== 'undefined' && window.localStorage) {
        window.localStorage.setItem(key, value);
        return;
      }
      await AsyncStorage.setItem(key, value);
    } catch (e) {
      console.warn(`[SafeStorage] setItem('${key}') fallback to memory:`, e);
    }
  },

  async removeItem(key: string): Promise<void> {
    delete memoryStore[key];
    try {
      if (Platform.OS === 'web' && typeof window !== 'undefined' && window.localStorage) {
        window.localStorage.removeItem(key);
        return;
      }
      await AsyncStorage.removeItem(key);
    } catch (e) {
      console.warn(`[SafeStorage] removeItem('${key}') fallback to memory:`, e);
    }
  },
};
