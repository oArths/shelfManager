import React from 'react';

interface HistoryItem {
  id: number;
  shelf_name: string;
  entrada: string;
  saida: string | null;
}

export default function TableHistory({ data }: { data: HistoryItem[] }): React.JSX.Element {
  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '-';
    // Adiciona 'Z' para forçar interpretação como UTC
    const date = new Date(dateStr + 'Z');
    return date.toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    }).replace(',', ' às');
  };

  // Ordena do mais recente para o mais antigo (assumindo que já vem ordenado)
  const sortedData = [...data].sort((a, b) => new Date(b.entrada + 'Z').getTime() - new Date(a.entrada + 'Z').getTime());

  // Limita aos 5 registros mais recentes
  const limitedData = sortedData.slice(0, 5);

  return (
    <div className="flex flex-col items-start justify-between w-full min-h-70 h-auto bg-white border-l border-r border-border">
      <table className="w-full h-auto">
        <thead className="w-full">
          <tr className="thead-tr">
            <td className="thead-td" scope="col">Local</td>
            <td className="thead-td" scope="col">Entrada</td>
            <td className="thead-td" scope="col">Saída</td>
          </tr>
        </thead>
        <tbody>
          {limitedData.length === 0 ? (
            <tr className="w-full h-80 bg-white border border-y border-x-0 border-border cursor-pointer">
              <td colSpan={3} className="w-full h-full text-gray500 font-light text-base text-center bg-white">
                Nenhum histórico encontrado.
              </td>
            </tr>
          ) : (
            limitedData.map((item, index) => (
              <tr key={item.id || index} className="tbody-tr">
                <td className="tbody-td">{item.shelf_name}</td>
                <td className="tbody-td">{formatDate(item.entrada)}</td>
                <td className="tbody-td">{formatDate(item.saida)}</td>
              </tr>
            ))
          )}
        </tbody>
      </table>
      {data.length > 5 && (
        <div className="text-xs text-gray-500 p-2 text-center w-full">
          Mostrando os 5 registros mais recentes
        </div>
      )}
    </div>
  );
}