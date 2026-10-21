import React, { useEffect, useState } from 'react';
import { apiRequest, downloadAuthenticated, errorMessage } from '../lib/api';
import DashboardHeader from '../components/DashboardHeader';
import EpisodeDownloads from '../components/EpisodeDownloads';
import Footer from '../components/Footer';
import LineChart from '../components/LineChart';
import SecuritySection from '../components/SecuritySection';
import ChangePassword from './ChangePassword';
import type { AdminKpis, AdminStats, AdminUser, ConsentSummary, PodcastWithCosts, StatsDay, StatsTotals } from '../lib/types';
import { cleanTitle } from '../lib/text';

// Espace administrateur, en onglets : activité, audience, générations,
// épisodes, comptes, cookies (RGPD) et sécurité du compte.

const TABS = [
  { id: 'apercu', label: "Vue d'ensemble" },
  { id: 'audience', label: 'Écoutes & téléchargements' },
  { id: 'generations', label: 'Générations & coûts' },
  { id: 'episodes', label: 'Épisodes' },
  { id: 'comptes', label: 'Comptes' },
  { id: 'cookies', label: 'Cookies & RGPD' },
  { id: 'securite', label: 'Mot de passe & 2FA' },
] as const;

type TabId = (typeof TABS)[number]['id'];

const PERIODS = [7, 30, 90, 365];

const dollars = (v: number) => `${v.toFixed(v >= 10 ? 0 : 2)} $`;
const integer = (v: number) => Math.round(v).toLocaleString('fr-FR');

function KpiTile({ label, value, sub }: { label: string; value: React.ReactNode; sub?: React.ReactNode }) {
  return (
    <div className="kpi-tile">
      <span className="kpi-tile-label">{label}</span>
      <span className="kpi-tile-value">{value}</span>
      {sub && <span className="kpi-tile-sub">{sub}</span>}
    </div>
  );
}

/** Évolution par rapport à la période précédente de même durée. */
function Delta({ current, previous, invert = false }: { current: number; previous: number; invert?: boolean }) {
  if (previous === 0) {
    return <span className="kpi-delta">{current > 0 ? 'nouveau sur la période' : 'stable'}</span>;
  }
  const pct = Math.round(((current - previous) / previous) * 100);
  const good = invert ? pct < 0 : pct > 0;
  const sign = pct > 0 ? '▲ +' : pct < 0 ? '▼ ' : '';
  return (
    <span className={`kpi-delta ${pct === 0 ? '' : good ? 'kpi-delta--good' : 'kpi-delta--bad'}`}>
      {sign}{pct} % vs période précédente
    </span>
  );
}

function PeriodPicker({ days, onChange }: { days: number; onChange: (d: number) => void }) {
  return (
    <div className="period-picker" role="group" aria-label="Période">
      {PERIODS.map((p) => (
        <button key={p} type="button" aria-pressed={days === p} onClick={() => onChange(p)}>
          {p === 365 ? '1 an' : `${p} j`}
        </button>
      ))}
    </div>
  );
}

const pick = (series: StatsDay[], value: (d: StatsDay) => number) => series.map((d) => ({ date: d.date, value: value(d) }));

function StatTiles({ stats, keys }: { stats: AdminStats; keys: (keyof StatsTotals)[] }) {
  const tiles: Record<keyof StatsTotals, { label: string; format: (v: number) => string; invert?: boolean }> = {
    plays: { label: 'Écoutes', format: integer },
    downloads: { label: 'Téléchargements', format: integer },
    generations: { label: 'Épisodes publiés', format: integer },
    cost: { label: 'Coût IA estimé', format: dollars, invert: true },
    errors: { label: 'Générations en échec', format: integer, invert: true },
    error_rate: { label: "Taux d'échec", format: (v) => `${v} %`, invert: true },
  };
  return (
    <div className="kpi-grid">
      {keys.map((k) => (
        <KpiTile
          key={k}
          label={tiles[k].label}
          value={tiles[k].format(stats.current[k])}
          sub={<Delta current={stats.current[k]} previous={stats.previous[k]} invert={tiles[k].invert} />}
        />
      ))}
    </div>
  );
}

function EventsNotice({ stats }: { stats: AdminStats }) {
  if (stats.events_ready) return null;
  return (
    <p className="admin-notice">
      La mesure des écoutes et téléchargements démarre dès que la migration <code>SQL/20261021000000.sql</code> est
      appliquée sur le serveur.
    </p>
  );
}

