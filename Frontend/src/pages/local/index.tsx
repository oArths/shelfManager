import { useState, useEffect } from 'react';
import * as I from 'lucide-react';
import Modal from '../../components/modal';
import logo from '../../assets/logo.png';
import TableLocal from '../../components/table/tableLocal';
import TableHistory from '../../components/table/tableHistory';
import SingleDropdown from '../../components/dropdown/SingleDropdown';
import { useParams } from 'react-router-dom';
import { apiFetch } from '../../services/api';

export default function Local() {
  const chartOptions = ['Nome do Produto', 'Sku'];
  const { id } = useParams();
  const [seacrh, setSearch] = useState('');
  const [items, setItems] = useState<any[]>([]);
  const [localName, setLocalName] = useState('');
  const [newLocalModal, setNewLocalModal] = useState(false);
  const [selectedItem, setSelectedItem] = useState<any>(null);
  const [removeLocalModal, setRemoveLocalModal] = useState(false);
  const [historyLocalModal, setHistoryLocalModal] = useState(false);
  const [optionChart, setOptionChart] = useState(chartOptions[0]);

  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [loadingSearch, setLoadingSearch] = useState(false);
  const [allShelves, setAllShelves] = useState<any[]>([]);
  const [selectedShelfMove, setSelectedShelfMove] = useState<string>('');

  const loadShelves = async () => {
    try {
      const data = await apiFetch('/shelves');
      setAllShelves(data);
    } catch (error) {
      console.error(error);
    }
  };

  // Função para carregar detalhes da prateleira (nome)
  const loadShelfDetails = async () => {
    if (!id) return;
    try {
      const data = await apiFetch(`/shelves/${id}`);
      setLocalName(data.name);
    } catch (error) {
      console.error('Erro ao carregar nome da prateleira:', error);
    }
  };

  useEffect(() => {
    if (seacrh.length < 2) {
      setSearchResults([]);
      return;
    }

    const timeout = setTimeout(() => {
      searchProducts();
    }, 40);

    return () => clearTimeout(timeout);
  }, [seacrh]);

  const searchProducts = async () => {
    try {
      setLoadingSearch(true);

      const searchType = optionChart === 'Sku' ? 'sku' : 'name';

      const data = await apiFetch(
        `/products/search?q=${encodeURIComponent(seacrh)}&type=${searchType}&limit=20`
      );

      setSearchResults(data.data || []);
      setShowDropdown(true);
    } catch (error: any) {
      console.error(error);
    } finally {
      setLoadingSearch(false);
    }
  };

  useEffect(() => {
    if (!id) return;

    loadShelfDetails(); // Carrega o nome da prateleira
    loadItems();        // Carrega os itens
  }, [id]);

  const loadItems = async () => {
    try {
      const data = await apiFetch(`/shelves/${id}/items`);
      setItems(data);
    } catch (error: any) {
      console.error(error);
    }
  };

  const HandlerChartOptionSelect = (option: string): void => {
    setOptionChart(option);
  };

  const handleShelfSelect = (option: string) => {
    const shelf = allShelves.find((s) => s.name === option);
    if (shelf) setSelectedShelfMove(shelf.id);
  };

  const confirmMove = async () => {
    if (!selectedItem || !selectedShelfMove) return;

    try {
      await apiFetch('/items/move', {
        method: 'POST',
        body: JSON.stringify({
          product_id: selectedItem.product_id,
          to_shelf_id: Number(selectedShelfMove),
        }),
      });

      setNewLocalModal(false);
      loadItems();
    } catch (error) {
      console.error(error);
    }
  };

  const handleMoveNewLocal = (item: any) => {
    setSelectedItem(item);
    loadShelves();
    setNewLocalModal(true);
  };

  const confirmRemove = async () => {
    if (!id || !selectedItem) return;

    try {
      await apiFetch(`/shelves/${id}/items/${selectedItem.product_id}`, {
        method: 'DELETE',
      });

      setRemoveLocalModal(false);
      loadItems();
    } catch (error) {
      console.error(error);
    }
  };

  const handleRemove = (item: any) => {
    setSelectedItem(item);
    setRemoveLocalModal(true);
  };

  const handleHistory = (item: any) => {
    setSelectedItem(item);
    setHistoryLocalModal(true);
  };

  const handleAddSingleProduct = async (product: any) => {
    if (!id) return;

    // Verifica se já está na prateleira
    if (product.in_shelf) {
      alert(`Este produto já está em ${product.shelf_name}`);
      return;
    }

    try {
      await apiFetch(`/shelves/${id}/items`, {
        method: 'POST',
        body: JSON.stringify({
          product_id: product.id,
          quantity: 1,
        }),
      });

      // Recarrega a lista
      loadItems();

      // Fecha o dropdown
      setShowDropdown(false);
      setSearch('');
      setSearch('');
      setSearchResults([]);
    } catch (error: any) {
      console.error(error);
    }
  };

  return (
    <section className="flex flex-col w-full  items-center justify-center  ">
      <div className="flex flex-col gap-5 w-[90%] h-full">
        <h1 className="text-3xl font-semibold text-black200 pt-10 pb-0">
          {localName}
        </h1>

        <fieldset className=" w-full flex flex-col lg:flex-row items-end gap-5">
          <div className="relative w-full">
            <div className="  flex items-center w-full   relative">
              <I.Search size={24} className="stroke-black200/70 absolute top-2 left-3" />
              <input
                placeholder="Pesquisar..."
                className="input w-full px-12 bg-white"
                value={seacrh}
                onChange={(e) => setSearch(e.target.value)}
                onFocus={() => setShowDropdown(true)}
              />
            </div>

            {showDropdown && searchResults.length > 0 && (
              <div className="absolute z-50 mt-2 w-full bg-white border rounded-md shadow-md max-h-60 overflow-y-auto">
                {searchResults.map((product) => (
                  <div
                    key={product.id}
                    className="px-4 py-2 hover:bg-gray-100 cursor-pointer flex justify-between items-center gap-5"
                  >
                    <div className=" flex flex-row gap-5">
                      <figure className=" bg-border rounded-md w-10 h-10 p-1 flex items-center justify-center">
                        <img
                          src={product.image || product.main_image || ''}
                          alt={product.name || 'Produto'}
                          className="aspect-square object-cover"
                        />
                      </figure>
                      <span className="text-sm h-auto flex justify-center items-center">
                        {optionChart === 'Sku' ? product.sku : product.name}
                      </span>
                    </div>

                    {product.in_shelf && (
                      <span className="text-xs flex justify-center items-center text-red-500">
                        Já está em {product.shelf_name}
                      </span>
                    )}
                    <button
                      onClick={() => handleAddSingleProduct(product)}
                      disabled={product.in_shelf}
                      className={`btn text-white text-sm h-8 px-5 ${
                        product.in_shelf ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue200'
                      }`}
                    >
                      Adicionar
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
          <label className=" flex flex-col gap-1 lg:w-1/2 w-full ">
            <span>Tipo de Pesquisa</span>
            <SingleDropdown
              filterKey="title"
              options={chartOptions}
              selectedOption={optionChart}
              onOptionSelect={HandlerChartOptionSelect}
            />
          </label>
        </fieldset>
        <TableLocal
          data={items}
          onMove={handleMoveNewLocal}
          onRemove={handleRemove}
          onHistory={handleHistory}
        />
      </div>

      {/* Modal de confirmação para remover item */}
      {removeLocalModal && selectedItem && (
        <Modal
          onClose={() => setRemoveLocalModal(false)}
          Children={
            <div className="flex flex-col gap-5 min-w-80 items-center px-6 py-4">
              <div className="rounded-full p-3 bg-pink200/10 w-fit">
                <I.Trash2 className="stroke-pink200" size={53} />
              </div>
              <h2 className="font-medium text-2xl text-black text-center w-full">
                <p>Deseja realmente excluir</p>
                <p className="text-pink200 break-words text-center">
                  {selectedItem.product_data?.name || 'este item'}
                </p>
              </h2>

              <div className="w-full flex flex-row gap-5 mt-2">
                <button
                  onClick={() => setRemoveLocalModal(false)}
                  className="btn px-8 py-1.5 w-1/2 text-sm lg:text-base bg-white border border-black400/70 text-black400/70"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  onClick={confirmRemove}
                  className="btn w-1/2 bg-pink200 text-white px-8 py-1.5 text-sm lg:text-base"
                >
                  Excluir
                </button>
              </div>
            </div>
          }
        />
      )}

      {/* Modal de histórico */}
      {historyLocalModal && selectedItem && (
        <Modal
          onClose={() => setHistoryLocalModal(false)}
          isFull={true}
          Children={
            <div className="flex flex-col gap-5  w-auto ">
              <nav className=" flex flex-row items-center justify-start gap-5">
                <figure className=" bg-border rounded-md w-28 h-28 p-1 flex items-center justify-center">
                  <img src={logo} className="aspect-square" />
                </figure>
                <div className="flex flex-col items-start justify-start gap-5">
                  <h2 className="text-xl font-medium text-black100">
                    {selectedItem.product_data?.name}
                  </h2>
                  <p className="text-xl font-normal text-black100">
                    {selectedItem.product_data?.sku}
                  </p>
                </div>
              </nav>
              <TableHistory data={[selectedItem]} />
            </div>
          }
        />
      )}

      {/* Modal de mover item */}
      {newLocalModal && (
        <Modal
          onClose={() => setNewLocalModal(false)}
          Children={
            <div className="flex flex-col gap-5 lg:w-100 ">
              <h2 className="font-medium text-xl text-black">Escolha um local para o produto</h2>
              <div className="w-full flex flex-col gap-1  ">
                <label className="text-base text-black font-normal ">Novo Local</label>
                <SingleDropdown
                  filterKey="title"
                  relative={true}
                  options={allShelves.map((s) => s.name)}
                  selectedOption={
                    allShelves.find((s) => s.id === Number(selectedShelfMove))?.name || ''
                  }
                  onOptionSelect={handleShelfSelect}
                />
              </div>
              <div className="w-full flex flex-row gap-5">
                <button
                  onClick={() => setNewLocalModal(false)}
                  className=" btn px-8 py-1.5  w-1/2 text-sm lg:text-base bg-white border border-black400/70 text-black400/70"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  onClick={confirmMove}
                  className="btn w-1/2 bg-blue200 text-white  px-8 py-1.5 text-sm lg:text-base"
                >
                  Confirmar
                </button>
              </div>
            </div>
          }
        />
      )}
    </section>
  );
}