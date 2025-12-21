import { useState } from 'react';
import * as I from 'lucide-react';
import Modal from '../../components/modal';
import logo from '../../../public/logo.png';
import locais from '../../json/locais.json';
import TableLocal from '../../components/table/tableLocal';
import TableHistory from "../../components/table/tableHistory";
import SingleDropdown from '../../components/dropdown/SingleDropdown';

export default function Local() {
  const chartOptions = [
    'Nome da Prateleira',
    'Sku',
    'Nome do Produto',
    'sf',
    'df',
    'df',
    'df',
    'df',
  ];
  const [seacrh, setSearch] = useState('');
  const [newLocalModal, setNewLocalModal] = useState(true);
  const [moveLocalModal, setMoveLocalModal] = useState(true);
  const [removeLocalModal, setRemoveLocalModal] = useState(true);
  const [historyLocalModal, setHistoryLocalModal] = useState(true);
  const [optionChart, setOptionChart] = useState(chartOptions[0]);

  const HandlerChartOptionSelect = (option: string): void => {
    setOptionChart(option);
  };
  const itemName = 'Armário do Fernando';
  const [deleteModal, setDeleteModal] = useState(false);
  return (
    <section className="flex flex-col w-full  items-center justify-center  ">
      <div className="flex flex-col gap-5 w-[90%] h-full">
        <h1 className="text-4xl font-medium text-black200 pt-5 ">Local</h1>
        <fieldset className=" w-full flex flex-col lg:flex-row items-end gap-5">
          <div className="  flex items-center w-full lg:w-3/4  relative">
            <I.Search size={24} className="stroke-black200/70 absolute top-2 left-3" />
            <input
              placeholder="Pesquisar..."
              className="input w-full px-12 bg-white"
              onChange={(event) => setSearch(event.target.value)}
            />
          </div>
          <button className="btn bg-blue200 text-white h-10 w-full lg:w-1/4">
            Adicionar Produto
          </button>
        </fieldset>
        <TableLocal data={locais[0]} />
      </div>
      {removeLocalModal && (
        <Modal
          onClose={() => setRemoveLocalModal(false)}
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
      {historyLocalModal && (
        <Modal
          onClose={() => setHistoryLocalModal(false)}
          Children={
            <div className="flex flex-col gap-5 lg:w-200 w-auto ">
              <nav className=" flex flex-row items-center justify-start gap-5">
                <figure className=" bg-border rounded-md w-28 h-28 p-1 flex items-center justify-center">
                  <img src={logo} className="aspect-square" />
                </figure>
                <div className="flex flex-col items-start justify-start gap-5">
                  <h2 className="text-xl font-medium text-black100">SANDÁLIA DKNY COM PEDRAS</h2>
                  <p className="text-xl font-normal text-black100">2870061026008</p>
                </div>
              </nav>
              <TableHistory data={locais} />
            </div>
          }
        />
      )}
      {newLocalModal && (
        <Modal
          onClose={() => setNewLocalModal(false)}
          Children={
            <div className="flex flex-col gap-5 min-w-100 ">
              <h2 className="font-medium text-2xl text-black">Adicionar Local</h2>
              <div className="w-full flex flex-col gap-1 relative ">
                <label className="text-lg text-black font-medium ">Novo Local</label>
                <SingleDropdown
                  filterKey="title"
                  options={chartOptions}
                  selectedOption={optionChart}
                  onOptionSelect={HandlerChartOptionSelect}
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
      {moveLocalModal && (
        <Modal
          onClose={() => setMoveLocalModal(false)}
          Children={
            <div className="flex flex-col gap-5 min-w-100  items-center">
              <div className=" rounded-full p-3 bg-blue200/10 w-fit">
                <I.Archive className="stroke-blue200" size={53} />
              </div>
              <h2 className="font-medium text-2xl text-black  text-justify w-full flex flex-col items-center">
                <p>Deseja realmente excluir</p>
                <p className="text-blue200 truncate">{itemName}</p>
              </h2>
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
