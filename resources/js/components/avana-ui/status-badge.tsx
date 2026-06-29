import { cn } from '@/lib/utils';

interface StatusBadgeProps {
    status: string;
    className?: string;
}

type StatusTone =
    'green' | 'amber' | 'red' | 'slate' | 'navy' | 'blue' | 'muted';

const toneClasses: Record<StatusTone, string> = {
    green: 'bg-green-50 text-green-700 ring-green-600/20',
    amber: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    red: 'bg-red-50 text-red-700 ring-red-600/20',
    slate: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    navy: 'bg-transparent text-[#0E1A3A] ring-[#0E1A3A]/30',
    blue: 'bg-[#2F54C9]/10 text-[#2F54C9] ring-[#2F54C9]/20',
    muted: 'bg-slate-50 text-slate-500 ring-slate-400/20',
};

/**
 * Maps a status string (case-insensitive, Indonesian & English) to a tone.
 * Unknown statuses fall back to slate.
 */
const statusTones: Record<string, StatusTone> = {
    // green
    active: 'green',
    aktif: 'green',
    present: 'green',
    hadir: 'green',
    approved: 'green',
    disetujui: 'green',
    tetap: 'green',
    dibayar: 'green',
    published: 'green',
    final: 'green',
    // amber
    pending: 'amber',
    menunggu: 'amber',
    late: 'amber',
    terlambat: 'amber',
    probation: 'amber',
    need_review: 'amber',
    // red
    rejected: 'red',
    ditolak: 'red',
    absent: 'red',
    alpa: 'red',
    alpha: 'red',
    // slate
    draft: 'slate',
    // navy (outline)
    locked: 'navy',
    terkunci: 'navy',
    // blue
    kontrak: 'blue',
    diproses: 'blue',
    calculated: 'blue',
    leave: 'blue',
    cuti: 'blue',
    // muted
    inactive: 'muted',
    nonaktif: 'muted',
    resign: 'muted',
};

/**
 * Pill-shaped status badge with colour mapping per the AvanaHR status guide.
 */
export function StatusBadge({ status, className }: StatusBadgeProps) {
    const key = String(status ?? '')
        .trim()
        .toLowerCase();
    const tone = statusTones[key] ?? 'slate';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ring-1 ring-inset',
                toneClasses[tone],
                className,
            )}
        >
            {status}
        </span>
    );
}

export default StatusBadge;
