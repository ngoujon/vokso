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

export interface AdminUser {
  id: number;
  email: string;
  role: Role;
  status: 'active' | 'disabled';
  created_at: string;
  podcasts_count: number;
}
