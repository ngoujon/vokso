import { config } from './config';

const TOKEN_KEY = 'auth_token';

export const tokenStorage = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
};

/** Erreur renvoyée par l'API : message lisible + code machine éventuel (ex. "totp_required"). */
export class ApiError extends Error {
  constructor(message: string, public readonly code?: string, public readonly status?: number) {
    super(message);
    this.name = 'ApiError';
  }
}

export function authHeaders(): Record<string, string> {
  const token = tokenStorage.get();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

/** Message d'une erreur quelconque (catch), pour affichage. */
export function errorMessage(error: unknown, fallback = 'Une erreur est survenue'): string {
  return error instanceof Error ? error.message : fallback;
}

/**
 * Appel JSON à l'API, authentifié si un jeton est présent. Un corps
 * FormData est envoyé tel quel (le navigateur pose alors le Content-Type).
 */
export async function apiRequest<T = unknown>(path: string, options: RequestInit = {}): Promise<T> {
  const isFormData = options.body instanceof FormData;
  const response = await fetch(`${config.apiUrl}/${path}`, {
    ...options,
    headers: {
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...authHeaders(),
      ...(options.headers as Record<string, string> | undefined),
    },
  });

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new ApiError(data.error || 'Une erreur est survenue', data.code, response.status);
  }
  return data as T;
}

/** Téléchargement d'un fichier protégé : un simple <a href> n'enverrait pas le jeton. */
export async function downloadAuthenticated(path: string, fileName: string): Promise<void> {
  const response = await fetch(`${config.apiUrl}/${path}`, { headers: authHeaders() });
  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    throw new ApiError(data.error || 'Téléchargement impossible.', data.code, response.status);
  }
  const url = URL.createObjectURL(await response.blob());
  triggerDownload(url, fileName);
  URL.revokeObjectURL(url);
}

export function triggerDownload(url: string, fileName: string, newTab = false): void {
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName;
  if (newTab) {
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
  }
  document.body.appendChild(link);
  link.click();
  link.remove();
}

export function downloadText(title: string, text: string): void {
  const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
  triggerDownload(url, `${title || 'podcast'}.txt`);
  URL.revokeObjectURL(url);
}
