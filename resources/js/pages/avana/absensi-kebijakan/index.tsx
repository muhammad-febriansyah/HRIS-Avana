import { Head, useForm } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import AttendancePolicyController from '@/actions/App/Http/Controllers/Avana/AttendancePolicyController';
import { AIcon, btnP, C, card } from '@/lib/avana';

/** Mirrors AttendancePolicy::SCOPES, loosest last. */
const SCOPE_OPTIONS = [
    {
        value: 'assigned',
        label: 'Lokasi ditugaskan',
        hint: 'Hanya di kantor / cabang karyawan sendiri.',
    },
    {
        value: 'any_branch',
        label: 'Semua cabang',
        hint: 'Boleh absen di kantor cabang mana pun. Radius tetap dicek.',
    },
    {
        value: 'anywhere',
        label: 'WFA — di mana saja',
        hint: 'Radius tidak dicek. GPS tetap direkam dan ditandai WFA.',
    },
] as const;

interface Policy {
    attendance_scope: string;
    require_face_enrollment: boolean;
    require_liveness_challenge: boolean;
    face_enforcement: string;
    integrity_enforcement: string;
    block_mock_location: boolean;
    block_rooted: boolean;
    block_emulator: boolean;
}

interface PageProps {
    policy: Policy;
    attestationEnabled: boolean;
}

const sectionTitle: CSSProperties = {
    fontSize: 15,
    fontWeight: 700,
    color: C.text,
    marginBottom: 4,
};

const sectionHint: CSSProperties = {
    fontSize: 13,
    color: C.muted,
    marginBottom: 16,
};

function Toggle({
    on,
    onChange,
    label,
    hint,
}: {
    on: boolean;
    onChange: (v: boolean) => void;
    label: string;
    hint?: string;
}) {
    return (
        <label
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 12,
                cursor: 'pointer',
                padding: '10px 0',
            }}
        >
            <button
                type="button"
                onClick={() => onChange(!on)}
                aria-pressed={on}
                style={{
                    flexShrink: 0,
                    width: 40,
                    height: 24,
                    borderRadius: 999,
                    border: 'none',
                    background: on ? C.primary : '#CBD5E1',
                    position: 'relative',
                    cursor: 'pointer',
                    transition: 'background 120ms',
                }}
            >
                <span
                    style={{
                        position: 'absolute',
                        top: 2,
                        left: on ? 18 : 2,
                        width: 20,
                        height: 20,
                        borderRadius: '50%',
                        background: '#fff',
                        transition: 'left 120ms',
                    }}
                />
            </button>
            <span>
                <span
                    style={{
                        display: 'block',
                        fontSize: 14,
                        fontWeight: 600,
                        color: C.text,
                    }}
                >
                    {label}
                </span>
                {hint ? (
                    <span
                        style={{
                            display: 'block',
                            fontSize: 12.5,
                            color: C.muted,
                            marginTop: 2,
                        }}
                    >
                        {hint}
                    </span>
                ) : null}
            </span>
        </label>
    );
}

