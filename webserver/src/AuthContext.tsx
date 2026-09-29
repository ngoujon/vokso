import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { apiRequest, tokenStorage } from './api';
import type { User } from './types';

interface AuthResponse {
  token: string;
  user: User;
}

interface AuthContextValue {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string, totpCode?: string) => Promise<User>;
  register: (email: string, password: string, website?: string) => Promise<User>;
  logout: () => Promise<void>;
  changePassword: (currentPassword: string, newPassword: string) => Promise<void>;
  setup2fa: () => Promise<{ secret: string; otpauth_uri: string }>;
  enable2fa: (totpCode: string) => Promise<void>;
  disable2fa: (password: string) => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const loadUser = useCallback(async () => {
    if (!tokenStorage.get()) {
      setUser(null);
      setLoading(false);
      return;
    }
    try {
      const data = await apiRequest<{ user: User }>('auth-me');
      setUser(data.user);
    } catch {
      tokenStorage.clear();
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadUser();
  }, [loadUser]);

  const authenticate = async (path: string, body: Record<string, string>): Promise<User> => {
    const data = await apiRequest<AuthResponse>(path, { method: 'POST', body: JSON.stringify(body) });
    tokenStorage.set(data.token);
    setUser(data.user);
    return data.user;
  };

  const login = (email: string, password: string, totpCode = '') =>
    authenticate('auth-login', { email, password, totp_code: totpCode });

  const register = (email: string, password: string, website = '') =>
    authenticate('auth-register', { email, password, website });

  const logout = async () => {
    try {
      await apiRequest('auth-logout', { method: 'POST' });
    } catch {
      // Déconnexion locale dans tous les cas.
    } finally {
      tokenStorage.clear();
      setUser(null);
    }
  };

  const changePassword = async (currentPassword: string, newPassword: string) => {
    await apiRequest('auth-change-password', {
      method: 'POST',
      body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }),
    });
    await loadUser();
  };

  const setup2fa = () => apiRequest<{ secret: string; otpauth_uri: string }>('auth-2fa-setup', { method: 'POST' });

  const enable2fa = async (totpCode: string) => {
    await apiRequest('auth-2fa-enable', { method: 'POST', body: JSON.stringify({ totp_code: totpCode }) });
    await loadUser();
  };

  const disable2fa = async (password: string) => {
    await apiRequest('auth-2fa-disable', { method: 'POST', body: JSON.stringify({ password }) });
    await loadUser();
  };

  return (
    <AuthContext.Provider
      value={{ user, loading, login, register, logout, changePassword, setup2fa, enable2fa, disable2fa }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuth doit être utilisé à l'intérieur de AuthProvider");
  }
  return ctx;
}
