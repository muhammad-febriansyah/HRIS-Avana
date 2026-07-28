import { AIcon, C } from '@/lib/avana';
import type { AccessRole } from './types';

interface RoleModalProps {
    roleName: string;
    onChangeName: (value: string) => void;
    /** Role id to copy permissions and menu visibility from, '' for none. */
    copyFromRole: string;
    onChangeCopyFrom: (value: string) => void;
    roles: AccessRole[];
    onSubmit: () => void;
    onClose: () => void;
}

/**
 * "Buat Peran" modal: a name, plus an optional role to start from — a role with
 * no permissions sees no menu at all, which reads as broken rather than empty.
 */
export function RoleModal({
    roleName,
    onChangeName,
    copyFromRole,
    onChangeCopyFrom,
    roles,
    onSubmit,
    onClose,
}: RoleModalProps) {
    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
            }}
        >
            <div
                onClick={onClose}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 400,
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        width: 48,
                        height: 48,
                        borderRadius: 12,
                        background: C.primary + '1a',
                        color: C.primary,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginBottom: 16,
                    }}
                >
                    <AIcon name="shield" size={22} color={C.primary} />
                </div>
                <div
                    style={{
                        fontSize: 18,
                        fontWeight: 600,
                        color: C.navy,
                    }}
                >
                    Buat Peran Baru
                </div>
                <div
                    style={{
                        fontSize: 13.5,
                        color: C.muted,
                        marginTop: 8,
                        lineHeight: 1.55,
                    }}
                >
                    Peran baru langsung jadi tab tersendiri. Di tab itu Anda
                    memasukkan penggunanya lalu memilih menu yang mereka lihat.
                </div>
                <div style={{ marginTop: 18 }}>
                    <label
                        style={{
                            display: 'block',
                            fontSize: 13,
                            fontWeight: 500,
                            marginBottom: 7,
                        }}
                    >
                        Nama Peran <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        value={roleName}
                        autoFocus
                        onChange={(event) => onChangeName(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                onSubmit();
                            }
                        }}
                        placeholder="mis. Supervisor Cabang"
                        style={{
                            width: '100%',
                            height: 42,
                            padding: '0 13px',
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            fontSize: 13.5,
                            color: C.text,
                            outline: 'none',
                        }}
                    />
                </div>

                <div style={{ marginTop: 14 }}>
                    <label
                        style={{
                            display: 'block',
                            fontSize: 13,
                            fontWeight: 500,
                            marginBottom: 7,
                        }}
                    >
                        Template awal{' '}
                        <span style={{ color: C.faint, fontWeight: 400 }}>
                            (opsional)
                        </span>
                    </label>
                    <div
                        style={{
                            fontSize: 12,
                            color: C.muted,
                            marginBottom: 7,
                            lineHeight: 1.5,
                        }}
                    >
                        Peran baru mewarisi izin &amp; menu peran yang dipilih.
                        Bisa diubah setelahnya, dan tidak ikut berubah kalau
                        peran contohnya nanti diubah.
                    </div>
                    <select
                        value={copyFromRole}
                        onChange={(event) =>
                            onChangeCopyFrom(event.target.value)
                        }
                        style={{
                            width: '100%',
                            height: 42,
                            padding: '0 11px',
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            fontSize: 13.5,
                            color: C.text,
                            background: '#fff',
                            outline: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        <option value="">
                            Tanpa template — peran mulai kosong tanpa menu
                        </option>
                        {roles
                            .filter((role) => role.code !== 'super_admin')
                            .map((role) => (
                                <option key={role.id} value={role.id}>
                                    Tiru {role.name}
                                </option>
                            ))}
                    </select>
                    <div
                        style={{
                            fontSize: 12,
                            color: C.faint,
                            marginTop: 7,
                            lineHeight: 1.5,
                        }}
                    >
                        Contoh: “Supervisor Cabang” pilih <b>Tiru Manager</b>,
                        lalu buang menu Payroll di tabnya. Tanpa template, peran
                        baru tidak melihat menu apa pun sampai Anda
                        menyalakannya satu per satu.
                    </div>
                </div>

                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button
                        onClick={onClose}
                        style={{
                            flex: 1,
                            height: 44,
                            background: '#fff',
                            color: C.text,
                            border: `1px solid ${C.border}`,
                            borderRadius: 9,
                            fontSize: 14,
                            fontWeight: 500,
                            cursor: 'pointer',
                            transition: '.15s',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 8,
                        }}
                    >
                        <AIcon name="x" size={16} />
                        Batal
                    </button>
                    <button
                        onClick={onSubmit}
                        disabled={roleName.trim() === ''}
                        style={{
                            flex: 1,
                            height: 44,
                            background: C.primary,
                            color: '#fff',
                            border: 'none',
                            borderRadius: 9,
                            fontSize: 14,
                            fontWeight: 600,
                            cursor:
                                roleName.trim() === ''
                                    ? 'not-allowed'
                                    : 'pointer',
                            opacity: roleName.trim() === '' ? 0.6 : 1,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 8,
                            transition: '.15s',
                        }}
                    >
                        <AIcon name="plus" size={16} />
                        Buat Peran
                    </button>
                </div>
            </div>
        </div>
    );
}
