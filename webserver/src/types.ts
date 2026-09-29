export type Role = 'user' | 'admin';

export interface User {
  id: number;
  email: string;
  role: Role;
  status?: 'active' | 'disabled';
  must_change_password?: boolean;
  totp_enabled?: boolean;
}

/** Épisode publié, tel que renvoyé par /listing et /search. */
export interface Episode {
  id: string;
  title: string;
  description: string | null;
  image_url: string;
  audio_url: string;
  created_at: string;
  category: string | null;
  category_icon: string | null;
}

export interface Category {
  id: number;
  label: string;
  icon: string | null;
  cover_image: string | null;
  podcast_count: number;
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

export type JobStep = 'queued' | 'transcription' | 'text' | 'media' | 'finalizing' | 'done';

export interface JobStatus {
  status: 'pending' | 'processing' | 'done' | 'error';
  step: JobStep;
  progress: number;
  error: string | null;
}

export interface Usage {
  used: number;
  limit: number;
  unlimited: boolean;
}

export interface Invoice {
  id: number;
  number: string;
  issued_at: string;
  currency: string;
  description: string;
  amount_total: string | number;
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
