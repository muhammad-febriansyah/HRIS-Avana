import { Head, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { AIcon, btnP, C, card } from '@/lib/avana';
import {
    Field,
    formatDate,
    inputStyle,
    PageHeader,
    PageShell,
    Panel,
    selectStyle,
    textareaStyle,
    withError,
} from './components';

interface Profile {
    id: number;
    employee_no: string | null;
    full_name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    nik: string | null;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    religion: string | null;
    marital_status: string | null;
    status: string | null;
    join_date: string | null;
    photo_url: string | null;
    employment: {
        company: string | null;
        branch: string | null;
        department: string | null;
        position: string | null;
        job_grade: string | null;
        employment_type: string | null;
    };
}

type FlashProps = { flash?: { success?: string } };

const GENDERS = [
    { value: '', label: '— Pilih —' },
    { value: 'male', label: 'Laki-laki' },
    { value: 'female', label: 'Perempuan' },
    { value: 'unspecified', label: 'Tidak disebutkan' },
];

export default function SayaProfil({ profile }: { profile: Profile }) {
    const { flash } = usePage<FlashProps>().props;
    const photoInput = useRef<HTMLInputElement>(null);

    const form = useForm({
        phone: profile.phone ?? '',
        address: profile.address ?? '',
        email: profile.email ?? '',
        nik: profile.nik ?? '',
        gender: profile.gender ?? '',
        birth_place: profile.birth_place ?? '',
        birth_date: profile.birth_date ?? '',
        religion: profile.religion ?? '',
        marital_status: profile.marital_status ?? '',
    });

    const photoForm = useForm<{ photo: File | null }>({ photo: null });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/avana/saya/profil', { preserveScroll: true });
    };

    const uploadPhoto = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        photoForm.setData('photo', file);
        photoForm.post('/avana/saya/profil/foto', {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                if (photoInput.current) {
                    photoInput.current.value = '';
                }
            },
        });
    };

    return (
        <>
            <Head title="Profil Saya" />
            <PageShell>
                <PageHeader
                    title="Profil Saya"
                    subtitle="Data pribadi yang bisa kamu perbarui sendiri. Data jabatan dikelola HR."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 2fr)',
                        gap: 16,
                        alignItems: 'start',
                    }}
                >
                    {/* Identity card (read-only) */}
                    <div style={{ ...card, padding: '24px 22px' }}>
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            {profile.photo_url ? (
                                <img
                                    src={profile.photo_url}
                                    alt={profile.full_name}
                                    style={{
                                        width: 92,
                                        height: 92,
                                        borderRadius: '50%',
                                        objectFit: 'cover',
                                    }}
                                />
                            ) : (
                                <div
                                    style={{
                                        width: 92,
                                        height: 92,
                                        borderRadius: '50%',
                                        background: `${C.primary}1a`,
                                        color: C.primary,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: 30,
                                        fontWeight: 700,
                                    }}
                                >
                                    {profile.full_name
                                        .split(' ')
                                        .slice(0, 2)
                                        .map((part) => part.charAt(0))
                                        .join('')
                                        .toUpperCase()}
                                </div>
                            )}
                            <div style={{ textAlign: 'center' }}>
                                <div
                                    style={{
                                        fontSize: 16,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    {profile.full_name}
                                </div>
                                <div
                                    style={{ fontSize: 12.5, color: C.muted }}
                                >
                                    {profile.employment.position ?? '—'}
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => photoInput.current?.click()}
                                disabled={photoForm.processing}
                                style={{
                                    background: 'none',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    padding: '7px 13px',
                                    fontSize: 12.5,
                                    color: C.text,
                                    cursor: 'pointer',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                }}
                            >
                                <AIcon name="camera" size={14} color={C.text} />
                                {photoForm.processing
                                    ? 'Mengunggah…'
                                    : 'Ganti Foto'}
                            </button>
                            <input
                                ref={photoInput}
                                type="file"
                                accept="image/*"
                                onChange={uploadPhoto}
                                style={{ display: 'none' }}
                            />
                        </div>

                        <div
                            style={{
                                marginTop: 20,
                                paddingTop: 18,
                                borderTop: `1px solid ${C.line}`,
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 12,
                            }}
                        >
                            <ReadOnlyRow
                                label="NIP"
                                value={profile.employee_no}
                            />
                            <ReadOnlyRow
                                label="Perusahaan"
                                value={profile.employment.company}
                            />
                            <ReadOnlyRow
                                label="Cabang"
                                value={profile.employment.branch}
                            />
                            <ReadOnlyRow
                                label="Departemen"
                                value={profile.employment.department}
                            />
                            <ReadOnlyRow
                                label="Level"
                                value={profile.employment.job_grade}
                            />
                            <ReadOnlyRow
                                label="Status Kerja"
                                value={titleCase(
                                    profile.employment.employment_type,
                                )}
                            />
                            <ReadOnlyRow
                                label="Tanggal Masuk"
                                value={
                                    profile.join_date
                                        ? formatDate(profile.join_date)
                                        : null
                                }
                            />
                        </div>
                    </div>

                    {/* Editable personal data */}
                    <form onSubmit={submit}>
                        <Panel
                            title="Data Pribadi"
                            subtitle="Perubahan tersimpan langsung ke data karyawanmu."
                        >
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 16,
                                }}
                            >
                                <Field
                                    label="Email"
                                    error={form.errors.email}
                                >
                                    <input
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.email,
                                        )}
                                    />
                                </Field>
                                <Field
                                    label="No. Telepon"
                                    error={form.errors.phone}
                                >
                                    <input
                                        value={form.data.phone}
                                        onChange={(event) =>
                                            form.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="08xxxxxxxxxx"
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.phone,
                                        )}
                                    />
                                </Field>
                                <Field label="NIK" error={form.errors.nik}>
                                    <input
                                        value={form.data.nik}
                                        onChange={(event) =>
                                            form.setData(
                                                'nik',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="16 digit"
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.nik,
                                        )}
                                    />
                                </Field>
                                <Field
                                    label="Jenis Kelamin"
                                    error={form.errors.gender}
                                >
                                    <select
                                        value={form.data.gender}
                                        onChange={(event) =>
                                            form.setData(
                                                'gender',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            selectStyle,
                                            !!form.errors.gender,
                                        )}
                                    >
                                        {GENDERS.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Tempat Lahir"
                                    error={form.errors.birth_place}
                                >
                                    <input
                                        value={form.data.birth_place}
                                        onChange={(event) =>
                                            form.setData(
                                                'birth_place',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.birth_place,
                                        )}
                                    />
                                </Field>
                                <Field
                                    label="Tanggal Lahir"
                                    error={form.errors.birth_date}
                                >
                                    <input
                                        type="date"
                                        value={form.data.birth_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'birth_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.birth_date,
                                        )}
                                    />
                                </Field>
                                <Field
                                    label="Agama"
                                    error={form.errors.religion}
                                >
                                    <input
                                        value={form.data.religion}
                                        onChange={(event) =>
                                            form.setData(
                                                'religion',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.religion,
                                        )}
                                    />
                                </Field>
                                <Field
                                    label="Status Pernikahan"
                                    error={form.errors.marital_status}
                                >
                                    <input
                                        value={form.data.marital_status}
                                        onChange={(event) =>
                                            form.setData(
                                                'marital_status',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.marital_status,
                                        )}
                                    />
                                </Field>
                            </div>

                            <div style={{ marginTop: 16 }}>
                                <Field
                                    label="Alamat"
                                    error={form.errors.address}
                                >
                                    <textarea
                                        value={form.data.address}
                                        onChange={(event) =>
                                            form.setData(
                                                'address',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            textareaStyle,
                                            !!form.errors.address,
                                        )}
                                    />
                                </Field>
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'flex-end',
                                    marginTop: 18,
                                }}
                            >
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    style={{
                                        ...btnP,
                                        height: 44,
                                        opacity: form.processing ? 0.7 : 1,
                                    }}
                                >
                                    <AIcon
                                        name="check"
                                        size={16}
                                        color="#fff"
                                    />
                                    Simpan Perubahan
                                </button>
                            </div>
                        </Panel>
                    </form>
                </div>
            </PageShell>
        </>
    );
}

/** Present a raw enum value ("contract") as a label ("Contract"). */
function titleCase(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return value
        .split(/[\s_-]+/)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function ReadOnlyRow({
    label,
    value,
}: {
    label: string;
    value: string | null;
}) {
    return (
        <div
            style={{
                display: 'flex',
                justifyContent: 'space-between',
                gap: 12,
                fontSize: 12.5,
            }}
        >
            <span style={{ color: C.faint }}>{label}</span>
            <span
                style={{
                    color: C.text,
                    fontWeight: 500,
                    textAlign: 'right',
                }}
            >
                {value ?? '—'}
            </span>
        </div>
    );
}
