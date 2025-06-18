export const apiFetch = async <T>(url: string, options: RequestInit = {}): Promise<Response & { json(): Promise<T> }> => {
    // Get fresh CSRF token on every request
    const getCsrfToken = () => {
        const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
        return meta?.content || '';
    };

    const headers = {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Content-Type': 'application/json',
        ...options.headers,
    }

    const response = await fetch(url, {
        ...options,
        headers,
        credentials: 'same-origin',
    })

    if (!response.ok) {
        const error = new Error(response.statusText)
        error.message = `${error.message}. Status: ${response.status}`
        throw error
    }

    return response as Response & { json(): Promise<T> }
}
