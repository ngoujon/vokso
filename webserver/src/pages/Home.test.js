import { render, screen, waitFor, act } from '@testing-library/react';
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

test('recherche un podcast après un délai de saisie', async () => {
  jest.useFakeTimers();
  const user = userEvent.setup({ advanceTimers: jest.advanceTimersByTime });
  global.fetch = jest.fn().mockResolvedValue({ ok: true, json: async () => ({ success: true, data: [] }) });

  render(<Home />);
  await waitFor(() => expect(global.fetch).toHaveBeenCalled());
  global.fetch.mockClear();

  const searchInput = screen.getByPlaceholderText('Rechercher un podcast...');
  await user.type(searchInput, 'jazz');

  await act(async () => {
    jest.advanceTimersByTime(2000);
  });

  await waitFor(() => {
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/search?query=jazz')
    );
  });
  expect(await screen.findByText('Résultats')).toBeInTheDocument();

  jest.useRealTimers();
});

test('lit et met en pause l\'audio d\'une génération', async () => {
  const generation = {
    title: 'Podcast test',
    created_at: '2026-01-01',
    image_url: 'img.jpg',
    audio_url: 'audio.mp3',
  };
  global.fetch = jest.fn().mockResolvedValue({
    ok: true,
    json: async () => ({ success: true, data: [generation] }),
  });

  const playMock = jest.fn();
  const pauseMock = jest.fn();
  window.HTMLMediaElement.prototype.play = playMock;
  window.HTMLMediaElement.prototype.pause = pauseMock;

  const user = userEvent.setup();
  const { container } = render(<Home />);

  await waitFor(() => expect(screen.getByText('Podcast test')).toBeInTheDocument());

  const playButton = container.querySelector('.play-pause-btn');
  await user.click(playButton);
  expect(playMock).toHaveBeenCalled();

  await user.click(playButton);
  expect(pauseMock).toHaveBeenCalled();
});

test('affiche la progression de la génération pendant le sondage du statut', async () => {
  jest.useFakeTimers();
  const user = userEvent.setup({ advanceTimers: jest.advanceTimersByTime });

  global.fetch = jest.fn().mockImplementation((url) => {
    if (url.includes('/generation-status')) {
      return Promise.resolve({
        ok: true,
        json: async () => ({ status: 'processing', step: 'text', progress: 42 }),
      });
    }
    if (url.includes('/generation')) {
      return Promise.resolve({ ok: true, json: async () => ({ job_id: 'job-1' }) });
    }
    return Promise.resolve({ ok: true, json: async () => ({ success: true, data: [] }) });
  });

  render(<Home />);
  await waitFor(() => expect(global.fetch).toHaveBeenCalled());

  const input = screen.getByPlaceholderText('Tapez ici...');
  await user.type(input, 'Un sujet');
  await user.click(screen.getByRole('button', { name: 'Générer' }));

  await waitFor(() => expect(screen.getByText("En file d'attente...")).toBeInTheDocument());

  await act(async () => {
    jest.advanceTimersByTime(2000);
  });

  await waitFor(() => expect(screen.getByText('Génération du texte...')).toBeInTheDocument());

  jest.useRealTimers();
});
