import { useState, useEffect } from 'react';
import * as I from 'lucide-react';
import Modal from '../../components/modal';
import logo from '../../assets/logo.png';
// import locais from '../../json/locais.json';
import TableLocal from '../../components/table/tableLocal';
import TableHistory from '../../components/table/tableHistory';
import SingleDropdown from '../../components/dropdown/SingleDropdown';
import { useParams } from 'react-router-dom';
import { apiFetch } from '../../services/api';
export default function Local() {
  const chartOptions = ['Nome do Produto', 'Sku'];
  const itemName = 'Armário do Fernando';
  const { id } = useParams();
  const [seacrh, setSearch] = useState('');
  const [items, setItems] = useState<any[]>([]);
  const [localName, setLocalName] = useState('');
  const [deleteModal, setDeleteModal] = useState(false);
  const [newLocalModal, setNewLocalModal] = useState(false);
  const [selectedItem, setSelectedItem] = useState<any>(null);
  const [removeLocalModal, setRemoveLocalModal] = useState(false);
  const [historyLocalModal, setHistoryLocalModal] = useState(false);
  const [optionChart, setOptionChart] = useState(chartOptions[0]);

  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [selectedProducts, setSelectedProducts] = useState<any[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [loadingSearch, setLoadingSearch] = useState(false);

  useEffect(() => {
    if (seacrh.length < 2) {
      setSearchResults([]);
      return;
    }

    const timeout = setTimeout(() => {
      searchProducts();
    }, 400);

    return () => clearTimeout(timeout);
  }, [seacrh]);

  const searchProducts = async () => {
    try {
      setLoadingSearch(true);

      const data = await apiFetch(`/products/search?q=${encodeURIComponent(seacrh)}&limit=20`);

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

    loadItems();
  }, [id]);

  const loadItems = async () => {
    try {
      const data = await apiFetch(`/shelves/${id}/items`);
      setLocalName(data.name);
      setItems(data);
      console.log(data);
    } catch (error: any) {
      console.error(error);
    }
  };

  const HandlerChartOptionSelect = (option: string): void => {
    setOptionChart(option);
  };

  const handleMoveNewLocal = () => {
    setNewLocalModal(true);
  };

  const handleRemove = (item: any) => {
    setSelectedItem(item);
    setRemoveLocalModal(true);
  };

  const handleHistory = (item: any) => {
    setSelectedItem(item);
    setHistoryLocalModal(true);
  };
  const handleSelectProduct = (product: any) => {
    if (product.in_shelf) return;

    const alreadySelected = selectedProducts.find((p) => p.id === product.id);
    if (alreadySelected) return;

    setSelectedProducts([...selectedProducts, product]);
    setSearch('');
    setShowDropdown(false);
  };
  const handleAddProducts = async () => {
    if (!id) return;

    try {
      for (const product of selectedProducts) {
        await apiFetch(`/shelves/${id}/items`, {
          method: 'POST',
          body: JSON.stringify({
            product_id: product.id,
            product_name: product.name,
            product_sku: product.sku,
            quantity: 1,
          }),
        });
      }

      setSelectedProducts([]);
      loadItems();
    } catch (error: any) {
      console.error(error);
    }
  };
  return (
    <section className="flex flex-col w-full  items-center justify-center  ">
      <div className="flex flex-col gap-5 w-[90%] h-full">
        <h1 className="text-4xl font-medium text-black200 pt-5 ">{localName}</h1>
        <fieldset className=" w-full flex flex-col lg:flex-row items-end gap-5">
          {/* <div className="  flex items-center w-full lg:w-3/4  relative">
            <I.Search size={24} className="stroke-black200/70 absolute top-2 left-3" />
            <input
              placeholder="Pesquisar..."
              className="input w-full px-12 bg-white"
              onChange={(event) => setSearch(event.target.value)}
            />
          </div> */}
          <div className="relative w-full">
            <input
              placeholder="Pesquisar..."
              className="input w-full px-12 bg-white"
              value={seacrh}
              onChange={(e) => setSearch(e.target.value)}
              onFocus={() => setShowDropdown(true)}
            />

            {showDropdown && searchResults.length > 0 && (
              <div className="absolute z-50 mt-2 w-full bg-white border rounded-md shadow-md max-h-60 overflow-y-auto">
                {searchResults.map((product) => (
                  <div
                    key={product.id}
                    onClick={() => handleSelectProduct(product)}
                    className="px-4 py-2 hover:bg-gray-100 cursor-pointer flex justify-between"
                  >
                    <span>{product.name}</span>

                    {product.in_shelf && (
                      <span className="text-xs text-red-500">Já está em {product.shelf_name}</span>
                    )}
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
          <button
            onClick={handleAddProducts}
            className="btn bg-blue200 text-white h-10 w-full lg:w-1/4"
          >
            Adicionar Produto
          </button>
        </fieldset>
        <div className="flex flex-wrap gap-2">
          {selectedProducts.map((product) => (
            <div
              key={product.id}
              className="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm flex items-center gap-2"
            >
              {product.name}
              <button
                onClick={() =>
                  setSelectedProducts(selectedProducts.filter((p) => p.id !== product.id))
                }
              >
                ✕
              </button>
            </div>
          ))}
        </div>
        <TableLocal
          data={items}
          onMove={handleMoveNewLocal}
          onRemove={handleRemove}
          onHistory={handleHistory}
        />
      </div>
      {removeLocalModal && selectedItem && (
        <Modal
          onClose={() => setRemoveLocalModal(false)}
          Children={
            <div className="flex flex-col gap-5 lg:w-100 items-center">
              <div className=" rounded-full p-3 bg-pink200/10 w-fit">
                <I.Trash2 className="stroke-pink200" size={53} />
              </div>
              <h2 className="font-medium text-2xl text-black  text-justify w-full flex flex-col items-center">
                <p>Deseja realmente excluir</p>
                <p className="text-pink200 truncate">{selectedItem.name}</p>
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
                  <h2 className="text-xl font-medium text-black100">SANDÁLIA DKNY COM PEDRAS</h2>
                  <p className="text-xl font-normal text-black100">2870061026008</p>
                </div>
              </nav>
              <TableHistory data={items} />
            </div>
          }
        />
      )}
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
