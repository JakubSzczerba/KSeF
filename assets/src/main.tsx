import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const rootElement = document.getElementById('ksef-react-root');
if (!rootElement) {
  throw new Error('Brak elementu #ksef-react-root');
}

createRoot(rootElement).render(
  <StrictMode>
    <App bootstrap={JSON.parse(rootElement.dataset['bootstrap'] ?? '{}')} />
  </StrictMode>
);
