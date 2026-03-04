import { useState, useEffect } from 'react';
import * as I from 'lucide-react';
import Modal from '../../components/modal';
import logo from '../../assets/logo.png';
import TableLocal from '../../components/table/tableLocal';
import TableHistory from '../../components/table/tableHistory';
import SingleDropdown from '../../components/dropdown/SingleDropdown';
import { useParams } from 'react-router-dom';
import { apiFetch } from '../../services/api';
import toast, { Toaster } from 'react-hot-toast';

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

  // Novos estados para histórico
  const [historyData, setHistoryData] = useState<any[]>([]);
  const [loadingHistory, setLoadingHistory] = useState(false);

  // Estado para modal de confirmação de mover ao adicionar
  const [moveConfirmModal, setMoveConfirmModal] = useState(false);
  const [productToMove, setProductToMove] = useState<any>(null);

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
    if (!selectedItem || !selectedShelfMove || selectedShelfMove === selectedItem.shelf_id) return;

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

  const handleHistory = async (item: any) => {
    setSelectedItem(item);
    setLoadingHistory(true);
    try {
      const data = await apiFetch(`/items/${item.product_id}/history`);
      setHistoryData(data);
      setHistoryLocalModal(true);
    } catch (error) {
      console.error('Erro ao carregar histórico:', error);
    } finally {
      setLoadingHistory(false);
    }
  };

  // Função para confirmar movimento vindo do modal de adição
  const confirmMoveFromAdd = async () => {
    if (!id || !productToMove) return;

    try {
      await apiFetch('/items/move', {
        method: 'POST',
        body: JSON.stringify({
          product_id: productToMove.id,
          to_shelf_id: Number(id),
        }),
      });

      loadItems();
      setMoveConfirmModal(false);
      setProductToMove(null);
      setShowDropdown(false);
      setSearch('');
      setSearchResults([]);
    } catch (error: any) {
      console.error(error);
    }
  };

  const handleAddSingleProduct = async (product: any) => {
    if (!id) return;

    // Verifica se o produto já está em alguma prateleira
    if (product.in_shelf) {
      // Se já está na prateleira atual, exibe mensagem de erro
      if (Number(product.in_shelf) === Number(id)) {
        toast.error('Este produto já está nesta prateleira.');
        return;
      }
      // Caso contrário, abre modal para mover de outra prateleira
      setShowDropdown(false); // Fecha o dropdown antes de abrir o modal
      setProductToMove(product);
      setMoveConfirmModal(true);
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

      loadItems();
      setShowDropdown(false);
      setSearch('');
      setSearchResults([]);
    } catch (error: any) {
      console.error(error);
    }
  };

  return (
    <section className="flex flex-col w-full items-center justify-center">
      {/* Toaster adicionado para exibir notificações */}
      <Toaster
        position="top-center"
        reverseOrder={false}
        toastOptions={{
          duration: 3000,
        }}
      />
      <div className="flex flex-col gap-5 w-[90%] h-full">
        <h1 className="text-3xl font-semibold text-black200 pt-10 pb-0">
          {localName}
        </h1>

        <fieldset className="w-full flex flex-col lg:flex-row items-end gap-5">
          <div className="relative w-full">
            <div className="flex items-center w-full relative">
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
                    <div className="flex flex-row gap-5">
                      <figure className="bg-border rounded-md w-10 h-10 p-1 flex items-center justify-center">
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
                      className="btn text-white text-sm h-8 px-5 bg-blue200"
                    >
                      Adicionar
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
          <label className="flex flex-col gap-1 lg:w-1/2 w-full">
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

      {/* Modal de confirmação para mover produto ao adicionar */}
      {moveConfirmModal && productToMove && (
        <Modal
          onClose={() => {
            setMoveConfirmModal(false);
            setProductToMove(null);
          }}
          Children={
            <div className="flex flex-col gap-5 min-w-80 items-center px-6 py-4">
              <div className="rounded-full p-3 bg-amber-100 w-fit">
                <I.HelpCircle className="text-amber-600" size={53} />
              </div>
              <h2 className="font-medium text-2xl text-black text-center w-full">
                <p>{productToMove.name}</p>
                <p className="text-amber-600 break-words text-center text-base font-normal mt-2">
                  já está em {productToMove.shelf_name}. Deseja mover para {localName}?
                </p>
              </h2>

              <div className="w-full flex flex-row gap-5 mt-2">
                <button
                  onClick={() => {
                    setMoveConfirmModal(false);
                    setProductToMove(null);
                  }}
                  className="btn px-8 py-1.5 w-1/2 text-sm lg:text-base bg-white border border-black400/70 text-black400/70"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  onClick={confirmMoveFromAdd}
                  className="btn w-1/2 bg-blue200 text-white px-8 py-1.5 text-sm lg:text-base"
                >
                  Confirmar
                </button>
              </div>
            </div>
          }
        />
      )}

      {/* Modal de confirmação para remover item */}
      {removeLocalModal && selectedItem && (
        <Modal
          onClose={() => setRemoveLocalModal(false)}
          Children={
            <div className="flex flex-col gap-5 min-w-80 items-center px-6 py-4">
              <div className="rounded-full p-3 bg-pink-100 w-fit">
                <I.Trash2 className="text-pink-600" size={53} />
              </div>
              <h2 className="font-medium text-2xl text-black text-center w-full">
                <p>Deseja realmente excluir</p>
                <p className="text-pink-600 break-words text-center">
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
                  className="btn w-1/2 bg-pink-600 text-white px-8 py-1.5 text-sm lg:text-base"
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
            <div className="flex flex-col gap-5 w-auto">
              <nav className="flex flex-row items-center justify-start gap-5">
                <figure className="bg-border rounded-md w-28 h-28 p-1 flex items-center justify-center">
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
              {loadingHistory ? (
                <div className="text-center py-4">Carregando...</div>
              ) : (
                <TableHistory data={historyData} />
              )}
            </div>
          }
        />
      )}

      {/* Modal de mover item */}
      {newLocalModal && selectedItem && (
        <Modal
          onClose={() => setNewLocalModal(false)}
          Children={
            <div className="flex flex-col gap-5 lg:w-100">
              <h2 className="font-medium text-xl text-black">Escolha um local para o produto</h2>
              <div className="w-full flex flex-col gap-1">
                <label className="text-base text-black font-normal">Novo Local</label>
                <SingleDropdown
                  filterKey="title"
                  relative={true}
                  options={allShelves
                    .filter((shelf) => shelf.id !== selectedItem.shelf_id)
                    .map((s) => s.name)}
                  selectedOption={
                    allShelves.find((s) => s.id === Number(selectedShelfMove))?.name || ''
                  }
                  onOptionSelect={handleShelfSelect}
                />
              </div>
              <div className="w-full flex flex-row gap-5">
                <button
                  onClick={() => setNewLocalModal(false)}
                  className="btn px-8 py-1.5 w-1/2 text-sm lg:text-base bg-white border border-black400/70 text-black400/70"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  onClick={confirmMove}
                  disabled={!selectedShelfMove || selectedShelfMove === selectedItem.shelf_id}
                  className={`btn w-1/2 px-8 py-1.5 text-sm lg:text-base ${
                    !selectedShelfMove || selectedShelfMove === selectedItem.shelf_id
                      ? 'bg-gray-400 text-white cursor-not-allowed'
                      : 'bg-blue200 text-white'
                  }`}
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