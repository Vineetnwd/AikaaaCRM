import React, { createContext, useContext, useState, useEffect } from 'react';
import { SafeStorage } from '../services/storage';
import { api, STORAGE_KEYS, DEFAULT_BASE_URL } from '../services/api';
import { User, Company } from '../types/crm';

interface AuthContextType {
  user: User | null;
  company: Company | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  isExecutive: boolean;
  isAdmin: boolean;
  isSuperAdmin: boolean;
  isImpersonating: boolean;
  originalSuperAdmin: { token: string; user: User; company: Company | null } | null;
  baseUrl: string;
  login: (identifier: string, password: string) => Promise<{ success: boolean; error?: string }>;
  logout: () => Promise<void>;
  updateBaseUrl: (url: string) => Promise<void>;
  impersonateCompany: (companyId: number | string) => Promise<{ success: boolean; error?: string }>;
  revertImpersonation: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [company, setCompany] = useState<Company | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [originalSuperAdmin, setOriginalSuperAdmin] = useState<{
    token: string;
    user: User;
    company: Company | null;
  } | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [baseUrl, setBaseUrlState] = useState<string>(DEFAULT_BASE_URL);

  useEffect(() => {
    initAuth();
  }, []);

  const initAuth = async () => {
    try {
      await api.init();
      setBaseUrlState(api.getBaseUrl());

      const storedToken = await SafeStorage.getItem(STORAGE_KEYS.TOKEN);
      const storedUser = await SafeStorage.getItem(STORAGE_KEYS.USER);
      const storedCompany = await SafeStorage.getItem(STORAGE_KEYS.COMPANY);
      const storedSuperAdmin = await SafeStorage.getItem('@aikocrm_superadmin_backup');

      if (storedSuperAdmin) {
        setOriginalSuperAdmin(JSON.parse(storedSuperAdmin));
      }

      if (storedToken && storedUser) {
        setToken(storedToken);
        api.setToken(storedToken);
        setUser(JSON.parse(storedUser));
        if (storedCompany) {
          setCompany(JSON.parse(storedCompany));
        }
      }
    } catch (e) {
      console.warn('Auth restoration failed', e);
    } finally {
      setIsLoading(false);
    }
  };

  const login = async (identifier: string, password: string) => {
    try {
      const res = await api.login(identifier, password);
      if (res.success && res.token && res.user) {
        setToken(res.token);
        setUser(res.user);
        setCompany(res.company || null);
        setOriginalSuperAdmin(null);

        api.setToken(res.token);
        await SafeStorage.setItem(STORAGE_KEYS.TOKEN, res.token);
        await SafeStorage.setItem(STORAGE_KEYS.USER, JSON.stringify(res.user));
        if (res.company) {
          await SafeStorage.setItem(STORAGE_KEYS.COMPANY, JSON.stringify(res.company));
        } else {
          await SafeStorage.removeItem(STORAGE_KEYS.COMPANY);
        }
        await SafeStorage.removeItem('@aikocrm_superadmin_backup');
        return { success: true };
      }
      return { success: false, error: res.error || 'Login failed' };
    } catch (err: any) {
      return { success: false, error: err.message || 'Network error occurred' };
    }
  };

  const impersonateCompany = async (companyId: number | string) => {
    try {
      const res = await api.impersonateCompany(companyId);
      if (res.success && res.token && res.user) {
        // ⚡ Set the new token on the api singleton FIRST — before any setState or
        // await calls — so that any render triggered by the state updates below
        // will always call api endpoints with the correct impersonation token.
        api.setToken(res.token);

        // Save current session as Super Admin backup if not already impersonating
        if (!originalSuperAdmin && token && user) {
          const backup = { token, user, company };
          setOriginalSuperAdmin(backup);
          // Fire-and-forget storage write — don't await here so we don't
          // accidentally flush a React render between state updates
          SafeStorage.setItem('@aikocrm_superadmin_backup', JSON.stringify(backup));
        }

        setToken(res.token);
        setUser(res.user);
        setCompany(res.company || null);

        // Persist session to storage (fire-and-forget, non-blocking)
        SafeStorage.setItem(STORAGE_KEYS.TOKEN, res.token);
        SafeStorage.setItem(STORAGE_KEYS.USER, JSON.stringify(res.user));
        if (res.company) {
          SafeStorage.setItem(STORAGE_KEYS.COMPANY, JSON.stringify(res.company));
        } else {
          SafeStorage.removeItem(STORAGE_KEYS.COMPANY);
        }

        return { success: true };
      }
      return { success: false, error: res.error || 'Failed to login as company' };
    } catch (err: any) {
      return { success: false, error: err.message || 'Network error occurred' };
    }
  };

  const revertImpersonation = async () => {
    try {
      let backup = originalSuperAdmin;
      if (!backup) {
        const stored = await SafeStorage.getItem('@aikocrm_superadmin_backup');
        if (stored) backup = JSON.parse(stored);
      }

      if (backup) {
        // ⚡ Restore super admin token on api singleton FIRST
        api.setToken(backup.token);

        setToken(backup.token);
        setUser(backup.user);
        setCompany(backup.company);
        setOriginalSuperAdmin(null);

        // Persist to storage (fire-and-forget)
        SafeStorage.setItem(STORAGE_KEYS.TOKEN, backup.token);
        SafeStorage.setItem(STORAGE_KEYS.USER, JSON.stringify(backup.user));
        if (backup.company) {
          SafeStorage.setItem(STORAGE_KEYS.COMPANY, JSON.stringify(backup.company));
        } else {
          SafeStorage.removeItem(STORAGE_KEYS.COMPANY);
        }
        SafeStorage.removeItem('@aikocrm_superadmin_backup');
      }
    } catch (e) {
      console.warn('Failed to revert impersonation', e);
    }
  };

  const logout = async () => {
    setToken(null);
    setUser(null);
    setCompany(null);
    setOriginalSuperAdmin(null);
    api.setToken(null);
    await SafeStorage.removeItem(STORAGE_KEYS.TOKEN);
    await SafeStorage.removeItem(STORAGE_KEYS.USER);
    await SafeStorage.removeItem(STORAGE_KEYS.COMPANY);
    await SafeStorage.removeItem('@aikocrm_superadmin_backup');
  };

  const updateBaseUrl = async (newUrl: string) => {
    await api.setBaseUrl(newUrl);
    setBaseUrlState(newUrl);
  };

  const isExecutive = user?.role?.toLowerCase() === 'executive';
  const isAdmin = user?.role?.toLowerCase() === 'admin';
  const isSuperAdmin =
    user?.role?.toLowerCase() === 'super_admin' ||
    user?.role?.toLowerCase() === 'superadmin' ||
    !!originalSuperAdmin;
  const isImpersonating = !!originalSuperAdmin;

  return (
    <AuthContext.Provider
      value={{
        user,
        company,
        token,
        isLoading,
        isAuthenticated: !!token && !!user,
        isExecutive,
        isAdmin,
        isSuperAdmin,
        isImpersonating,
        originalSuperAdmin,
        baseUrl,
        login,
        logout,
        updateBaseUrl,
        impersonateCompany,
        revertImpersonation,
      }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
