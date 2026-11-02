import { describe, expect, it, vi } from 'vitest';
import { ApiError, createApiClient } from '../api';

function memoryStorage(initial = {}) {
    const values = { ...initial };
    return {
        getItem: (key) => values[key] ?? null,
        setItem: (key, value) => { values[key] = value; },
        removeItem: (key) => { delete values[key]; },
    };
}

const json = (status, body) => ({ status, ok: status >= 200 && status < 300, json: async () => body });

describe('api client', () => {
    it('attaches the stored token as a bearer header', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(json(200, { data: {} }));
        const api = createApiClient({ fetchImpl, storage: memoryStorage() });
        api.setToken('1|abc');

        await api.summary();

        expect(fetchImpl).toHaveBeenCalledWith('/api/dashboard/summary', {
            headers: { Accept: 'application/json', Authorization: 'Bearer 1|abc' },
        });
    });

    it('sends no Authorization header without a token', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(json(200, { data: [] }));
        const api = createApiClient({ fetchImpl, storage: memoryStorage() });

        await api.stuck();

        expect(fetchImpl.mock.calls[0][0]).toBe('/api/dashboard/stuck?per_page=100');
        expect(fetchImpl.mock.calls[0][1].headers).not.toHaveProperty('Authorization');
    });

    it('clears the token and reports a 401', async () => {
        const onUnauthorized = vi.fn();
        const api = createApiClient({ fetchImpl: vi.fn().mockResolvedValue(json(401, { message: 'Unauthenticated.' })), storage: memoryStorage(), onUnauthorized });
        api.setToken('bad');

        await expect(api.summary()).rejects.toMatchObject({ status: 401 });
        expect(onUnauthorized).toHaveBeenCalledOnce();
        expect(api.getToken()).toBeNull();
    });

    it('turns other error statuses into an ApiError with the server message', async () => {
        const api = createApiClient({ fetchImpl: vi.fn().mockResolvedValue(json(500, { message: 'Server Error' })), storage: memoryStorage() });

        const error = await api.summary().catch((e) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect(error.status).toBe(500);
        expect(error.message).toBe('Request failed with status 500. Server Error');
    });

    it('reports an unreachable server', async () => {
        const api = createApiClient({ fetchImpl: vi.fn().mockRejectedValue(new TypeError('fetch failed')), storage: memoryStorage() });

        await expect(api.summary()).rejects.toMatchObject({ status: 0, message: 'Could not reach the server.' });
    });
});
