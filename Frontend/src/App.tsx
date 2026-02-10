import Login from './pages/login';
import Locais from './pages/locais';
import Header from './components/header';
import { Routes, Route } from 'react-router-dom';
import PrivateRoute from './auth/component/PrivateRoute';
import Local from "./pages/local";
// TODO: usar essa api de post https://lebrecho.com.br/wp-json/prestashop-explorer/v1/table/wp_posts/sample?limit=30
// TODO: Combar com essa api: https://lebrecho.com.br/wp-json/prestashop-explorer/v1/table/wp_wc_product_meta_lookup/sample?limit=30


export default function App(): React.JSX.Element {
  return (
    <>
      <Routes>
        <Route path="/" element={<Login />} />
        <Route
          path="/locais"
          element={
            <PrivateRoute>
              <Header />
              <Locais />
            </PrivateRoute>
          }
        />
        <Route
          path="/local"
          element={
            <PrivateRoute>
              <Header />
              <Local />
            </PrivateRoute>
          }
        />
      </Routes>
    </>
  );
}
