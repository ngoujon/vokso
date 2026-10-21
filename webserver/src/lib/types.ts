// Formes des réponses de l'API utilisées par l'application.

export type Role = 'user' | 'admin';

export interface User {
  id: number;
  email: string;
  role: Role;
  status?: 'active' | 'disabled';
  must_change_password?: boolean;
  totp_enabled?: boolean;
}

/** Podcast avec détail des coûts (espace utilisateur et admin). */
export interface PodcastWithCosts {
  generation_id: string;
  title: string;
  description: string;
  image_url: string;
  audio_url: string;
  created_at: string;
  category: string | null;
  cost_text: string | number;
  cost_image: string | number;
  cost_audio: string | number;
  cost_total: string | number;
  user_email?: string | null;
}

export interface AdminKpis {
  total_users: number;
  total_podcasts: number;
  podcasts_today: number;
  podcasts_this_week: number;
  cost: { text: number; image: number; audio: number; total: number; average_per_podcast: number };
  jobs_by_status: { pending: number; processing: number; done: number; error: number };
  top_categories: { label: string; total: number }[];
}

export interface StatsDay {
  date: string;
  generations: number;
  errors: number;
  cost: number;
  plays: number;
  downloads_audio: number;
  downloads_pdf: number;
}

export interface StatsTotals {
  generations: number;
  cost: number;
  errors: number;
  error_rate: number;
  plays: number;
  downloads: number;
}

export type AiBudgetUsage = Record<string, Record<'hourly' | 'daily', { used: number; max: number }>>;

export interface AdminStats {
  days: number;
  events_ready: boolean;
  series: StatsDay[];
  current: StatsTotals;
  previous: StatsTotals;
  all_time: { plays: number; downloads: number };
  top_episodes: { generation_id: string; title: string; slug: string | null; plays: number; downloads: number }[];
  ai_budget: AiBudgetUsage;
}

export interface ConsentSummary {
  ready: boolean;
  days: number;
  accepted: number;
  refused: number;
  total: number;
  latest: { created_at: string; consent_id: string; choice: 'accepted' | 'refused'; banner_version: string; page: string | null }[];
}

export interface AdminUser {
  id: number;
  email: string;
  role: Role;
  status: 'active' | 'disabled';
  created_at: string;
  podcasts_count: number;
}
