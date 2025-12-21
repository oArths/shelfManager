import { useState } from 'react';
import logo from '../../../public/logo.png';
import toast, { Toaster } from 'react-hot-toast';
import { useAuth } from '../../contexts/AuthContext';
import { useNavigate, useLocation } from 'react-router-dom';

export default function Login() {
  const handleLogin = (): void => {};

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const navigate = useNavigate();
  const location = useLocation();
  const { login } = useAuth();

  const from = location.state?.from?.pathname || '/';

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const success = await login(username, password);

      if (success) {
        toast.success('Login Efetuado!!');
        navigate(from + 'local', { replace: true });
      } else {
        toast.error('Erro ao fazer login. Tente novamente. !');
      }
    } catch (err) {
      toast.error('Erro ao fazer login. Tente novamente. !');
    } finally {
      setLoading(false);
    }
  };
  return (
    <section className="bg-white_cold w-full h-screen flex items-center justify-center">
      <Toaster
        position="top-center"
        reverseOrder={false}
        toastOptions={{
          duration: 3000,
        }}
      />
      <nav className="flex flex-col w-fit gap-7 min-w-100 bg-white py-10 px-14  rounded-md border border-border">
        <div className="flex items-center justify-center ">
          <img src={logo} />
        </div>

        <form className=" flex flex-col gap-5" onSubmit={handleSubmit}>
          <div className="w-full flex flex-col gap-1 ">
            <label className="text-base text-black font-medium ">Usuário</label>
            <input
              className="input px-4 "
              type="text"
              id="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Digite seu usuario"
              disabled={loading}
            />
          </div>
          <div>
            <label className="text-base text-black font-medium ">Senha</label>
            <input
              className="input  px-4"
              type="password"
              id="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Digite sua senha"
              disabled={loading}
            />
          </div>
          <button
            type="submit"
            disabled={loading || !username || !password}
            onClick={handleLogin}
            className="btn bg-blue200 text-white  px-8 py-1.5 text-sm lg:text-base"
          >
            {loading ? 'Entrando...' : 'Entrar'}
          </button>
        </form>
      </nav>
    </section>
  );
}
