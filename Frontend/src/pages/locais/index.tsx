import { useState } from 'react';
import * as I from 'lucide-react';
import Modal from '../../components/modal';
import locais from '../../json/locais.json';
import TableLocais from '../../components/table/tableLocais';
import SingleDropdown from '../../components/dropdown/SingleDropdown';

export default function Locais() {
  const chartOptions = ['Nome da Prateleira', 'Sku', 'Nome do Produto'];
  const [seacrh, setSearch] = useState('');
  const [changeName, setChangeName] = useState('');
  const [optionChart, setOptionChart] = useState(chartOptions[0]);
  const [editModal, setEditModal] = useState(false);
  const [deleteModal, setDeleteModal] = useState(false);
  const [newLocalModal, setNewLocalModal] = useState(true);
  const [newLocalName, setNewLocalName] = useState('');

  const itemName = 'Armário do Fernando';
  const HandlerChartOptionSelect = (option: string): void => {
    setOptionChart(option);
  };
  return (
    <section className="flex flex-col  w-full items-center justify-center  pb-10">
      <div className="flex flex-col gap-5 w-[90%] h-full">
        <h1 className="text-4xl font-medium text-black200 pt-5 ">Locais</h1>
        <fieldset className=" w-full flex flex-col lg:flex-row items-end gap-5">
          <div className="  flex items-center w-full  relative">
            <I.Search size={24} className="stroke-black200/70 absolute top-2 left-3" />
            <input
              placeholder="Pesquisar..."
              className="input w-full px-12 bg-white"
              onChange={(event) => setSearch(event.target.value)}
            />
          </div>
          <div className="w-full flex flex-col md:flex-row justify-between items-end gap-5">
            <label className=" flex flex-col gap-1 lg:w-1/2 w-full ">
              <span>Tipo de Pesquisa</span>
              <SingleDropdown
                filterKey="title"
                options={chartOptions}
                selectedOption={optionChart}
                onOptionSelect={HandlerChartOptionSelect}
              />
            </label>
            <button
              onClick={() => setNewLocalModal(true)}
              className="btn bg-blue200 text-white h-10 lg:w-1/2 w-full"
            >
              Adicionar Local
            </button>
          </div>
        </fieldset>
        <TableLocais data={locais} />
      </div>
      {editModal && (
        <Modal
          onClose={() => setEditModal(false)}
          Children={
            <div className="flex flex-col gap-5 min-w-100">
              <h2 className="font-medium text-2xl text-black">Alterar nome do Local</h2>
              <div className="w-full flex flex-col gap-1 ">
                <label className="text-lg text-black font-medium ">Nome</label>
                <input
                  className="input px-4 "
                  type="text"
                  value={changeName}
                  onChange={(e) => setChangeName(e.target.value)}
                  placeholder="Altere o nome"
                />
              </div>
              <div className="w-full flex flex-row gap-5">
                <button className=" btn px-8 py-1.5  w-1/2 text-sm lg:text-base bg-white border border-black400/70 text-black400/70">
                  Cancelar
                </button>
                <button
                  type="button"
                  className="btn w-1/2 bg-blue200 text-white  px-8 py-1.5 text-sm lg:text-base"
                >
                  Confirmar
                </button>
              </div>
            </div>
          }
        />
      )}
      {newLocalModal && (
        <Modal
          onClose={() => setNewLocalModal(false)}
          Children={
            <div className="flex flex-col gap-5 min-w-100">
              <h2 className="font-medium text-2xl text-black">Adicionar Local</h2>
              <div className="w-full flex flex-col gap-1 ">
                <label className="text-lg text-black font-medium ">Nome</label>
                <input
                  className="input px-4 "
                  type="text"
                  value={newLocalName}
                  onChange={(e) => setNewLocalName(e.target.value)}
                  placeholder="Defina um nome"
                />
              </div>
              <div className="w-full flex flex-row gap-5">
                <button className=" btn px-8 py-1.5  w-1/2 text-sm lg:text-base bg-white border border-black400/70 text-black400/70">
                  Cancelar
                </button>
                <button
                  type="button"
                  className="btn w-1/2 bg-blue200 text-white  px-8 py-1.5 text-sm lg:text-base"
                >
                  Confirmar
                </button>
              </div>
            </div>
          }
        />
      )}
      {deleteModal && (
        <Modal
          onClose={() => setDeleteModal(false)}
          Children={
            <div className="flex flex-col gap-5 min-w-100 items-center">
              <div className=" rounded-full p-3 bg-pink200/10 w-fit">
                <I.Trash2 className="stroke-pink200" size={53} />
              </div>
              <h2 className="font-medium text-2xl text-black  text-justify w-full flex flex-col items-center">
                <p>Deseja realmente excluir</p>
                <p className="text-pink200 truncate">{itemName}</p>
              </h2>

              <div className="w-full flex flex-row gap-5">
                <button className=" btn px-8 py-1.5  w-1/2 text-sm lg:text-base bg-white border border-black400/70 text-black400/70">
                  Cancelar
                </button>
                <button
                  type="button"
                  className="btn w-1/2 bg-pink200 text-white  px-8 py-1.5 text-sm lg:text-base"
                >
                  Excluir
                </button>
              </div>
            </div>
          }
        />
      )}
    </section>
  );
}
