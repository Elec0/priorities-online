import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { LobbyPage } from './pages/LobbyPage';

const root = document.getElementById('root');
if (root) {
  createRoot(root).render(
    <StrictMode>
      <LobbyPage />
    </StrictMode>
  );
}
