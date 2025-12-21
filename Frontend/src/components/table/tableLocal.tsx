import { useState } from 'react';
import Pagination from '../pagination';
import logo from '../../../public/logo.png';

import type { Local } from './interface';

export default function TableLocal({ data }: { data: Local }): React.JSX.Element {
  const [offset, setOffSet] = useState(0);
  const limit = 7;
  const imgName =
    'C:\\Users\\70089795\\Documents\\Projetos\\shelfManager\\Frontend\\public\\logo.png';

  return (
    <div className="flex flex-col items-start  justify-between w-full min-h-96 h-auto  bg-white border-l border-r   border-border">
      <table className="w-full h-auto  ">
        <thead className="w-full ">
          <tr className="thead-tr ">
            <td className="thead-td  " scope="col">
              Imagem
            </td>
            <td className="thead-td" scope="col">
              Nome
            </td>
            <td className="thead-td" scope="col">
              Codigo
            </td>
            <td className="thead-td " scope="col">
              Ações
            </td>
          </tr>
        </thead>
        <tbody>
          {data.locais.length < 1 && (
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
            data.locais.slice(offset, offset + limit).map((Item, Index) => {
              return (
                <tr key={Index} className="tbody-tr ">
                  <td className="tbody-td " scope="row">
                    <div className="w-full h-full flex items-center justify-center">
                      <figure className=" bg-border rounded-md w-10 h-10 p-1 flex items-center justify-center">
                        <img src={logo} className="aspect-square" />
                      </figure>
                    </div>
                    {/* {Item.image} */}
                  </td>
                  <td className="tbody-td">{Item.name}</td>
                  <td className="tbody-td">{Item.code}</td>
                  <td className="tbody-td">
                    <div className="h-auto flex  gap-1 lg:gap-3 flex-row items-center justify-center px-5">
                      <button className="btn px-4 py-1 text-sm bg-blue200/80 text-white">
                        Mover
                      </button>
                      <button className="btn  px-4 py-1 text-sm bg-pink200/80 text-white">
                        Remover
                      </button>
                      <button className="btn  px-4 py-1 text-sm bg-yellow200/80 text-white">
                        Historico
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
        </tbody>
      </table>
      <Pagination limit={limit} offset={offset} setOffset={setOffSet} total={data.locais.length} />
    </div>
  );
}
