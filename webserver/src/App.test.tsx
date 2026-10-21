import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import App from './App';
import AdminApp from './pages/AdminApp';
import { AuthProvider } from './lib/AuthContext';

test("affiche le formulaire de connexion de l'administration sans session", async () => {
  render(
    <MemoryRouter>
      <AuthProvider>
        <AdminApp />
      </AuthProvider>
    </MemoryRouter>
  );
  expect(await screen.findByRole('heading', { name: 'Administration' })).toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Se connecter' })).toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Gérer les cookies' })).toBeInTheDocument();
});

test('affiche la page de contact avec l\'en-tête et le pied de page communs', () => {
  render(
    <MemoryRouter initialEntries={['/contact']}>
      <App />
    </MemoryRouter>
  );
  expect(screen.getByRole('heading', { name: 'Contact' })).toBeInTheDocument();
  expect(screen.getAllByRole('link', { name: 'Discothèque' }).length).toBeGreaterThan(0);
  expect(screen.getByRole('link', { name: 'Toute la discothèque' })).toHaveAttribute('href', '/discotheque');
});
