import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import Home from './Home';

function mockListingFetch() {
  return jest.fn().mockResolvedValue({
    ok: true,
    json: async () => ({ success: true, data: [] }),
  });
}

beforeEach(() => {
  global.fetch = mockListingFetch();
});

afterEach(() => {
  jest.clearAllMocks();
});

test('affiche le formulaire en mode texte par défaut', async () => {
  render(<Home />);
  expect(screen.getByPlaceholderText('Tapez ici...')).toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Générer' })).toBeInTheDocument();
  await waitFor(() => expect(global.fetch).toHaveBeenCalled());
});

test('bascule vers le mode fichier audio', async () => {
  const user = userEvent.setup();
  render(<Home />);

  await user.click(screen.getByRole('button', { name: 'Fichier audio' }));

  expect(screen.queryByPlaceholderText('Tapez ici...')).not.toBeInTheDocument();
  expect(document.querySelector('input[type="file"]')).toBeInTheDocument();
});

test('affiche une erreur si on soumet le mode audio sans fichier', async () => {
  const user = userEvent.setup();
  render(<Home />);

  await user.click(screen.getByRole('button', { name: 'Fichier audio' }));
  await user.click(screen.getByRole('button', { name: 'Générer' }));

  expect(await screen.findByText('Merci de sélectionner un fichier audio.')).toBeInTheDocument();
});

test('envoie le sujet saisi lors de la soumission du formulaire', async () => {
  const user = userEvent.setup();
  global.fetch = jest.fn()
    .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [] }) })
    .mockResolvedValueOnce({ ok: true, json: async () => ({ job_id: 'job-1' }) });

  render(<Home />);

  const input = screen.getByPlaceholderText('Tapez ici...');
  await user.type(input, 'Un sujet de test');
  await user.click(screen.getByRole('button', { name: 'Générer' }));

  await waitFor(() => {
    expect(global.fetch).toHaveBeenLastCalledWith(
      expect.stringContaining('/generation'),
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ input: 'Un sujet de test' }),
      })
    );
  });
});