function EnforcementChoice({
    value,
    onChange,
}: {
    value: string;
    onChange: (v: string) => void;
}) {
    const options: { key: string; label: string; hint: string }[] = [
        {
            key: 'block',
            label: 'Blokir',
            hint: 'Absen ditolak saat cek gagal.',
        },
        {
            key: 'flag',
            label: 'Tandai',
            hint: 'Absen tetap masuk, ditandai untuk HR.',
        },
    ];

    return (
        <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
            {options.map((opt) => {
                const active = value === opt.key;

                return (
                    <button
                        key={opt.key}
                        type="button"
                        onClick={() => onChange(opt.key)}
                        style={{
                            flex: 1,
                            textAlign: 'left',
                            padding: '12px 14px',
                            borderRadius: 10,
                            border: `1.5px solid ${active ? C.primary : C.border}`,
                            background: active ? '#EEF2FF' : '#fff',
                            cursor: 'pointer',
                        }}
                    >
                        <span
                            style={{
                                display: 'block',
                                fontSize: 14,
                                fontWeight: 700,
                                color: active ? C.primary : C.text,
                            }}
                        >
                            {opt.label}
                        </span>
                        <span
                            style={{
                                display: 'block',
                                fontSize: 12,
                                color: C.muted,
                                marginTop: 2,
                            }}
                        >
                            {opt.hint}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

function Section({ children }: { children: ReactNode }) {
    return (
        <div style={{ ...card, padding: 20, marginBottom: 16 }}>{children}</div>
    );
}

export default function AbsensiKebijakan({
    policy,
    attestationEnabled,
}: PageProps) {
    const form = useForm<Policy>({ ...policy });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(AttendancePolicyController.update.url(), {
            preserveScroll: true,
            onSuccess: () => toast.success('Kebijakan absensi tersimpan'),
        });
    };

    return (
        <>
            <Head title="Kebijakan Absensi" />

            <div style={{ padding: '28px 32px' }}>
                <div style={{ marginBottom: 22 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 12.5,
                            color: C.faint,
                            marginBottom: 7,
                        }}
                    >
                        <span>Beranda</span>
                        <AIcon name="chevron-right" size={13} />
                        <span>Kehadiran</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>
                            Kebijakan Absensi
                        </span>
                    </div>
                    <h1
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                            letterSpacing: '-.01em',
                        }}
                    >
                        Kebijakan Absensi
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Atur verifikasi wajah dan keamanan perangkat saat
                        karyawan clock-in di aplikasi.
                    </div>
                </div>

                <form onSubmit={submit}>
                    <Section>
                        <div style={sectionTitle}>Area Absensi</div>
                        <div style={sectionHint}>
                            Default untuk semua karyawan. Bisa ditimpa per
                            karyawan di halaman Karyawan.
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                                marginTop: 10,
                            }}
                        >
                            {SCOPE_OPTIONS.map((option) => (
                                <label
                                    key={option.value}
                                    style={{
                                        display: 'flex',
                                        gap: 10,
                                        alignItems: 'flex-start',
                                        cursor: 'pointer',
                                        padding: '10px 12px',
                                        borderRadius: 10,
                                        border: `1px solid ${
                                            form.data.attendance_scope ===
                                            option.value
                                                ? C.primary
                                                : C.border
                                        }`,
                                        background:
                                            form.data.attendance_scope ===
                                            option.value
                                                ? 'rgba(37,71,249,.04)'
                                                : 'transparent',
                                    }}
                                >
                                    <input
                                        type="radio"
                                        name="attendance_scope"
                                        checked={
                                            form.data.attendance_scope ===
                                            option.value
                                        }
                                        onChange={() =>
                                            form.setData(
                                                'attendance_scope',
                                                option.value,
                                            )
                                        }
                                        style={{ marginTop: 3 }}
                                    />
                                    <span>
                                        <span
                                            style={{
                                                display: 'block',
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                                color: C.text,
                                            }}
                                        >
                                            {option.label}
                                        </span>
                                        <span
                                            style={{
                                                display: 'block',
                                                fontSize: 12,
                                                color: C.muted,
                                            }}
                                        >
                                            {option.hint}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </Section>

                    <Section>
                        <div style={sectionTitle}>Verifikasi Wajah</div>
                        <div style={sectionHint}>
                            Cocokkan wajah karyawan dengan data terdaftar saat
                            absen.
                        </div>

                        <Toggle
                            on={form.data.require_face_enrollment}
                            onChange={(v) =>
                                form.setData('require_face_enrollment', v)
                            }
                            label="Wajib daftar wajah"
                            hint="Karyawan tidak bisa absen sebelum mendaftarkan wajah."
                        />

                        <div style={{ marginTop: 8 }}>
                            <span
                                style={{
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: C.text,
                                }}
                            >
                                Saat wajah tidak cocok
                            </span>
                            <EnforcementChoice
                                value={form.data.face_enforcement}
                                onChange={(v) =>
                                    form.setData('face_enforcement', v)
                                }
                            />
                        </div>
                    </Section>

                    <Section>
                        <div style={sectionTitle}>Integritas Perangkat</div>
                        <div style={sectionHint}>
                            Tutup celah Fake GPS, root/jailbreak, dan emulator.
                        </div>

                        <span
                            style={{
                                fontSize: 13,
                                fontWeight: 600,
                                color: C.text,
                            }}
                        >
                            Saat perangkat tidak tepercaya
                        </span>
                        <EnforcementChoice
                            value={form.data.integrity_enforcement}
                            onChange={(v) =>
                                form.setData('integrity_enforcement', v)
                            }
                        />

                        <div
                            style={{
                                marginTop: 12,
                                borderTop: `1px solid ${C.border}`,
                                paddingTop: 4,
                            }}
                        >
                            <Toggle
                                on={form.data.block_mock_location}
                                onChange={(v) =>
                                    form.setData('block_mock_location', v)
                                }
                                label="Deteksi lokasi palsu (Fake GPS)"
                            />
                            <Toggle
                                on={form.data.block_rooted}
                                onChange={(v) =>
                                    form.setData('block_rooted', v)
                                }
                                label="Deteksi root / jailbreak"
                            />
                            <Toggle
                                on={form.data.block_emulator}
                                onChange={(v) =>
                                    form.setData('block_emulator', v)
                                }
                                label="Deteksi emulator"
                            />
                        </div>

                        <div
                            style={{
                                marginTop: 12,
                                padding: '10px 12px',
                                borderRadius: 8,
                                background: attestationEnabled
                                    ? '#ECFDF5'
                                    : '#F8FAFC',
                                fontSize: 12.5,
                                color: C.muted,
                                display: 'flex',
                                gap: 8,
                            }}
                        >
                            <AIcon
                                name={
                                    attestationEnabled ? 'shield-check' : 'info'
                                }
                                size={16}
                            />
                            <span>
                                {attestationEnabled
                                    ? 'Play Integrity / App Attest aktif — token perangkat diverifikasi ke server penyedia.'
                                    : 'Verifikasi kriptografis (Play Integrity / App Attest) belum diaktifkan. Deteksi di atas memakai sinyal dari aplikasi. Aktifkan kredensial penyedia untuk verifikasi penuh.'}
                            </span>
                        </div>
                    </Section>

                    <Section>
                        <div style={sectionTitle}>Anti-Replay</div>
                        <div style={sectionHint}>
                            Cegah pengiriman ulang data absensi yang sudah
                            direkam.
                        </div>

                        <Toggle
                            on={form.data.require_liveness_challenge}
                            onChange={(v) =>
                                form.setData('require_liveness_challenge', v)
                            }
                            label="Wajib tantangan liveness (nonce sekali pakai)"
                            hint="Aplikasi harus meminta kode sesi baru untuk setiap absen."
                        />
                    </Section>

                    <button
                        type="submit"
                        style={{ ...btnP, opacity: form.processing ? 0.6 : 1 }}
                        disabled={form.processing}
                    >
                        <AIcon name="save" size={16} />
                        Simpan Kebijakan
                    </button>
                </form>
            </div>
        </>
    );
}
