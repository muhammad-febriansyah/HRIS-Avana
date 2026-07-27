/**
 * Shared types for the AvanaHR employee social wall (Sosmed).
 * These mirror the `Avana\SocialController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** A post as serialized for the moderation feed. */
export interface SocialPostRow {
    id: number;
    body: string;
    image_url: string | null;
    status: 'published' | 'hidden';
    likes_count: number;
    comments_count: number;
    reports_count: number;
    author: string;
    author_photo: string | null;
    category: string | null;
    category_icon: string | null;
    category_color: string | null;
    created_at: string | null;
    created_for_humans: string | null;
}

/** A category row from the master. */
export interface SocialCategoryRow {
    id: number;
    name: string;
    slug: string;
    icon: string;
    color: string;
    description: string | null;
    status: string;
    sort_order: number;
    posts_count: number;
}

/** One entry on the contributor leaderboard. */
export interface LeaderboardRow {
    rank: number;
    employee_id: number;
    name: string;
    photo: string | null;
    posts: number;
    likes: number;
    comments: number;
    points: number;
}

/** How a point total is made up, so the UI can explain the ranking. */
export interface PointWeights {
    post: number;
    like: number;
    comment: number;
}

/** One nominee's standing in the live Employee of the Month tally. */
export interface EotmStanding {
    rank: number;
    employee_id: number;
    name: string;
    photo: string | null;
    votes: number;
    percent: number;
    core_value: string | null;
    core_value_icon: string | null;
    core_value_color: string | null;
}

/** A month of Employee of the Month voting. */
export interface EotmPeriod {
    id: number;
    period: string;
    label: string;
    title: string | null;
    description: string | null;
    status: string;
    is_open: boolean;
    closes_at: string | null;
    winner: string | null;
    winner_votes: number;
    total_votes: number;
}

/** A core value a voter attributes to their nominee. */
export interface EotmCoreValue {
    id: number;
    name: string;
    icon: string;
    color: string;
}

/** A closed period, kept for the roll of honour. */
export interface EotmHistoryRow {
    id: number;
    label: string;
    winner: string | null;
    winner_votes: number;
}

export interface EotmPayload {
    period: EotmPeriod | null;
    standings: EotmStanding[];
    core_values: EotmCoreValue[];
    history: EotmHistoryRow[];
}

/** Flat form payload backing the "open a period" modal. */
export interface PeriodFormData {
    period: string;
    title: string;
    description: string;
    closes_at: string;
}

export const emptyPeriodForm: PeriodFormData = {
    period: '',
    title: '',
    description: '',
    closes_at: '',
};

/** Flat form payload backing the core-value modal. */
export interface CoreValueFormData {
    name: string;
    icon: string;
    color: string;
}

export const emptyCoreValueForm: CoreValueFormData = {
    name: '',
    icon: 'shield-check',
    color: '#7C3AED',
};

export interface SocialKpis {
    posts: number;
    hidden: number;
    reported: number;
    categories: number;
    contributors: number;
    this_month: number;
}

/** Laravel paginator shape, trimmed to what this page reads. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface SosmedIndexProps {
    posts: Paginated<SocialPostRow>;
    categories: SocialCategoryRow[];
    leaderboard: LeaderboardRow[];
    weights: PointWeights;
    eotm: EotmPayload;
    kpis: SocialKpis;
    filters: { category?: string; status?: string; reported?: string };
}

/** Flat form payload backing the category modal. */
export interface CategoryFormData {
    name: string;
    icon: string;
    color: string;
    description: string;
    status: string;
    sort_order: string;
}

export const emptyCategoryForm: CategoryFormData = {
    name: '',
    icon: 'lightbulb',
    color: '#2F54C9',
    description: '',
    status: 'active',
    sort_order: '0',
};

/**
 * Icons offered by the picker. Kept to a curated set of Lucide names that read
 * well at chip size — a free-text field would let a typo render a blank chip.
 */
export const ICON_CHOICES: string[] = [
    'lightbulb',
    'trophy',
    'star',
    'sparkles',
    'heart',
    'party-popper',
    'megaphone',
    'users-round',
    'handshake',
    'target',
    'rocket',
    'graduation-cap',
    'coffee',
    'music',
    'camera',
    'gift',
    'shield-check',
    'leaf',
    'flame',
    'smile',
];

/** Accent swatches for a category chip. */
export const COLOR_CHOICES: string[] = [
    '#2F54C9',
    '#7C3AED',
    '#0EA5E9',
    '#16A34A',
    '#F59E0B',
    '#DC2626',
    '#DB2777',
    '#0F172A',
];

export const STATUS_OPTIONS = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];
