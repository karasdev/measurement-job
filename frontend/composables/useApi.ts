/** Options for api fetch; body can be a plain object (will be JSON-stringified). */
type ApiFetchOptions = Omit<RequestInit, 'body'> & {
  body?: BodyInit | Record<string, unknown>
}

export function useApi() {
  const config = useRuntimeConfig()
  const apiBase = (config.public.apiBase as string).replace(/\/$/, '')
  const token = useCookie('auth_token')

  function fetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
    const url = path.startsWith('http') ? path : `${apiBase}${path.startsWith('/') ? '' : '/'}${path}`
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(options.headers as Record<string, string>),
    }
    if (token.value) {
      (headers as Record<string, string>)['Authorization'] = `Bearer ${token.value}`
    }
    const body =
      options.body != null && typeof options.body === 'object' && (options.body as object).constructor === Object
        ? JSON.stringify(options.body)
        : options.body
    const fetchOptions = { ...options, headers, body }
    return $fetch<T>(url, fetchOptions as Parameters<typeof $fetch>[1])
  }

  return { apiBase, token, fetch }
}
