import { useState } from 'react';
import Pagination from '../pagination';
import type { Item } from './interface';

export default function TableLocal({
  data,
  onMove,
  onRemove,
  onHistory,
  isLoading = false,
}: {
  data: Item[];
  onMove: (item: any) => void;
  onRemove: (item: any) => void;
  onHistory: (item: any) => void;
  isLoading?: boolean;
}): React.JSX.Element {
  const [offset, setOffSet] = useState(0);
  const limit = 7;

  return (
    <div className="flex flex-col items-start justify-between w-full bg-white border-l border-r border-border">
      {/* Área da tabela com altura fixa e rolagem vertical */}
      <div className="w-full h-[376.5px] overflow-y-auto">
        <div className="overflow-x-auto">
          <table className="w-full table-fixed min-w-225">
            <thead className="w-full">
              <tr className="thead-tr">
                <td className="thead-td max-xl:hidden " scope="col">
                  Imagem
                </td>
                <td className="thead-td " scope="col">
                  Nome
                </td>
                <td className="thead-td max-lg:hidden " scope="col">
                  SKU
                </td>
                <td className="thead-td max-xl:hidden " scope="col">
                  Quantidade
                </td>
                <td className="thead-td w" scope="col">
                  Ações
                </td>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr className="w-full h-80 bg-white border border-y border-x-0 border-border">
                  <td
                    colSpan={5}
                    className="w-full h-full text-gray500 font-light text-base text-center bg-white"
                  >
                    Carregando...
                  </td>
                </tr>
              ) : !data || data.length === 0 ? (
                <tr className="w-full h-80 bg-white border border-y border-x-0 border-border">
                  <td
                    colSpan={5}
                    className="w-full h-full text-gray500 font-light text-base text-center bg-white"
                  >
                    item não encontrado...
                  </td>
                </tr>
              ) : (
                data.slice(offset, offset + limit).map((item, index) => {
                  const productData = item.product_data || {};
                  const mainImage = productData.image || productData.main_image || '';
                  return (
                    <tr key={item.id || index} className="tbody-tr">
                      <td className="tbody-td max-xl:hidden truncate" title={productData.name}>
                        <div className="w-full h-full flex items-center justify-center">
                          <figure className="bg-border rounded-md w-10 h-10 p-1 flex items-center justify-center">
                            {mainImage ? (
                              <img
                                src={mainImage}
                                alt={productData.name || 'Produto'}
                                className="aspect-square object-cover"
                              />
                            ) : (
                              <div className="w-full h-full bg-gray-200 rounded-md flex items-center justify-center">
                                <span className="text-xs text-gray-400">Sem img</span>
                              </div>
                            )}
                          </figure>
                        </div>
                      </td>
                      <td className="tbody-td truncate px-5" title={productData.name}>
                        {productData.name || 'Nome não disponível'}
                      </td>
                      <td className="tbody-td max-lg:hidden truncate" title={productData.sku}>
                        {productData.sku || 'SKU não disponível'}
                      </td>
                      <td className="tbody-td max-xl:hidden truncate">{item.quantity || 1}</td>
                      <td className="tbody-td">
                        <div className="h-auto flex gap-1 lg:gap-3 flex-row items-center justify-center px-5">
                          <button
                            onClick={() => onMove(item)}
                            className="btn px-4 py-1 text-sm bg-blue200/80 text-white whitespace-nowrap"
                          >
                            Mover
                          </button>
                          <button
                            onClick={() => onRemove(item)}
                            className="btn px-4 py-1 text-sm bg-pink200/80 text-white whitespace-nowrap"
                          >
                            Remover
                          </button>
                          <button
                            onClick={() => onHistory(item)}
                            className="btn px-4 py-1 text-sm bg-yellow200/80 text-white whitespace-nowrap"
                          >
                            Historico
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Paginação */}
      {data && data.length > 0 && (
        <Pagination limit={limit} offset={offset} setOffset={setOffSet} total={data.length} />
      )}
    </div>
  );
}
