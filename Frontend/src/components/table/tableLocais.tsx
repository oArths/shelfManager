import { useState } from 'react';
import Pagination from '../pagination';
import type { Local } from './interface';
import { useNavigate, useLocation } from 'react-router-dom';


export default function TableLocais({
  data,
  onEdit,
  onRemove,
}: {
  data: Local[];
  onEdit: () => void;
  onRemove: () => void;
}): React.JSX.Element {
  const [offset, setOffSet] = useState(0);
  const limit = 7;
  const navigate = useNavigate();
  const location = useLocation();
  const from = location.state?.from?.pathname || '/';

  const handlerBakcLoacais = () => {
    navigate(from + 'local', { replace: true });
  };
  return (
    <div className="flex flex-col items-start  justify-between w-full min-h-96 h-auto  bg-white border-l border-r   border-border">
      <table className="w-full h-auto  ">
        <thead className="w-full ">
          <tr className="thead-tr ">
            <td className="thead-td  " scope="col">
              Local
            </td>
            <td className="thead-td  max-lg:hidden" scope="col">
              Quantidade
            </td>
            <td className="thead-td " scope="col">
              Ações
            </td>
          </tr>
        </thead>
        <tbody>
          {data.length < 1 && (
            <tr className="w-full h-80 bg-white border border-y  border-x-0 border-border cursor-pointer">
              <td
                colSpan={3}
                className="w-full h-full text-gray500 font-light text-base text-center bg-white"
              >
                item não encontrado...
              </td>
            </tr>
          )}

          {data &&
            data.slice(offset, offset + limit).map((Item, Index) => {
              return (
                <tr onClick={() => handlerBakcLoacais()} key={Index} className="tbody-tr ">
                  <td className="tbody-td " scope="row">
                    {Item.nome}
                  </td>
                  <td className="tbody-td  max-lg:hidden">{Item.locais.length}</td>
                  <td className="tbody-td">
                    <div className="h-auto flex  gap-1 lg:gap-3 flex-row items-center justify-center">
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          onEdit();
                        }}
                        className="btn px-4 py-1 text-sm bg-green200/80 text-white"
                      >
                        Editar
                      </button>
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          onRemove();
                        }}
                        className="btn  px-4 py-1 text-sm bg-pink200/80 text-white"
                      >
                        Remover
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
        </tbody>
      </table>
      <Pagination limit={limit} offset={offset} setOffset={setOffSet} total={data.length} />
    </div>
  );
}
