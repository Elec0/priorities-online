import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { IndexPage } from './pages/IndexPage';

const root = document.getElementById('root');
if (root) {
  createRoot(root).render(
    <StrictMode>
      <IndexPage />
    </StrictMode>
  );
}
