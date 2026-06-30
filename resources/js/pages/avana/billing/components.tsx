import type { CSSProperties, ReactNode } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import { INVOICE_STATUS_LABEL, SUBSCRIPTION_STATUS_LABEL } from './types';

export const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

export const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

export const selectStyle: CSSProperties = { ...inputStyle, cursor: 'pointer' };

export const iconBtn: CSSProperties = {
    width: 32,
    height: 32,
    border: `1px solid ${C.border}`,
    background: '#fff',
    borderRadius: 8,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: '.15s',
    textDecoration: 'none',
};

const INVOICE_COLORS: Record<string, [string, string]> = {
    paid: [C.green, 'rgba(22,163,74,.1)'],
    unpaid: [C.amber, 'rgba(217,119,6,.1)'],
    overdue: [C.red, 'rgba(220,38,38,.1)'],
    cancelled: [C.muted, 'rgba(107,114,128,.12)'],
};

const SUBSCRIPTION_COLORS: Record<string, [string, string]> = {
    active: [C.green, 'rgba(22,163,74,.1)'],
    trial: [C.sky, 'rgba(110,155,230,.15)'],
    past_due: [C.amber, 'rgba(217,119,6,.1)'],
    cancelled: [C.muted, 'rgba(107,114,128,.12)'],
};

function Pill({ color, bg, label }: { color: string; bg: string; label: string }) {
    return (
        <span style={{ display: 'inline-block', padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color, background: bg }}>
            {label}
        </span>
    );
}

export function InvoiceStatusPill({ status }: { status: string }) {
    const [color, bg] = INVOICE_COLORS[status] ?? INVOICE_COLORS.cancelled;

    return <Pill color={color} bg={bg} label={INVOICE_STATUS_LABEL[status] ?? status} />;
}

export function SubscriptionStatusPill({ status }: { status: string }) {
    const [color, bg] = SUBSCRIPTION_COLORS[status] ?? SUBSCRIPTION_COLORS.cancelled;

    return <Pill color={color} bg={bg} label={SUBSCRIPTION_STATUS_LABEL[status] ?? status} />;
}

export function KpiCard({ icon, label, value, accent }: { icon: string; label: string; value: ReactNode; accent?: string }) {
    return (
        <div style={{ ...card, padding: 18, flex: 1, minWidth: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                <div
                    style={{
                        width: 34,
                        height: 34,
                        borderRadius: 9,
                        background: accent ? `${accent}1a` : C.surface,
                        color: accent ?? C.muted,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <AIcon name={icon} size={16} color={accent ?? C.muted} />
                </div>
                <div style={{ fontSize: 12.5, color: C.muted }}>{label}</div>
            </div>
            <div style={{ fontSize: 22, fontWeight: 700, color: C.navy }}>{value}</div>
        </div>
    );
}

export function Modal({ title, onClose, children, width = 560 }: { title: string; onClose: () => void; children: ReactNode; width?: number }) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'flex-start', justifyContent: 'center', padding: '48px 20px', overflowY: 'auto' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
            <div style={{ position: 'relative', width: '100%', maxWidth: width, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26, animation: 'toastIn .2s ease' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 }}>
                    <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>{title}</div>
                    <button onClick={onClose} style={iconBtn} aria-label="Tutup">
                        <AIcon name="x" size={16} color={C.muted} />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
