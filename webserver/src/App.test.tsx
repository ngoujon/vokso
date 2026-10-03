import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import App from './App';

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
