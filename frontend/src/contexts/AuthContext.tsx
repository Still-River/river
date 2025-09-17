import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, ApiUser, MeResponse } from '../utils/api';

type AuthContextValue = {
  user: ApiUser | null;
  loading: boolean;
  error: string | null;
  loginWithGoogle: () => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<ApiUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const data = await api.get<MeResponse>('/auth/me', { skipAuthHandling: true });
      setUser(data.user);
      setError(null);
    } catch (err) {
      if ((err as Error).message === 'unauthenticated') {
        setUser(null);
        setError(null);
      } else {
        setError((err as Error).message);
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }

    const url = new URL(window.location.href);
    const loginResult = url.searchParams.get('login');
    const errorParam = url.searchParams.get('error');
    const messageParam = url.searchParams.get('message');

    if (loginResult === 'success') {
      void refresh();
    }

    if (errorParam) {
      setError(messageParam ?? errorParam);
    }

    if (loginResult || errorParam || messageParam) {
      url.searchParams.delete('login');
      url.searchParams.delete('error');
      url.searchParams.delete('message');

      const newSearch = url.searchParams.toString();
      const newUrl = `${url.pathname}${newSearch ? `?${newSearch}` : ''}${url.hash}`;
      window.history.replaceState({}, document.title, newUrl);
    }
  }, [refresh]);

  const loginWithGoogle = useCallback(async () => {
    const redirectTarget = typeof window === 'undefined' ? undefined : window.location.href;
    const query = redirectTarget ? `?redirect=${encodeURIComponent(redirectTarget)}` : '';

    try {
      setError(null);

      const data = await api.get<{ authUrl: string }>(`/auth/google/url${query}`, {
        skipAuthHandling: true,
      });

      if (typeof window !== 'undefined') {
        window.location.assign(data.authUrl);
      }
    } catch (err) {
      setError((err as Error).message);
    }
  }, [setError]);

  const logout = useCallback(async () => {
    await api.post('/auth/logout');
    await refresh();
  }, [refresh]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      error,
      loginWithGoogle,
      logout,
      refresh,
    }),
    [user, loading, error, loginWithGoogle, logout, refresh]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
}
