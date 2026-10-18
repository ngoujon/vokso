import { ApiError, apiRequest, tokenStorage } from './api';

afterEach(() => {
  localStorage.clear();
  jest.restoreAllMocks();
});

test('envoie le jeton et décode la réponse JSON', async () => {
  tokenStorage.set('abc');
  const fetchMock = jest.fn().mockResolvedValue({ ok: true, json: async () => ({ ok: 1 }) });
  global.fetch = fetchMock as unknown as typeof fetch;

  await expect(apiRequest('auth-me')).resolves.toEqual({ ok: 1 });
  expect(fetchMock.mock.calls[0][1].headers).toMatchObject({ Authorization: 'Bearer abc' });
});

test("transforme une erreur de l'API en ApiError avec son code", async () => {
  global.fetch = jest.fn().mockResolvedValue({
    ok: false,
    status: 429,
    json: async () => ({ error: 'Limite atteinte', code: 'quota_reached' }),
  }) as unknown as typeof fetch;

  const error = await apiRequest('generation').catch((e) => e);
  expect(error).toBeInstanceOf(ApiError);
  expect(error).toMatchObject({ message: 'Limite atteinte', code: 'quota_reached', status: 429 });
});

test("n'impose pas de Content-Type JSON pour un envoi de fichier", async () => {
  const fetchMock = jest.fn().mockResolvedValue({ ok: true, json: async () => ({}) });
  global.fetch = fetchMock as unknown as typeof fetch;

  await apiRequest('generation-audio', { method: 'POST', body: new FormData() });
  expect(fetchMock.mock.calls[0][1].headers).not.toHaveProperty('Content-Type');
});
