const API_URL = process.env.NEXT_PUBLIC_API_URL;

/**
 * Wrapper fino sobre `fetch` pra chamar a API do Laravel: já resolve a
 * base URL, os headers padrão de JSON, e lança um erro com a mensagem
 * que o Laravel devolveu quando a resposta não é 2xx.
 */
export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...options.headers,
    },
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    throw new Error(body?.message ?? `Erro na API: ${response.status}`);
  }

  // 204 No Content (ex: logout) não tem corpo — response.json() quebraria.
  if (response.status === 204) {
    return null as T;
  }

  return response.json() as Promise<T>;
}
