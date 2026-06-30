import { AIcon, C } from '@/lib/avana';

interface RoleModalProps {
    roleName: string;
    onChangeName: (value: string) => void;
    onSubmit: () => void;
    onClose: () => void;
}

/** "Buat Role Kustom" modal: a single name field that creates a tenant role. */
export function RoleModal({
    roleName,
    onChangeName,
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
                    Buat Role Kustom
                </div>
                <div
                    style={{
                        fontSize: 13.5,
                        color: C.muted,
                        marginTop: 8,
                        lineHeight: 1.55,
                    }}
                >
                    Tambahkan peran baru untuk tenant Anda. Atur izinnya melalui
                    matriks setelah dibuat.
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
                        Nama Role <span style={{ color: C.red }}>*</span>
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
                        }}
                    >
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
                        Buat Role
                    </button>
                </div>
            </div>
        </div>
    );
}
