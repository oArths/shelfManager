// Serviço para comunicação direta com a API do WordPress.
// Criado para eliminar a ida ao backend na busca de produtos (performance).

const WORDPRESS_API_URL = import.meta.env.VITE_WORDPRESS_API_URL;

interface WordPressApiOptions extends RequestInit {
  params?: Record<string, string>;
}

export async function wordpressApiFetch<T = any>(
  endpoint: string,
  options: WordPressApiOptions = {}
): Promise<T> {
  const url = new URL(`${WORDPRESS_API_URL}${endpoint}`);

  if (options.params) {
    Object.entries(options.params).forEach(([key, value]) => {
      url.searchParams.append(key, value);
    });
  }

  const response = await fetch(url.toString(), {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      // Se o plugin exigir chave de API, descomente a linha abaixo:
      // 'X-API-Key': 'SHELF_MANAGER_2024',
      ...options.headers,
    },
  });

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    throw new Error(data?.message || 'Erro na requisição ao WordPress');
  }

  return data;
}