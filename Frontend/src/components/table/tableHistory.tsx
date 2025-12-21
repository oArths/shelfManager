import type { Local } from './interface';

export default function TableHistory({ data }: { data: Local[] }): React.JSX.Element {
  return (
    <div className="flex flex-col items-start  justify-between w-full min-h-70 h-auto  bg-white border-l border-r   border-border">
      <table className="w-full h-auto  ">
        <thead className="w-full ">
          <tr className="thead-tr ">
            <td className="thead-td  " scope="col">
              Local
            </td>
            <td className="thead-td" scope="col">
              Entrada
            </td>
            <td className="thead-td " scope="col">
              Saida
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
            data.map((Item, Index) => {
              return (
                <tr key={Index} className="tbody-tr ">
                  <td className="tbody-td " scope="row">
                    {Item.nome}
                  </td>
                  <td className="tbody-td">{Item.locais.length}</td>
                  <td className="tbody-td">{Item.locais.length}</td>
          
                </tr>
              );
            })}
        </tbody>
      </table>
    </div>
  );
}
