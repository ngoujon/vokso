import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { config } from './config';

const AuthContext = createContext(null);

async function apiRequest(path, options = {}) {
  const token = localStorage.getItem('auth_token');
  const response = await fetch(`${config.apiUrl}/${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
  });

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(data.error || 'Une erreur est survenue');
    error.code = data.code;
    throw error;
  }
  return data;
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const loadUser = useCallback(async () => {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      setUser(null);
      setLoading(false);
      return;
    }
    try {
      const data = await apiRequest('auth-me');
      setUser(data.user);
    } catch (e) {
      localStorage.removeItem('auth_token');
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadUser();
  }, [loadUser]);

  const login = async (email, password, totpCode = '') => {
    const data = await apiRequest('auth-login', {
      method: 'POST',
      body: JSON.stringify({ email, password, totp_code: totpCode }),
    });
    localStorage.setItem('auth_token', data.token);
    setUser(data.user);
    return data.user;
  };

  const register = async (email, password, website = '') => {
    const data = await apiRequest('auth-register', {
      method: 'POST',
      body: JSON.stringify({ email, password, website }),
    });
    localStorage.setItem('auth_token', data.token);
    setUser(data.user);
    return data.user;
  };

  const logout = async () => {
    try {
      await apiRequest('auth-logout', { method: 'POST' });
    } finally {
      localStorage.removeItem('auth_token');
      setUser(null);
    }
  };

  const changePassword = async (currentPassword, newPassword) => {
    await apiRequest('auth-change-password', {
      method: 'POST',
      body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }),
    });
    await loadUser();
  };

  const setup2fa = () => apiRequest('auth-2fa-setup', { method: 'POST' });

  const enable2fa = async (totpCode) => {
    await apiRequest('auth-2fa-enable', { method: 'POST', body: JSON.stringify({ totp_code: totpCode }) });
    await loadUser();
  };

  const disable2fa = async (password) => {
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

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth doit être utilisé à l\'intérieur de AuthProvider');
  }
  return ctx;
}

export { apiRequest };
