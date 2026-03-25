export const apiFetch = async (url: string, options: any = {}) => {
  const token = localStorage.getItem('token');

  const response = await fetch(`https://lebrecho.com.br/api/shelf${url}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Authorization: token ? `Bearer ${token}` : '',
      ...options.headers,
    },
  });

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    throw new Error(data?.detail || data?.message || 'Erro inesperado na requisição');
  }

  return data;

};
