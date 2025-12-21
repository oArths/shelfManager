import logo from '../../../public/logo.png';
import { useAuth } from '../../contexts/AuthContext';

export default function Header() {
  const { logout } = useAuth();

  return (
    <header className=" h-[10%] px-10 flex flex-row items-center justify-between  border-b border-border">
      <div className="flex items-center justify-center w-16 aspect-square ">
        <img src={logo} />
      </div>
      <ul className="flex flex-row items-center justify-between gap-10">
        <li className="cursor-pointer px-2 py-1 hover:bg-border/30 rounded-md">Locais</li>
        <li className="cursor-pointer px-2 py-1 hover:bg-border/30 rounded-md">Relatorios</li>
      </ul>
      <button
        onClick={logout}
        className=" btn px-8 py-1.5 text-sm lg:text-base bg-white border border-pink200 text-pink200"
      >
        Sair
      </button>
    </header>
  );
}
