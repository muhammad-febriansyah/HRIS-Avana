/** Shared shapes for the Rapat & Transkrip screens. */

export type MeetingStatus = 'recording' | 'processing' | 'ready' | 'failed';

export type MeetingVisibility = 'participants' | 'tenant';

export interface MeetingRow {
    id: number;
    title: string;
    location: string | null;
    status: MeetingStatus;
    started_at: string | null;
    duration_minutes: number;
    recorded_by: string | null;
    action_item_count: number;
    has_summary: boolean;
    participants: string[];
}

export interface MeetingDetail extends MeetingRow {
    summary: string | null;
    /** Decisions the meeting reached, as the model listed them. */
    decisions: string[];
    summary_model: string | null;
    summary_tokens: number;
    failure_reason: string | null;
    visibility: MeetingVisibility;
    has_audio: boolean;
    ended_at: string | null;
}

export interface TranscriptLine {
    id: number;
    timecode: string;
    start_ms: number;
    speaker_index: number;
    speaker: string;
    text: string;
}

export interface SpeakerRow {
    speaker_index: number;
    employee_id: number | null;
    display_name: string | null;
    resolved_name: string;
    guessed_by_ai: boolean;
    confidence: number | null;
    lines: number;
}

export interface ActionItemRow {
    id: number;
    text: string;
    assignee_employee_id: number | null;
    assignee: string | null;
    due_date: string | null;
    status: 'open' | 'done';
    source: 'ai' | 'manual';
}

/** One premium analysis: its label, and the stored answer when it exists. */
export interface InsightRow {
    type: string;
    label: string;
    payload: Record<string, unknown> | null;
    model: string | null;
    tokens: number;
    generated_at: string | null;
}

export interface EmployeeOption {
    id: number;
    full_name: string;
}

export interface MeetingIndexProps {
    meetings: MeetingRow[];
    filters: { search: string; status: string };
    kpis: {
        total: number;
        ready: number;
        processing: number;
        minutes: number;
        tokens: number;
    };
    recorderReady: boolean;
}

export interface MeetingDetailProps {
    meeting: MeetingDetail;
    transcript: TranscriptLine[];
    speakers: SpeakerRow[];
    actionItems: ActionItemRow[];
    insights: InsightRow[];
    employees: EmployeeOption[];
    can: { update: boolean; archive: boolean };
    proModel: string;
}

export interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

export const STATUS_LABELS: Record<MeetingStatus, string> = {
    recording: 'Merekam',
    processing: 'Diproses',
    ready: 'Siap',
    failed: 'Gagal',
};

/** `2026-07-30T09:12:00+07:00` → `30 Jul 2026 09:12`. */
export function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
