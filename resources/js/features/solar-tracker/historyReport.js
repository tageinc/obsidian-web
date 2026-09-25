export async function downloadHistoryReport(reportUrl, range, signal) {
    const url = new URL(reportUrl, window.location.origin);
    if (url.origin !== window.location.origin)
        throw new Error('The report must stay on this site.');
    url.searchParams.set('from', new Date(range.start).toISOString());
    url.searchParams.set('to', new Date(range.end).toISOString());
    let response;
    try {
        response = await fetch(url.href, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal,
        });
    } catch (error) {
        if (error.name === 'AbortError') throw error;
        throw new Error('The report could not be downloaded. Please try again.', { cause: error });
    }
    if (!response.ok) {
        const messages = {
            401: 'Please sign in again to download the report.',
            403: 'You do not have access to this device report.',
            404: 'This device could not be found.',
            422: 'Check the selected time range and try again.',
            429: 'Too many requests. Please wait before creating another report.',
        };
        throw new Error(
            messages[response.status] || 'The report could not be created. Please try again.',
        );
    }
    if (!response.headers.get('content-type')?.includes('application/pdf')) {
        throw new Error('Your session may have expired. Refresh this page and sign in again.');
    }
    const blob = await response.blob();
    if (signal?.aborted) throw new DOMException('Request cancelled', 'AbortError');
    const href = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const filename = response.headers
        .get('content-disposition')
        ?.match(/filename="?([\w.-]+\.pdf)"?/i)?.[1];
    link.href = href;
    link.download = filename || 'device-history.pdf';
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(href), 1000);
}
