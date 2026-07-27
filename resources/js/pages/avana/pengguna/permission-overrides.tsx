import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import UserController from '@/actions/App/Http/Controllers/Avana/UserController';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import type { PermissionActionOption, PermissionOverride } from './types';

interface PermissionOverridesProps {
    userId: number;
    overrides: PermissionOverride[];
    modules: string[];
    actions: PermissionActionOption[];
}

/** Prettify a module code for display, e.g. `salary_structure` -> `Salary Structure`. */
function moduleLabel(code: string): string {
    return code
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

const selectStyle: React.CSSProperties = {
    padding: '9px 11px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

/**
 * Per-user permission overrides: grant a code the user's roles lack, or revoke
 * one they inherit. Saved independently of the main user form.
 */
export function PermissionOverrides({
    userId,
    overrides,
    modules,
    actions,
}: PermissionOverridesProps) {
    const [rows, setRows] = useState<PermissionOverride[]>(overrides);
    const [module, setModule] = useState(modules[0] ?? '');
    const [action, setAction] = useState(actions[0]?.key ?? '');
    const [type, setType] = useState<'grant' | 'revoke'>('grant');
    const [saving, setSaving] = useState(false);

    const actionLabel = useMemo(
        () => Object.fromEntries(actions.map((a) => [a.key, a.label])),
        [actions],
    );

    const addRow = () => {
        if (module === '' || action === '') {
            return;
        }

        const code = `${module}.${action}`;
        setRows((current) => [
            ...current.filter((r) => r.code !== code),
            { code, type },
        ]);
    };

    const removeRow = (code: string) => {
        setRows((current) => current.filter((r) => r.code !== code));
    };

    const save = () => {
        setSaving(true);
        router.put(
            UserController.updateOverrides(userId).url,
            { overrides: rows.map((r) => ({ code: r.code, type: r.type })) },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Hak akses khusus disimpan'),
                onError: () => toast.error('Gagal menyimpan hak akses khusus'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div style={{ ...card, marginTop: 20, padding: '22px 24px' }}>
            <div style={{ fontSize: 16, fontWeight: 700, color: C.navy }}>
                Hak Akses Khusus
            </div>
            <div style={{ fontSize: 13, color: C.muted, margin: '4px 0 18px' }}>
                Berikan (grant) izin yang tidak dimiliki role, atau cabut
                (revoke) izin yang diwarisi dari role, khusus untuk pengguna
                ini.
            </div>

            {rows.length === 0 ? (
                <div
                    style={{
                        fontSize: 13,
                        color: C.faint,
                        padding: '10px 0 16px',
                    }}
                >
                    Belum ada hak akses khusus.
                </div>
            ) : (
                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 8,
                        marginBottom: 18,
                    }}
                >
                    {rows.map((row) => {
                        const isGrant = row.type === 'grant';

                        return (
                            <span
                                key={row.code}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    padding: '5px 6px 5px 11px',
                                    borderRadius: 999,
                                    fontSize: 12.5,
                                    fontWeight: 600,
                                    color: isGrant ? '#15803D' : '#B91C1C',
                                    background: isGrant ? '#F0FDF4' : '#FEF2F2',
                                    border: `1px solid ${isGrant ? '#BBF7D0' : '#FECACA'}`,
                                }}
                            >
                                {isGrant ? 'Grant' : 'Revoke'} · {row.code}
                                <button
                                    type="button"
                                    onClick={() => removeRow(row.code)}
                                    aria-label={`Hapus ${row.code}`}
                                    style={{
                                        display: 'inline-flex',
                                        border: 'none',
                                        background: isGrant
                                            ? 'rgba(21,128,61,.14)'
                                            : 'rgba(185,28,28,.14)',
                                        borderRadius: '50%',
                                        cursor: 'pointer',
                                        color: 'inherit',
                                        padding: 2,
                                    }}
                                >
                                    <AIcon name="x" size={13} />
                                </button>
                            </span>
                        );
                    })}
                </div>
            )}

            <div
                style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    alignItems: 'center',
                    gap: 10,
                }}
            >
                <select
                    value={module}
                    onChange={(e) => setModule(e.target.value)}
                    style={{ ...selectStyle, minWidth: 180 }}
                >
                    {modules.map((m) => (
                        <option key={m} value={m}>
                            {moduleLabel(m)}
                        </option>
                    ))}
                </select>
                <select
                    value={action}
                    onChange={(e) => setAction(e.target.value)}
                    style={{ ...selectStyle, minWidth: 140 }}
                >
                    {actions.map((a) => (
                        <option key={a.key} value={a.key}>
                            {actionLabel[a.key] ?? a.key}
                        </option>
                    ))}
                </select>
                <select
                    value={type}
                    onChange={(e) =>
                        setType(e.target.value as 'grant' | 'revoke')
                    }
                    style={{ ...selectStyle, minWidth: 120 }}
                >
                    <option value="grant">Grant</option>
                    <option value="revoke">Revoke</option>
                </select>
                <button type="button" onClick={addRow} style={btnOut}>
                    <AIcon name="plus" size={15} color={C.text} />
                    Tambah
                </button>
            </div>

            <div
                style={{
                    display: 'flex',
                    justifyContent: 'flex-end',
                    marginTop: 20,
                }}
            >
                <button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    style={{
                        ...btnP,
                        background: C.green,
                        opacity: saving ? 0.7 : 1,
                        cursor: saving ? 'not-allowed' : 'pointer',
                    }}
                >
                    <AIcon name="check" size={15} color="#fff" />
                    Simpan Hak Akses Khusus
                </button>
            </div>
        </div>
    );
}
