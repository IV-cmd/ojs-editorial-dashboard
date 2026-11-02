const TOKEN_KEY = 'ojs-dashboard-token';

export class ApiError extends Error {
    constructor(status, message) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
    }
}

// The only place that talks to the backend: adds the bearer token and turns non-2xx responses into ApiError.
export function createApiClient({ fetchImpl = (...args) => fetch(...args), storage = window.localStorage, onUnauthorized = () => {} } = {}) {
    const getToken = () => storage.getItem(TOKEN_KEY);
    const setToken = (token) => storage.setItem(TOKEN_KEY, token);
    const clearToken = () => storage.removeItem(TOKEN_KEY);

    async function get(path, params = {}) {
        const query = new URLSearchParams(params).toString();
        const headers = { Accept: 'application/json' };
        const token = getToken();
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }

        let response;
        try {
            response = await fetchImpl(`/api/${path}${query ? `?${query}` : ''}`, { headers });
        } catch {
            throw new ApiError(0, 'Could not reach the server.');
        }

        if (response.status === 401) {
            clearToken();
            onUnauthorized();
            throw new ApiError(401, 'Your token is missing, invalid, or revoked. Enter a valid token.');
        }

        if (!response.ok) {
            let message = `Request failed with status ${response.status}.`;
            try {
                const body = await response.json();
                if (body?.message) {
                    message = `${message} ${body.message}`;
                }
            } catch {
                // Non-JSON error body: keep the status-only message.
            }
            throw new ApiError(response.status, message);
        }

        return response.json();
    }

    return {
        getToken,
        setToken,
        clearToken,
        user: () => get('user'),
        filters: () => get('meta/filters'),
        summary: () => get('dashboard/summary'),
        stuck: (params = { per_page: 100 }) => get('dashboard/stuck', params),
        overdueReviews: (params = { per_page: 100 }) => get('dashboard/overdue-reviews', params),
    };
}
