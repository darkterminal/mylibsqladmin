export const apiFetch = async <T>(url: string, options: RequestInit = {}): Promise<Response & { json(): Promise<T> }> => {
    // Get fresh CSRF token on every request
    const getCsrfToken = () => {
        const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
        return meta?.content || '';
    };

    const headers = {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...options.headers,
    }

    const response = await fetch(url, {
        ...options,
        headers,
        credentials: 'same-origin',
    })

    if (!response.ok) {
        // Surface the server's message (validation 422s, our JSON 500s)
        // instead of a bare statusText, so callers can toast something
        // actionable.
        let message = response.statusText || `HTTP ${response.status}`
        try {
            const body = await response.clone().json()

            if (body && typeof body.message === 'string' && body.message.trim()) {
                message = body.message
            } else if (body && body.errors && typeof body.errors === 'object') {
                // Laravel validation failures often omit the top-level message
                // (e.g. { success: false, errors: { name: ["Group name already exists."] } }).
                const first = Object.values(body.errors as Record<string, unknown>)
                    .flatMap((value) => Array.isArray(value) ? value : [])
                    .find((entry): entry is string => typeof entry === 'string' && entry.trim().length > 0)

                if (first) {
                    message = first
                }
            }
        } catch {
            // Body was not JSON — fall back to the status text.
        }
        throw new Error(`${message} (HTTP ${response.status})`)
    }

    return response as Response & { json(): Promise<T> }
}
