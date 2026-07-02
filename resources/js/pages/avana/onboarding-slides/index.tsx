import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import OnboardingSlideController from '@/actions/App/Http/Controllers/Avana/OnboardingSlideController';
import { AIcon, ActionBtn, btnOut, btnP, C, card } from '@/lib/avana';
import {
    Field,
    ImageUpload,
    inputStyle,
    withError,
} from '../website-settings/components';

/* ============================================================
 * Onboarding slides: the mobile app intro carousel (super admin).
 * ============================================================ */

interface Slide {
    id: number;
    title: string;
    subtitle: string | null;
    image_url: string | null;
    sort_order: number;
    is_active: boolean;
}

interface OnboardingSlidesProps {
    slides: Slide[];
}

type FlashProps = { flash?: { success?: string } };

interface SlideFormData {
    title: string;
    subtitle: string;
    sort_order: number;
    is_active: boolean;
    image: File | null;
    remove_image: boolean;
}

const emptyForm = (order: number): SlideFormData => ({
    title: '',
    subtitle: '',
    sort_order: order,
    is_active: true,
    image: null,
    remove_image: false,
});

const labelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

export default function OnboardingSlides({ slides }: OnboardingSlidesProps) {
    const { flash } = usePage<FlashProps>().props;

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Slide | null>(null);
    const [confirm, setConfirm] = useState<Slide | null>(null);
    const [removedImage, setRemovedImage] = useState(false);

    const form = useForm<SlideFormData>(emptyForm(0));

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openCreate = () => {
        setEditing(null);
        setRemovedImage(false);
        form.clearErrors();
        form.setData(emptyForm(slides.length));
        setModalOpen(true);
    };

    const openEdit = (slide: Slide) => {
        setEditing(slide);
        setRemovedImage(false);
        form.clearErrors();
        form.setData({
            title: slide.title,
            subtitle: slide.subtitle ?? '',
            sort_order: slide.sort_order,
            is_active: slide.is_active,
            image: null,
            remove_image: false,
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        setRemovedImage(false);
        form.reset();
        form.clearErrors();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const url = editing
            ? OnboardingSlideController.update(editing.id).url
            : OnboardingSlideController.store().url;
        form.post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    const remove = () => {
        if (!confirm) {
            return;
        }

        router.delete(OnboardingSlideController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const currentImage = editing && !removedImage ? editing.image_url : null;

    return (
        <>
            <Head title="Onboarding App" />
            <div style={{ padding: '28px 32px', maxWidth: 900 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
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
                            <span>Platform</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Onboarding App
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
                            Onboarding App
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Slide intro yang tampil di aplikasi mobile karyawan
                            sebelum login.
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Slide
                    </button>
                </div>

                {slides.length === 0 && (
                    <div
                        style={{
                            ...card,
                            padding: '48px 18px',
                            textAlign: 'center',
                            color: C.muted,
                            fontSize: 13.5,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: 10,
                            }}
                        >
                            <AIcon
                                name="smartphone"
                                size={28}
                                color={C.faint}
                            />
                            <div>Belum ada slide onboarding.</div>
                        </div>
                    </div>
                )}

                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 14,
                    }}
                >
                    {slides.map((slide) => (
                        <div
                            key={slide.id}
                            style={{
                                ...card,
                                padding: 16,
                                display: 'flex',
                                gap: 16,
                                alignItems: 'center',
                                flexWrap: 'wrap',
                            }}
                        >
                            <div
                                style={{
                                    width: 96,
                                    height: 96,
                                    flex: 'none',
                                    borderRadius: 12,
                                    border: `1px solid ${C.border}`,
                                    background: C.surface,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    overflow: 'hidden',
                                }}
                            >
                                {slide.image_url ? (
                                    <img
                                        src={slide.image_url}
                                        alt={slide.title}
                                        style={{
                                            maxWidth: '100%',
                                            maxHeight: '100%',
                                            objectFit: 'contain',
                                        }}
                                    />
                                ) : (
                                    <AIcon
                                        name="image"
                                        size={24}
                                        color={C.faint}
                                    />
                                )}
                            </div>
                            <div style={{ flex: '1 1 260px', minWidth: 0 }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        marginBottom: 4,
                                    }}
                                >
                                    <span
                                        style={{
                                            display: 'inline-flex',
                                            width: 24,
                                            height: 24,
                                            borderRadius: 7,
                                            background: 'rgba(47,84,201,.1)',
                                            color: C.primary,
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: 12,
                                            fontWeight: 700,
                                        }}
                                    >
                                        {slide.sort_order + 1}
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 16,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {slide.title}
                                    </span>
                                    <span
                                        style={{
                                            display: 'inline-block',
                                            padding: '2px 10px',
                                            borderRadius: 100,
                                            fontSize: 11.5,
                                            fontWeight: 600,
                                            color: slide.is_active
                                                ? C.green
                                                : C.muted,
                                            background: slide.is_active
                                                ? 'rgba(22,163,74,.1)'
                                                : 'rgba(107,114,128,.12)',
                                        }}
                                    >
                                        {slide.is_active ? 'Aktif' : 'Nonaktif'}
                                    </span>
                                </div>
                                {slide.subtitle && (
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            color: C.muted,
                                            lineHeight: 1.5,
                                        }}
                                    >
                                        {slide.subtitle}
                                    </div>
                                )}
                            </div>
                            <div
                                style={{
                                    display: 'inline-flex',
                                    gap: 6,
                                    flexWrap: 'wrap',
                                }}
                            >
                                <ActionBtn
                                    icon="pencil"
                                    label="Ubah"
                                    variant="primary"
                                    onClick={() => openEdit(slide)}
                                />
                                <ActionBtn
                                    icon="trash-2"
                                    label="Hapus"
                                    variant="danger"
                                    onClick={() => setConfirm(slide)}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {modalOpen && (
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
                        onClick={closeModal}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submit}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 560,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            {editing ? 'Ubah Slide' : 'Tambah Slide'}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Gunakan gambar SVG (vektor) atau PNG/WEBP agar tajam
                            di semua layar.
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            <Field label="Judul" error={form.errors.title}>
                                <input
                                    type="text"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    placeholder="Absensi Praktis"
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.title,
                                    )}
                                />
                            </Field>
                            <Field
                                label="Subjudul"
                                error={form.errors.subtitle}
                            >
                                <input
                                    type="text"
                                    value={form.data.subtitle}
                                    onChange={(e) =>
                                        form.setData('subtitle', e.target.value)
                                    }
                                    placeholder="Clock-in & clock-out langsung dari ponsel."
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.subtitle,
                                    )}
                                />
                            </Field>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '120px 1fr',
                                    gap: 16,
                                    alignItems: 'center',
                                }}
                            >
                                <Field
                                    label="Urutan"
                                    error={form.errors.sort_order}
                                >
                                    <input
                                        type="number"
                                        min={0}
                                        value={form.data.sort_order}
                                        onChange={(e) =>
                                            form.setData(
                                                'sort_order',
                                                Number(e.target.value),
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.sort_order,
                                        )}
                                    />
                                </Field>
                                <label
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 9,
                                        cursor: 'pointer',
                                        fontSize: 13.5,
                                        color: C.text,
                                        marginTop: 18,
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_active}
                                        onChange={(e) =>
                                            form.setData(
                                                'is_active',
                                                e.target.checked,
                                            )
                                        }
                                        style={{
                                            width: 16,
                                            height: 16,
                                            cursor: 'pointer',
                                        }}
                                    />
                                    Tampilkan slide ini
                                </label>
                            </div>
                            <ImageUpload
                                label="Gambar / Ilustrasi"
                                hint="SVG, PNG, JPG atau WEBP · maks 2 MB"
                                accept="image/svg+xml,image/png,image/jpeg,image/webp"
                                file={form.data.image}
                                currentUrl={currentImage}
                                error={form.errors.image}
                                onPick={(file) => {
                                    form.setData('image', file);
                                    form.setData('remove_image', false);
                                    setRemovedImage(false);
                                }}
                                onClear={() => {
                                    form.setData('image', null);
                                    const hadServer = !!(
                                        editing && editing.image_url
                                    );
                                    form.setData('remove_image', hadServer);
                                    setRemovedImage(hadServer);
                                }}
                            />
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeModal}
                                style={{
                                    ...btnOut,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: form.processing ? 0.7 : 1,
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {confirm && (
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
                        onClick={() => setConfirm(null)}
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
                        }}
                    >
                        <div
                            style={{
                                width: 48,
                                height: 48,
                                borderRadius: 12,
                                background: 'rgba(220,38,38,.1)',
                                color: C.red,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginBottom: 16,
                            }}
                        >
                            <AIcon name="trash-2" size={22} color={C.red} />
                        </div>
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Hapus slide?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Slide{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.title}
                            </strong>{' '}
                            akan dihapus permanen.
                        </div>
                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                onClick={() => setConfirm(null)}
                                style={{
                                    ...btnOut,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                onClick={remove}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: C.red,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                }}
                            >
                                <AIcon name="trash-2" size={16} />
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
