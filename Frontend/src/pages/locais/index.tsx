import { useState, useEffect } from 'react';
import * as I from 'lucide-react';
import Modal from '../../components/modal';
// import locais from '../../json/locais.json';
import TableLocais from '../../components/table/tableLocais';
import SingleDropdown from '../../components/dropdown/SingleDropdown';
import { apiFetch } from '../../services/api';
import toast, { Toaster } from 'react-hot-toast';

export default function Locais() {
  const chartOptions = ['Nome da Prateleira', 'Sku', 'Nome do Produto'];
  const [search, setSearch] = useState('');
  const [optionChart, setOptionChart] = useState(chartOptions[0]);
  const [editModal, setEditModal] = useState(false);
  const [deleteModal, setDeleteModal] = useState(false);
  const [newLocalModal, setNewLocalModal] = useState(false);
  const [changeName, setChangeName] = useState<string>('');
  const [newLocalName, setNewLocalName] = useState<string>('');

  const [locais, setLocais] = useState<any[]>([]);
  const [selectedLocal, setSelectedLocal] = useState<any>(null);

  // Estados para armazenar itens de cada prateleira (necessário para busca por SKU e Nome do Produto)
  const [itemsByShelf, setItemsByShelf] = useState<Record<number, any[]>>({});
  const [loadingItems, setLoadingItems] = useState(false);

  // Função para carregar itens de todas as prateleiras
  const loadAllItems = async (shelves: any[]) => {
    setLoadingItems(true);
    const map: Record<number, any[]> = {};
    // Busca itens de cada prateleira em paralelo
    await Promise.all(
      shelves.map(async (shelf) => {
        try {
          const items = await apiFetch(`/shelves/${shelf.id}/items`);
          map[shelf.id] = items;
        } catch (error) {
          console.error(`Erro ao carregar itens da prateleira ${shelf.id}:`, error);
          map[shelf.id] = []; // em caso de erro, considera vazio
        }
      })
    );
    setItemsByShelf(map);
    setLoadingItems(false);
  };

  // Filtro dinâmico baseado na opção escolhida
  const filteredLocais = locais.filter((local) => {
    if (!search.trim()) return true;

    const value = search.toLowerCase();

    switch (optionChart) {
      case 'Nome da Prateleira':
        // Filtra pelo nome da prateleira
        return local.name?.toLowerCase().includes(value);

      case 'Sku': {
        // Filtra pelas prateleiras que possuem itens com o SKU pesquisado
        const items = itemsByShelf[local.id] || [];
        return items.some((item) =>
          item.product_data?.sku?.toLowerCase().includes(value)
        );
      }

      case 'Nome do Produto': {
        // Filtra pelas prateleiras que possuem itens com o nome pesquisado
        const items = itemsByShelf[local.id] || [];
        return items.some((item) =>
          item.product_data?.name?.toLowerCase().includes(value)
        );
      }

      default:
        return true;
    }
  });

  const handleDeleteLocal = async () => {
    try {
      await apiFetch(`/shelves/${selectedLocal.id}`, {
        method: 'DELETE',
      });

      setDeleteModal(false);
      await loadLocais(); // Recarrega a lista após excluir
    } catch (error: any) {
      toast.error(error.message);
    }
  };

  const handleUpdateLocal = async () => {
    try {
      await apiFetch(`/shelves/${selectedLocal.id}`, {
        method: 'PUT',
        body: JSON.stringify({ name: changeName }),
      });

      setEditModal(false);
      await loadLocais(); // Recarrega a lista após atualizar
    } catch (error: any) {
      toast.error(error.message);
    }
  };

  const handleEditModal = (local: any) => {
    setSelectedLocal(local);
    setChangeName(local.name ?? '');
    setEditModal(true);
  };

  useEffect(() => {
    console.log('recarregou'); // Comentário original mantido
    loadLocais();
  }, []);

  const loadLocais = async () => {
    try {
      const data = await apiFetch('/shelves');
      console.log('RETORNO API:', data); // Comentário original mantido
      setLocais(data);
      // Após carregar as prateleiras, carrega os itens de cada uma para suportar buscas por SKU e nome
      await loadAllItems(data);
    } catch (error: any) {
      toast.error(error.message);
    }
  };

  const itemName = selectedLocal?.nome || '';

  const handleCreateLocal = async () => {
    if (!newLocalName.trim()) {
      toast.error('Informe um nome para o local.');
      return;
    }
    try {
      await apiFetch('/shelves', {
        method: 'POST',
        body: JSON.stringify({
          name: newLocalName,
          description: null,
        }),
      });

      toast.success('Local criado com sucesso!');
      setNewLocalModal(false);
      setNewLocalName('');
      await loadLocais(); // Recarrega a lista após criar
    } catch (error: any) {
      toast.error(error.message);
    }
  };

  const HandlerChartOptionSelect = (option: string): void => {
    setOptionChart(option);
  };

  const handleDeleteModal = (local: any) => {
    setSelectedLocal(local);
    setDeleteModal(true);
  };

  return (
    <section className="flex flex-col w-full items-center justify-center pb-10">
      <Toaster
        position="top-center"
        reverseOrder={false}
        toastOptions={{
          duration: 3000,
        }}
      />
      <div className="flex flex-col gap-5 w-[90%] h-full">
        <h1 className="text-4xl font-medium text-black200 pt-5 ">Locais</h1>
        <fieldset className="w-full flex flex-col lg:flex-row items-end gap-5">
          <div className="flex items-center w-full relative">
            <I.Search size={24} className="stroke-black200/70 absolute top-2 left-3" />
            <input
              placeholder="Pesquisar..."
              className="input w-full px-12 bg-white"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
          </div>
          <div className="w-full flex flex-col md:flex-row justify-between items-end gap-5">
            <label className="flex flex-col gap-1 lg:w-1/2 w-full">
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

        {/* Indicador de carregamento dos itens - versão spinner discreto */}
        {loadingItems && (
          <div className="flex items-center justify-start gap-2 text-gray-500 text-sm py-1">
            <I.Loader className="animate-spin" size={16} />
            <span>Carregando itens das prateleiras...</span>
          </div>
        )}

        <TableLocais data={filteredLocais} onEdit={handleEditModal} onRemove={handleDeleteModal} />
      </div>

      {/* Modais (edit, create, delete) - mantidos iguais ao original */}
      {editModal && (
        <Modal
          onClose={() => setEditModal(false)}
          Children={
            <div className="flex flex-col gap-5 min-w-100">
              <h2 className="font-medium text-2xl text-black">Alterar nome do Local</h2>
              <div className="w-full flex flex-col gap-1">
                <label className="text-lg text-black font-medium">Nome</label>
                <input
                  className="input px-4"
                  type="text"
                  value={changeName ?? ''}
                  onChange={(e) => setChangeName(e.target.value)}
                  placeholder="Altere o nome"
                />
              </div>
              <div className="w-full flex flex-row gap-5">
                <button
                  onClick={() => setEditModal(false)}
                  className="btn px-8 py-1.5 w-1/2 text-sm lg:text-base bg-white border border-black400/70 text-black400/70"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  onClick={handleUpdateLocal}
                  className="btn w-1/2 bg-blue200 text-white px-8 py-1.5 text-sm lg:text-base"
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
              <div className="w-full flex flex-col gap-1">
                <label className="text-lg text-black font-medium">Nome</label>
                <input
                  className="input px-4"
                  type="text"
                  value={newLocalName ?? ''}
                  onChange={(e) => setNewLocalName(e.target.value)}
                  placeholder="Defina um nome"
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
                  onClick={handleCreateLocal}
                  className="btn w-1/2 bg-blue200 text-white px-8 py-1.5 text-sm lg:text-base"
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
              <div className="rounded-full p-3 bg-pink200/10 w-fit">
                <I.Trash2 className="stroke-pink200" size={53} />
              </div>
              <h2 className="font-medium text-2xl text-black text-justify w-full flex flex-col items-center">
                <p>Deseja realmente excluir</p>
                <p className="text-pink200 truncate">{itemName}</p>
              </h2>

              <div className="w-full flex flex-row gap-5">
                <button
                  onClick={() => setDeleteModal(false)}
                  className="btn px-8 py-1.5 w-1/2 text-sm lg:text-base bg-white border border-black400/70 text-black400/70"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  onClick={handleDeleteLocal}
                  className="btn w-1/2 bg-pink200 text-white px-8 py-1.5 text-sm lg:text-base"
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