/** Indicateurs globaux (admin-kpis) et principales tendances de la période. */
function OverviewTab({ stats }: { stats: AdminStats }) {
  const [kpis, setKpis] = useState<AdminKpis | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiRequest<AdminKpis>('admin-kpis').then(setKpis).catch((e) => setError(errorMessage(e)));
  }, []);

  return (
    <>
      <section>
        <h2>Sur la période</h2>
        <EventsNotice stats={stats} />
        <StatTiles stats={stats} keys={['plays', 'downloads', 'generations', 'cost', 'error_rate']} />
        <div className="chart-grid-2">
          <LineChart title="Écoutes par jour" points={pick(stats.series, (d) => d.plays)} format={integer} />
          <LineChart title="Épisodes publiés par jour" points={pick(stats.series, (d) => d.generations)} format={integer} />
        </div>
      </section>
      <section>
        <h2>Depuis le lancement</h2>
        {error && <p className="auth-error">{error}</p>}
        {kpis && (
          <div className="kpi-grid">
            <KpiTile label="Épisodes publiés" value={integer(kpis.total_podcasts)} sub={`${kpis.podcasts_today} aujourd'hui · ${kpis.podcasts_this_week} sur 7 jours`} />
            <KpiTile label="Écoutes mesurées" value={integer(stats.all_time.plays)} />
            <KpiTile label="Téléchargements mesurés" value={integer(stats.all_time.downloads)} />
            <KpiTile label="Coût IA total estimé" value={`${kpis.cost.total.toFixed(2)} $`} sub={`${kpis.cost.average_per_podcast.toFixed(4)} $ / épisode`} />
            <KpiTile
              label="Générations en cours"
              value={kpis.jobs_by_status.processing + kpis.jobs_by_status.pending}
              sub={`${kpis.jobs_by_status.error} en erreur au total`}
            />
          </div>
        )}
        {kpis && kpis.top_categories.length > 0 && (
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
    </>
  );
}

function AudienceTab({ stats }: { stats: AdminStats }) {
  return (
    <section>
      <h2>Écoutes & téléchargements</h2>
      <EventsNotice stats={stats} />
      <StatTiles stats={stats} keys={['plays', 'downloads']} />
      <div className="chart-grid-2">
        <LineChart title="Écoutes par jour" points={pick(stats.series, (d) => d.plays)} format={integer} />
        <LineChart title="Téléchargements MP3 par jour" points={pick(stats.series, (d) => d.downloads_audio)} format={integer} />
        <LineChart title="Téléchargements PDF par jour" points={pick(stats.series, (d) => d.downloads_pdf)} format={integer} />
      </div>
      <h3>Épisodes les plus écoutés</h3>
      {stats.top_episodes.length === 0 ? (
        <p>Aucune écoute mesurée sur la période.</p>
      ) : (
        <table className="data-table">
          <thead>
            <tr><th>Épisode</th><th>Écoutes</th><th>Téléchargements</th></tr>
          </thead>
          <tbody>
            {stats.top_episodes.map((e) => (
              <tr key={e.generation_id}>
                <td>{e.slug ? <a href={`/podcast/${e.slug}`} target="_blank" rel="noopener noreferrer">{cleanTitle(e.title)}</a> : cleanTitle(e.title)}</td>
                <td>{integer(Number(e.plays))}</td>
                <td>{integer(Number(e.downloads))}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <p className="admin-hint">
        Mesure anonyme, sans cookie : une écoute est comptée à la première lecture d'un épisode dans une page ; les
        téléchargements MP3 et PDF depuis la page de l'épisode.
      </p>
    </section>
  );
}

const BUDGET_LABELS: Record<string, string> = {
  image: 'Images', text: 'Texte', speech: 'Voix', transcription: 'Transcription', moderation: 'Modération',
};

function BudgetCell({ used, max }: { used: number; max: number }) {
  const full = max > 0 && used >= max;
  return (
    <td className={full ? 'budget-full' : undefined}>
      {used} / {max > 0 ? max : '∞'}{full ? ' — plafond atteint' : ''}
    </td>
  );
}

function GenerationsTab({ stats }: { stats: AdminStats }) {
  return (
    <>
      <section>
        <h2>Générations & coûts</h2>
        <StatTiles stats={stats} keys={['generations', 'cost', 'errors', 'error_rate']} />
        <div className="chart-grid-2">
          <LineChart title="Épisodes publiés par jour" points={pick(stats.series, (d) => d.generations)} format={integer} />
          <LineChart title="Coût IA estimé par jour" points={pick(stats.series, (d) => d.cost)} format={dollars} />
          <LineChart title="Générations en échec par jour" points={pick(stats.series, (d) => d.errors)} format={integer} />
        </div>
      </section>
      <section>
        <h2>Garde-fou de dépense IA</h2>
        <p className="admin-hint">
          Chaque appel payant à Mistral est compté, quel que soit l'appelant (site, n8n, commandes). Au plafond, les
          appels sont bloqués jusqu'à ce que la fenêtre glissante se libère. Plafonds réglables dans le .env de l'API
          (AI_BUDGET_*).
        </p>
        <table className="data-table">
          <thead>
            <tr><th>Type d'appel</th><th>Dernière heure</th><th>Dernières 24 h</th></tr>
          </thead>
          <tbody>
            {Object.entries(stats.ai_budget).map(([kind, usage]) => (
              <tr key={kind}>
                <th scope="row">{BUDGET_LABELS[kind] ?? kind}</th>
                <BudgetCell {...usage.hourly} />
                <BudgetCell {...usage.daily} />
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </>
  );
}

/** Comptes : un seul compte peut se connecter, les autres restent désactivés. */
function AccountsTab() {
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [error, setError] = useState<string | null>(null);

  const load = () =>
    apiRequest<{ data: AdminUser[] }>('admin-users')
      .then((d) => setUsers(d.data))
      .catch((e) => setError(errorMessage(e)));

  useEffect(() => {
    load();
  }, []);

  const disable = async (id: number) => {
    setError(null);
    try {
      await apiRequest('admin-users', { method: 'PATCH', body: JSON.stringify({ id, status: 'disabled' }) });
      await load();
    } catch (err) {
      setError(errorMessage(err));
    }
  };

  return (
    <section>
      <h2>Comptes</h2>
      <p className="admin-hint">
        Seul le compte administrateur peut se connecter à cette interface. Les autres comptes sont désactivés et ne
        peuvent pas être réactivés depuis l'interface.
      </p>
      {error && <p className="auth-error">{error}</p>}
      <table className="data-table">
        <thead>
          <tr><th>Email</th><th>Rôle</th><th>Statut</th><th>Épisodes</th><th>Créé le</th><th>Action</th></tr>
        </thead>
        <tbody>
          {users.map((u) => (
            <tr key={u.id}>
              <td>{u.email}</td>
              <td>{u.role === 'admin' ? 'Administrateur' : 'Utilisateur'}</td>
              <td>{u.status === 'active' ? 'Actif' : 'Désactivé'}</td>
              <td>{u.podcasts_count}</td>
              <td>{new Date(u.created_at).toLocaleDateString('fr-FR')}</td>
              <td className="data-table-actions">
                {u.status === 'active' && u.role !== 'admin' && (
                  <button onClick={() => disable(u.id)}>Désactiver</button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}

/** Liste paginée et filtrable de tous les épisodes, avec leur coût estimé. */
function EpisodesTab() {
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
      <h2>Épisodes</h2>
      {error && <p className="auth-error">{error}</p>}

      <input
        type="search"
        placeholder="Rechercher par titre ou email..."
        value={search}
        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
        className="admin-search"
      />

      <div className="table-scroll">
        <table className="data-table">
          <thead>
            <tr>
              <th>Titre</th><th>Compte</th><th>Catégorie</th><th>Créé le</th>
              <th>Coût texte</th><th>Coût image</th><th>Coût audio</th><th>Coût total</th><th>Fichiers</th>
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
                  <EpisodeDownloads episode={p} compact />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="pagination">
        <button disabled={page <= 1} onClick={() => setPage(page - 1)}>Précédent</button>
        <span>Page {page} / {totalPages}</span>
        <button disabled={page >= totalPages} onClick={() => setPage(page + 1)}>Suivant</button>
      </div>
    </section>
  );
}

/** Registre des choix du bandeau cookies et export pour un contrôle (CNIL). */
function CookiesTab() {
  const [summary, setSummary] = useState<ConsentSummary | null>(null);
  const [error, setError] = useState<string | null>(null);
  const today = new Date().toISOString().slice(0, 10);
  const [from, setFrom] = useState(() => {
    const d = new Date();
    d.setFullYear(d.getFullYear() - 1);
    return d.toISOString().slice(0, 10);
  });
  const [to, setTo] = useState(today);

  useEffect(() => {
    apiRequest<ConsentSummary>('admin-consents?days=30').then(setSummary).catch((e) => setError(errorMessage(e)));
  }, []);

  const exportCsv = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      const params = new URLSearchParams({ from, to });
      await downloadAuthenticated(`admin-consents-export?${params.toString()}`, `vokso-consentements-${from}_${to}.csv`);
    } catch (err) {
      setError(errorMessage(err, 'Export impossible.'));
    }
  };

  const rate = summary && summary.accepted + summary.refused > 0
    ? Math.round((summary.accepted / (summary.accepted + summary.refused)) * 100)
    : null;

  return (
    <section>
      <h2>Cookies & RGPD</h2>
      {error && <p className="auth-error">{error}</p>}
      {summary && !summary.ready && (
        <p className="admin-notice">
          Le registre des consentements démarre dès que la migration <code>SQL/20261021000000.sql</code> est appliquée
          sur le serveur.
        </p>
      )}
      {summary && (
        <div className="kpi-grid">
          <KpiTile label="Accords (30 j)" value={integer(summary.accepted)} />
          <KpiTile label="Refus (30 j)" value={integer(summary.refused)} />
          <KpiTile label="Taux d'accord (30 j)" value={rate === null ? '—' : `${rate} %`} />
          <KpiTile label="Choix enregistrés au total" value={integer(summary.total)} />
        </div>
      )}

      <h3>Exporter la preuve des consentements</h3>
      <p className="admin-hint">
        Fichier CSV (UTF-8, séparateur « ; ») : horodatage, identifiant aléatoire du choix, choix, finalité, version du
        bandeau, page, navigateur et empreinte pseudonymisée de l'IP (jamais l'adresse en clair). À remettre en cas de
        contrôle de la CNIL. Les preuves sont conservées 5 ans.
      </p>
      <form onSubmit={exportCsv} className="inline-form">
        <label>Du <input type="date" value={from} max={to} onChange={(e) => setFrom(e.target.value)} required /></label>
        <label>au <input type="date" value={to} min={from} max={today} onChange={(e) => setTo(e.target.value)} required /></label>
        <button type="submit">Télécharger le CSV</button>
      </form>

      <h3>Fonctionnement du bandeau</h3>
      <ul className="admin-list">
        <li>Aucun traceur soumis à consentement n'est déposé avant le choix ; la mesure d'audience fonctionne alors sans identifiant.</li>
        <li>« Tout refuser » et « Tout accepter » sont proposés au même niveau, avec la même mise en forme.</li>
        <li>Le choix est conservé 6 mois puis redemandé ; il reste modifiable via « Gérer les cookies » en pied de page.</li>
        <li>Un retrait efface aussitôt l'identifiant de mesure déposé dans le navigateur.</li>
      </ul>

      {summary && summary.latest.length > 0 && (
        <>
          <h3>Derniers choix</h3>
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr><th>Date</th><th>Identifiant</th><th>Choix</th><th>Version</th><th>Page</th></tr>
              </thead>
              <tbody>
                {summary.latest.map((c) => (
                  <tr key={`${c.consent_id}-${c.created_at}`}>
                    <td>{new Date(c.created_at.replace(' ', 'T')).toLocaleString('fr-FR')}</td>
                    <td><code>{c.consent_id.slice(0, 8)}…</code></td>
                    <td>{c.choice === 'accepted' ? 'Accepté' : 'Refusé'}</td>
                    <td>{c.banner_version}</td>
                    <td>{c.page}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </section>
  );
}

function readTab(): TabId {
  const hash = window.location.hash.slice(1);
  return (TABS.find((t) => t.id === hash)?.id ?? 'apercu') as TabId;
}

export default function AdminDashboard() {
  const [tab, setTab] = useState<TabId>(readTab);
  const [days, setDays] = useState(30);
  const [stats, setStats] = useState<AdminStats | null>(null);
  const [statsError, setStatsError] = useState<string | null>(null);

  useEffect(() => {
    const onHash = () => setTab(readTab());
    window.addEventListener('hashchange', onHash);
    return () => window.removeEventListener('hashchange', onHash);
  }, []);

  useEffect(() => {
    setStatsError(null);
    apiRequest<AdminStats>(`admin-stats?days=${days}`).then(setStats).catch((e) => setStatsError(errorMessage(e)));
  }, [days]);

  const choose = (id: TabId) => {
    setTab(id);
    window.history.replaceState(null, '', `#${id}`);
  };

  const needsStats = tab === 'apercu' || tab === 'audience' || tab === 'generations';

  return (
    <>
      <div className="container admin">
        <DashboardHeader title="Administration" />

        <nav className="admin-tabs" role="tablist" aria-label="Sections de l'administration">
          {TABS.map((t) => (
            <button
              key={t.id}
              type="button"
              role="tab"
              id={`tab-${t.id}`}
              aria-selected={tab === t.id}
              aria-controls="admin-panel"
              onClick={() => choose(t.id)}
            >
              {t.label}
            </button>
          ))}
        </nav>

        <div id="admin-panel" role="tabpanel" aria-labelledby={`tab-${tab}`}>
          {needsStats && <PeriodPicker days={days} onChange={setDays} />}
          {needsStats && statsError && <p className="auth-error">{statsError}</p>}
          {needsStats && !stats && !statsError && <p>Chargement des statistiques...</p>}
          {tab === 'apercu' && stats && <OverviewTab stats={stats} />}
          {tab === 'audience' && stats && <AudienceTab stats={stats} />}
          {tab === 'generations' && stats && <GenerationsTab stats={stats} />}
          {tab === 'episodes' && <EpisodesTab />}
          {tab === 'comptes' && <AccountsTab />}
          {tab === 'cookies' && <CookiesTab />}
          {tab === 'securite' && (
            <>
              <ChangePassword />
              <SecuritySection />
            </>
          )}
        </div>
      </div>
      <Footer />
    </>
  );
}
