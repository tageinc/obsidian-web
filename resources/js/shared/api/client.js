import axios from 'axios';

export class RequestError extends Error {
    constructor(message, status = 0, fieldErrors = {}, retryAfter = null) {
        super(message);
        this.name = 'RequestError';
        this.status = status;
        this.fieldErrors = fieldErrors;
        this.retryAfter = retryAfter;
    }
}

export async function requestJson(
    url,
    { method = 'GET', data, signal, csrfToken, headers = {} } = {},
) {
    const destination = new URL(url, window.location.origin);
    if (destination.origin !== window.location.origin) {
        throw new RequestError('This request must stay on this site.');
    }
    try {
        const response = await axios.request({
            url: destination.href,
            method,
            data,
            signal,
            timeout: 15000,
            withCredentials: true,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...headers,
            },
        });
        if (!response.headers['content-type']?.includes('application/json')) {
            throw new RequestError(
                'Your session may have expired. Refresh this page and sign in again.',
                419,
            );
        }
        return response.data;
    } catch (error) {
        if (axios.isCancel(error) || signal?.aborted)
            throw new DOMException('Request cancelled', 'AbortError');
        if (error instanceof RequestError) throw error;
        const status = error.response?.status || 0;
        const messages = {
            401: 'Please sign in again to continue.',
            419: 'Your session has expired. Refresh this page and try again.',
            403: 'You do not have access to this action.',
            404: 'This resource could not be found.',
            410: 'This resource is no longer available.',
            422: 'Please correct the highlighted fields and try again.',
            429: 'Too many requests. Please wait before trying again.',
        };
        const errors = status === 422 ? error.response?.data?.errors || {} : {};
        throw new RequestError(
            messages[status] || 'The request could not be completed. Please try again.',
            status,
            errors,
            error.response?.headers?.['retry-after'],
        );
    }
}
