import React, { useState, useEffect, useRef } from 'react';
import { View, StyleSheet, ActivityIndicator } from 'react-native';
import { useLocalSearchParams, Stack } from 'expo-router';
import { WebView } from 'react-native-webview';
import * as Print from 'expo-print';
import { Ionicons } from '@expo/vector-icons';
import { SafeStorage } from '../services/storage';
import { STORAGE_KEYS, api } from '../services/api';
import { Colors } from '../constants/theme';

export default function WebViewScreen() {
  const { url, title } = useLocalSearchParams<{ url: string; title?: string }>();
  const [authUrl, setAuthUrl] = useState<string | null>(null);
  const webViewRef = useRef<WebView>(null);

  useEffect(() => {
    (async () => {
      if (!url) return;
      const token = await SafeStorage.getItem(STORAGE_KEYS.TOKEN);
      if (token) {
        const separator = url.includes('?') ? '&' : '?';
        setAuthUrl(`${url}${separator}auth_token=${token}`);
      } else {
        setAuthUrl(url);
      }
    })();
  }, [url]);

  const handlePrint = () => {
    webViewRef.current?.injectJavaScript(`
      window.ReactNativeWebView.postMessage(document.documentElement.outerHTML);
      true;
    `);
  };

  const onMessage = async (event: any) => {
    try {
      const htmlContent = event.nativeEvent.data;
      // Provide a base URL so any relative images/css can load correctly
      const baseUrl = api.getBaseUrl().replace('/api', '');
      
      // We must inject a base tag to ensure assets load during print
      const printableHtml = htmlContent.includes('<head>') 
        ? htmlContent.replace('<head>', `<head><base href="${baseUrl}/" />`)
        : `<head><base href="${baseUrl}/" /></head>` + htmlContent;

      await Print.printAsync({ html: printableHtml });
    } catch (e) {
      console.warn('Failed to print HTML', e);
    }
  };

  if (!url || !authUrl) return null;

  const hideWebPrintBtnScript = `
    const btns = document.querySelectorAll('button');
    btns.forEach(btn => {
      if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes('window.print')) {
        btn.style.display = 'none';
      }
    });
    // Also hide any div with no-print class completely
    const noPrint = document.querySelectorAll('.no-print');
    noPrint.forEach(el => el.style.display = 'none');
    true;
  `;

  return (
    <View style={styles.container}>
      <Stack.Screen 
        options={{ 
          title: title || 'Document', 
          headerBackTitle: 'Back',
          headerRight: () => (
            <Ionicons 
              name="print-outline" 
              size={24} 
              color={Colors.primary} 
              onPress={handlePrint}
              style={{ marginRight: 15 }}
            />
          ),
        }} 
      />
      <WebView 
        ref={webViewRef}
        source={{ uri: authUrl }} 
        style={styles.webview}
        startInLoadingState={true}
        injectedJavaScript={hideWebPrintBtnScript}
        onMessage={onMessage}
        renderLoading={() => (
          <View style={styles.loader}>
            <ActivityIndicator size="large" color={Colors.primary} />
          </View>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  webview: {
    flex: 1,
  },
  loader: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#ffffff',
  }
});
