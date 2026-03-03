import { useState } from 'react';
import Pagination from '../pagination';

import type { Item } from './interface';

export default function TableLocal({
  data,
  onMove,
  onRemove,
  onHistory,
}: {
  data: Item[];
  onMove: (item: any) => void;
  onRemove: (item: any) => void;
  onHistory: (item: any) => void;
}): React.JSX.Element {
  const [offset, setOffSet] = useState(0);
  const limit = 7;

  return (
    <div className="flex flex-col items-start  justify-between w-full min-h-96 h-auto  bg-white border-l border-r   border-border">
      <table className="w-full h-auto  ">
        <thead className="w-full ">
          <tr className="thead-tr ">
            <td className="thead-td max-lg:hidden " scope="col">
              Imagem
            </td>
            <td className="thead-td" scope="col">
              Nome
            </td>
            <td className="thead-td max-xl:hidden" scope="col">
              SKU
            </td>
            <td className="thead-td max-xl:hidden" scope="col">
              Quantidade
            </td>
            <td className="thead-td " scope="col">
              Ações
            </td>
          </tr>
        </thead>
        <tbody>
          {!data || data.length === 0 ? (
            <tr className="w-full h-80 bg-white border border-y  border-x-0 border-border cursor-pointer">
              <td
                colSpan={3}
                className="w-full h-full text-gray500 font-light text-base text-center bg-white"
              >
                item não encontrado...
              </td>
            </tr>
          ) : (
            data &&
            data.slice(offset, offset + limit).map((item, index) => {
              const productData = item.product_data || {};
              const mainImage = productData.image || productData.main_image || '';
              return (
                <tr key={item.id || index} className="tbody-tr ">
                  <td className="tbody-td max-xl:hidden " scope="row">
                    <div className="w-full h-full flex items-center justify-center">
                      <figure className=" bg-border rounded-md w-10 h-10 p-1 flex items-center justify-center">
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
                  <td className="tbody-td">{productData.name || 'Nome não disponível'}</td>
                  <td className="tbody-td max-lg:hidden">
                    {productData.sku || 'SKU não disponível'}
                  </td>
                  <td className="tbody-td max-xl:hidden">
                    {item.quantity || 1}
                    {productData.sku || 'SKU não disponível'}
                  </td>
                  <td className="tbody-td">
                    <div className="h-auto flex  gap-1 lg:gap-3 flex-row items-center justify-center px-5">
                      <button
                        onClick={() => onMove(item)}
                        className="btn px-4 py-1 text-sm bg-blue200/80 text-white"
                      >
                        Mover
                      </button>
                      <button
                        onClick={() => onRemove(item)}
                        className="btn  px-4 py-1 text-sm bg-pink200/80 text-white"
                      >
                        Remover
                      </button>
                      <button
                        onClick={() => onHistory(item)}
                        className="btn  px-4 py-1 text-sm bg-yellow200/80 text-white"
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
      {data && data.length > 0 && (
        <Pagination limit={limit} offset={offset} setOffset={setOffSet} total={data.length} />
      )}
    </div>
  );
}
