const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8080';

type RequestOptions = RequestInit & { skipAuthHandling?: boolean };

type JsonValue = unknown;

async function request<T extends JsonValue>(path: string, options: RequestOptions = {}): Promise<T> {
  const { skipAuthHandling, headers, ...rest } = options;

  const response = await fetch(`${API_URL}${path}`, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...headers,
    },
    ...rest,
  });

  if (!response.ok) {
    if (response.status === 401 && !skipAuthHandling) {
      return Promise.reject(new Error('unauthenticated'));
    }

    const text = await response.text();
    let message = text || 'Request failed';

    try {
      const data = JSON.parse(text) as { error?: string; message?: string };
      if (typeof data.message === 'string' && data.message.trim() !== '') {
        message = data.message;
      } else if (typeof data.error === 'string' && data.error.trim() !== '') {
        message = data.error;
      }
    } catch (jsonError) {
      // leave message as-is when body is not JSON
    }

    throw new Error(message);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

export const api = {
  get: <T extends JsonValue>(path: string, options?: RequestOptions) => request<T>(path, { ...options, method: 'GET' }),
  post: <T extends JsonValue>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>(path, {
      ...options,
      method: 'POST',
      body: body !== undefined ? JSON.stringify(body) : undefined,
    }),
  put: <T extends JsonValue>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>(path, {
      ...options,
      method: 'PUT',
      body: body !== undefined ? JSON.stringify(body) : undefined,
    }),
};

export type ApiUser = {
  id: number;
  email: string;
  name: string | null;
  avatarUrl: string | null;
};

export type MeResponse = {
  user: ApiUser | null;
};

export type JournalPrompt = {
  id: number;
  key: string;
  title: string;
  question: string;
  guidance: string;
  placeholder: string;
  examples: string[];
  optional: boolean;
  position: number;
};

export type JournalSummary = {
  id: number;
  slug: string;
  title: string;
  description: string | null;
};

export type JournalDetailResponse = {
  journal: JournalSummary & {
    prompts: JournalPrompt[];
  };
  userState: JournalState | null;
  responses: Record<string, string>;
};

export type JournalState = {
  activeStep: number;
  todo: boolean;
  skippedAt: string | null;
  lastUpdated: string | null;
};

export type SaveJournalResponse = {
  state: {
    activeStep: number;
    todo: boolean;
    skippedAt: string | null;
    lastUpdated: string;
  };
  responses: Record<string, string>;
};
