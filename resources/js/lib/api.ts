const csrfToken = (document.querySelector<HTMLElement>('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''

export const apiFetch = async <T>(url: string, options: RequestInit = {}): Promise<Response & { json(): Promise<T> }> => {
    const headers = {
        'X-CSRF-TOKEN': csrfToken,
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
