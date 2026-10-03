import React, { useEffect, useState } from 'react';
import { apiRequest, downloadText, errorMessage, triggerDownload } from '../api';
import { useAuth } from '../AuthContext';
import SecuritySection from '../components/SecuritySection';
import { staticFileUrl } from '../config';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';
import type { AdminKpis, AdminUser, PodcastWithCosts, Role } from '../types';
import { cleanTitle } from '../utils/text';
import '../styles/globals.css';
import '../styles/Dashboard.css';

function KpiTile({ label, value, sub }: { label: string; value: React.ReactNode; sub?: string }) {
  return (
    <div className="kpi-tile">
      <span className="kpi-tile-label">{label}</span>
      <span className="kpi-tile-value">{value}</span>
      {sub && <span className="kpi-tile-sub">{sub}</span>}
    </div>
  );
}

function KpiSection() {
  const [kpis, setKpis] = useState<AdminKpis | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiRequest<AdminKpis>('admin-kpis').then(setKpis).catch((e) => setError(errorMessage(e)));
  }, []);

  if (error) return <p className="auth-error">{error}</p>;
  if (!kpis) return <p>Chargement des indicateurs...</p>;

  return (
    <section>
      <h2>Vue d'ensemble</h2>
      <div className="kpi-grid">
        <KpiTile label="Comptes utilisateurs" value={kpis.total_users} />
        <KpiTile label="Podcasts générés" value={kpis.total_podcasts} />
        <KpiTile label="Aujourd'hui" value={kpis.podcasts_today} />
        <KpiTile label="7 derniers jours" value={kpis.podcasts_this_week} />
        <KpiTile label="Coût total estimé" value={`${kpis.cost.total.toFixed(2)} $`} />
        <KpiTile label="Coût moyen / podcast" value={`${kpis.cost.average_per_podcast.toFixed(4)} $`} />
        <KpiTile
          label="Répartition du coût"
          value={`T:${kpis.cost.text.toFixed(2)}$ · I:${kpis.cost.image.toFixed(2)}$ · A:${kpis.cost.audio.toFixed(2)}$`}
          sub="Texte / Image / Audio"
        />
        <KpiTile
          label="Générations en cours"
          value={kpis.jobs_by_status.processing + kpis.jobs_by_status.pending}
          sub={`${kpis.jobs_by_status.error} en erreur`}
        />
      </div>
      {kpis.top_categories.length > 0 && (
        <div className="top-categories">
          <h3>Catégories les plus générées</h3>
          <ul>
            {kpis.top_categories.map((c) => (
              <li key={c.label}>{c.label} — {c.total}</li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}

function UsersSection() {
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState<{ email: string; password: string; role: Role }>({ email: '', password: '', role: 'user' });
  const [creating, setCreating] = useState(false);

  const load = () =>
    apiRequest<{ data: AdminUser[] }>('admin-users')
      .then((d) => setUsers(d.data))
      .catch((e) => setError(errorMessage(e)));

  useEffect(() => {
    load();
  }, []);

  const createUser = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreating(true);
    setError(null);
    try {
      await apiRequest('admin-users', { method: 'POST', body: JSON.stringify(form) });
      setForm({ email: '', password: '', role: 'user' });
      await load();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setCreating(false);
    }
  };

  const updateUser = async (id: number, patch: Partial<Pick<AdminUser, 'role' | 'status'>>) => {
    setError(null);
    try {
      await apiRequest('admin-users', { method: 'PATCH', body: JSON.stringify({ id, ...patch }) });
      await load();
    } catch (err) {
      setError(errorMessage(err));
    }
  };

  return (
    <section>
      <h2>Comptes utilisateurs</h2>
      {error && <p className="auth-error">{error}</p>}

      <form onSubmit={createUser} className="inline-form">
        <input
          type="email"
          placeholder="Email"
          value={form.email}
          onChange={(e) => setForm({ ...form, email: e.target.value })}
          required
        />
        <input
          type="password"
          placeholder="Mot de passe temporaire (12 caractères min.)"
          minLength={12}
          value={form.password}
          onChange={(e) => setForm({ ...form, password: e.target.value })}
          required
        />
        <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value as Role })}>
          <option value="user">Utilisateur</option>
          <option value="admin">Administrateur</option>
        </select>
        <button type="submit" disabled={creating}>Créer le compte</button>
      </form>

      <table className="data-table">
        <thead>
          <tr>
            <th>Email</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th>Podcasts</th>
            <th>Créé le</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {users.map((u) => (
            <tr key={u.id}>
              <td>{u.email}</td>
              <td>{u.role}</td>
              <td>{u.status}</td>
              <td>{u.podcasts_count}</td>
              <td>{new Date(u.created_at).toLocaleDateString('fr-FR')}</td>
              <td className="data-table-actions">
                <button onClick={() => updateUser(u.id, { role: u.role === 'admin' ? 'user' : 'admin' })}>
                  {u.role === 'admin' ? 'Retirer admin' : 'Passer admin'}
                </button>
                <button onClick={() => updateUser(u.id, { status: u.status === 'active' ? 'disabled' : 'active' })}>
                  {u.status === 'active' ? 'Désactiver' : 'Réactiver'}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}

function PodcastsSection() {
  const [podcasts, setPodcasts] = useState<PodcastWithCosts[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [error, setError] = useState<string | null>(null);
  const perPage = 20;

  useEffect(() => {
    const params = new URLSearchParams({ page: String(page), per_page: String(perPage), search });
    apiRequest<{ data: PodcastWithCosts[]; total: number }>(`admin-podcasts?${params.toString()}`)
      .then((d) => {
        setPodcasts(d.data);
        setTotal(d.total);
      })
      .catch((e) => setError(errorMessage(e)));
  }, [page, search]);

  const totalPages = Math.max(1, Math.ceil(total / perPage));

  return (
    <section>
      <h2>Monitoring des podcasts</h2>
      {error && <p className="auth-error">{error}</p>}

      <input
        type="search"
        placeholder="Rechercher par titre ou email..."
        value={search}
        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
        className="admin-search"
      />

      <table className="data-table">
        <thead>
          <tr>
            <th>Titre</th>
            <th>Compte</th>
            <th>Catégorie</th>
            <th>Créé le</th>
            <th>Coût texte</th>
            <th>Coût image</th>
            <th>Coût audio</th>
            <th>Coût total</th>
            <th>Fichiers</th>
          </tr>
        </thead>
        <tbody>
          {podcasts.map((p) => (
            <tr key={p.generation_id}>
              <td>{cleanTitle(p.title)}</td>
              <td>{p.user_email || 'Anonyme'}</td>
              <td>{p.category}</td>
              <td>{new Date(p.created_at).toLocaleString('fr-FR')}</td>
              <td>{Number(p.cost_text).toFixed(4)} $</td>
              <td>{Number(p.cost_image).toFixed(4)} $</td>
              <td>{Number(p.cost_audio).toFixed(4)} $</td>
              <td><strong>{Number(p.cost_total).toFixed(4)} $</strong></td>
              <td className="data-table-actions">
                <button onClick={() => triggerDownload(staticFileUrl('images', p.image_url), p.image_url, true)}>Image</button>
                <button onClick={() => triggerDownload(staticFileUrl('audios', p.audio_url), p.audio_url, true)}>Audio</button>
                <button onClick={() => downloadText(cleanTitle(p.title), p.description)}>Texte</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="pagination">
        <button disabled={page <= 1} onClick={() => setPage(page - 1)}>Précédent</button>
        <span>Page {page} / {totalPages}</span>
        <button disabled={page >= totalPages} onClick={() => setPage(page + 1)}>Suivant</button>
      </div>
    </section>
  );
}

export default function AdminDashboard() {
  useCanonical();
  useDocumentMeta(
    'Administration — Vokso',
    "Espace d'administration Vokso : gestion des utilisateurs et suivi de l'activité."
  );
  const { user, logout } = useAuth();

  return (
    <div className="container">
      <div className="dashboard-header">
        <h1>Espace administrateur</h1>
        <div className="dashboard-header-actions">
          <span>{user?.email}</span>
          <a href="/">Accueil</a>
          <button onClick={logout}>Déconnexion</button>
        </div>
      </div>

      <SecuritySection />
      <KpiSection />
      <UsersSection />
      <PodcastsSection />
    </div>
  );
}
