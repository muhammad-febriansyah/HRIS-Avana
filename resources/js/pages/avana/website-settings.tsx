import { Head, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent, CSSProperties, DragEvent, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import WebsiteSettingController from '@/actions/App/Http/Controllers/Avana/WebsiteSettingController';
import { AIcon, btnP, C, card } from '@/lib/avana';
import type { FlashProps } from './employees/types';

/** Settings serialized by `WebsiteSettingController::edit`. */
interface Settings {
    site_name: string | null;
    tagline: string | null;
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
    social_facebook: string | null;
    social_instagram: string | null;
    social_twitter: string | null;
    social_youtube: string | null;
    social_linkedin: string | null;
    social_tiktok: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    contact_whatsapp: string | null;
    contact_address: string | null;
    logo_url: string | null;
    favicon_url: string | null;
    og_image_url: string | null;
}

interface PageProps {
    settings: Settings;
}

type ImageField = 'logo' | 'favicon' | 'og_image';

interface FormData {
    site_name: string;
    tagline: string;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    social_facebook: string;
    social_instagram: string;
    social_twitter: string;
    social_youtube: string;
    social_linkedin: string;
    social_tiktok: string;
    contact_email: string;
    contact_phone: string;
    contact_whatsapp: string;
    contact_address: string;
    logo: File | null;
    favicon: File | null;
    og_image: File | null;
    remove_logo: boolean;
    remove_favicon: boolean;
    remove_og_image: boolean;
}

const TABS: { id: string; label: string; icon: string }[] = [
    { id: 'umum', label: 'Umum', icon: 'settings' },
    { id: 'brand', label: 'Brand & Logo', icon: 'image' },
    { id: 'seo', label: 'SEO', icon: 'search' },
    { id: 'sosial', label: 'Sosial Media', icon: 'share-2' },
    { id: 'kontak', label: 'Kontak', icon: 'phone' },
];

const labelStyle: CSSProperties = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7, color: C.text };

const inputStyle: CSSProperties = {
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

const textareaStyle: CSSProperties = { ...inputStyle, height: 'auto', padding: '11px 13px', minHeight: 90, resize: 'vertical', lineHeight: 1.5 };

function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError ? { ...base, border: `1px solid ${C.red}`, boxShadow: '0 0 0 3px rgba(220,38,38,.08)' } : base;
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={{ fontSize: 12, color: C.red, marginTop: 6, display: 'flex', alignItems: 'center', gap: 5 }}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

interface FieldProps {
    label: string;
    children: React.ReactNode;
    error?: string;
    hint?: string;
    full?: boolean;
}

function Field({ label, children, error, hint, full }: FieldProps) {
    return (
        <div style={{ gridColumn: full ? '1 / -1' : 'auto' }}>
            <label style={labelStyle}>{label}</label>
            {children}
            {hint && !error && <div style={{ fontSize: 12, color: C.faint, marginTop: 6 }}>{hint}</div>}
            <FieldError message={error} />
        </div>
    );
}

interface ImageUploadProps {
    label: string;
    hint: string;
    accept: string;
    file: File | null;
    currentUrl: string | null;
    error?: string;
    square?: boolean;
    onPick: (file: File) => void;
    onClear: () => void;
}

/** Drag-and-drop image picker with live preview; old image is freed server-side. */
function ImageUpload({ label, hint, accept, file, currentUrl, error, square, onPick, onClear }: ImageUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);

    useEffect(() => {
        if (!file) {
            setPreview(null);

            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const shown = preview ?? currentUrl;
    const previewBox = square ? 92 : 132;

    const handleFiles = (files: FileList | null) => {
        if (files && files[0]) {
            onPick(files[0]);
        }
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        handleFiles(event.dataTransfer.files);
    };

    return (
        <div>
            <label style={labelStyle}>{label}</label>
            <div style={{ display: 'flex', gap: 16, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                {/* Preview */}
                <div
                    style={{
                        width: square ? previewBox : 180,
                        height: previewBox,
                        flex: 'none',
                        borderRadius: 10,
                        border: `1px solid ${C.border}`,
                        background:
                            'repeating-conic-gradient(#F1F3F9 0% 25%, #fff 0% 50%) 50% / 18px 18px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        overflow: 'hidden',
                        position: 'relative',
                    }}
                >
                    {shown ? (
                        <img src={shown} alt={label} style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} />
                    ) : (
                        <AIcon name="image" size={26} color={C.faint} />
                    )}
                </div>

                {/* Dropzone + actions */}
                <div style={{ flex: 1, minWidth: 220 }}>
                    <div
                        onClick={() => inputRef.current?.click()}
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={onDrop}
                        style={{
                            border: `1.5px dashed ${dragging ? C.primary : C.border}`,
                            background: dragging ? 'rgba(47,84,201,.04)' : C.surface,
                            borderRadius: 10,
                            padding: '18px 16px',
                            textAlign: 'center',
                            cursor: 'pointer',
                            transition: '.15s',
                        }}
                    >
                        <AIcon name="upload-cloud" size={22} color={C.primary} style={{ margin: '0 auto 6px' }} />
                        <div style={{ fontSize: 13, color: C.text, fontWeight: 500 }}>
                            Tarik gambar ke sini atau <span style={{ color: C.primary }}>pilih file</span>
                        </div>
                        <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>{hint}</div>
                    </div>
                    <input
                        ref={inputRef}
                        type="file"
                        accept={accept}
                        onChange={(event: ChangeEvent<HTMLInputElement>) => {
                            handleFiles(event.target.files);
                            event.target.value = '';
                        }}
                        style={{ display: 'none' }}
                    />
                    {(shown || file) && (
                        <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    height: 34,
                                    padding: '0 12px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 12.5,
                                    fontWeight: 500,
                                    color: C.text,
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon name="repeat" size={14} />
                                Ganti
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    onClear();
                                    if (inputRef.current) {
                                        inputRef.current.value = '';
                                    }
                                }}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    height: 34,
                                    padding: '0 12px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 12.5,
                                    fontWeight: 500,
                                    color: C.red,
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon name="trash-2" size={14} color={C.red} />
                                Hapus
                            </button>
                        </div>
                    )}
                </div>
            </div>
            <FieldError message={error} />
        </div>
    );
}

export default function WebsiteSettings({ settings }: PageProps) {
    const { flash } = usePage<FlashProps>().props;
    const [tab, setTab] = useState('umum');
    const [removed, setRemoved] = useState<Record<ImageField, boolean>>({ logo: false, favicon: false, og_image: false });

    const form = useForm<FormData>({
        site_name: settings.site_name ?? '',
        tagline: settings.tagline ?? '',
        meta_title: settings.meta_title ?? '',
        meta_description: settings.meta_description ?? '',
        meta_keywords: settings.meta_keywords ?? '',
        social_facebook: settings.social_facebook ?? '',
        social_instagram: settings.social_instagram ?? '',
        social_twitter: settings.social_twitter ?? '',
        social_youtube: settings.social_youtube ?? '',
        social_linkedin: settings.social_linkedin ?? '',
        social_tiktok: settings.social_tiktok ?? '',
        contact_email: settings.contact_email ?? '',
        contact_phone: settings.contact_phone ?? '',
        contact_whatsapp: settings.contact_whatsapp ?? '',
        contact_address: settings.contact_address ?? '',
        logo: null,
        favicon: null,
        og_image: null,
        remove_logo: false,
        remove_favicon: false,
        remove_og_image: false,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const pickImage = (field: ImageField, file: File) => {
        form.setData(field, file);
        form.setData(`remove_${field}` as keyof FormData, false as never);
        setRemoved((prev) => ({ ...prev, [field]: false }));
    };

    const clearImage = (field: ImageField, hadServerImage: boolean) => {
        form.setData(field, null);
        form.setData(`remove_${field}` as keyof FormData, hadServerImage as never);
        setRemoved((prev) => ({ ...prev, [field]: hadServerImage }));
    };

    const currentUrl = (field: ImageField, url: string | null) => (removed[field] ? null : url);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(WebsiteSettingController.update().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('logo', 'favicon', 'og_image', 'remove_logo', 'remove_favicon', 'remove_og_image');
                setRemoved({ logo: false, favicon: false, og_image: false });
            },
        });
    };

    const text = (key: keyof FormData, props: { placeholder?: string; type?: string } = {}) => (
        <input
            value={form.data[key] as string}
            onChange={(event) => form.setData(key, event.target.value as never)}
            type={props.type ?? 'text'}
            placeholder={props.placeholder}
            style={withError(inputStyle, !!form.errors[key])}
        />
    );

    return (
        <>
            <Head title="Pengaturan Website" />
            <form onSubmit={submit} style={{ padding: '28px 32px', maxWidth: 980 }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                        <span>Platform</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Pengaturan Website</span>
                    </div>
                    <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Pengaturan Website</h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Logo, favicon, SEO, sosial media, dan informasi kontak situs publik</div>
                </div>

                {/* Tabs */}
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 18 }}>
                    {TABS.map((item) => {
                        const active = tab === item.id;

                        return (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => setTab(item.id)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    height: 40,
                                    padding: '0 16px',
                                    borderRadius: 9,
                                    border: `1px solid ${active ? 'transparent' : C.border}`,
                                    background: active ? C.primary : '#fff',
                                    color: active ? '#fff' : C.text,
                                    fontSize: 13.5,
                                    fontWeight: active ? 600 : 500,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name={item.icon} size={16} color={active ? '#fff' : C.muted} />
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {/* Panels */}
                <div style={{ ...card, padding: '24px 26px' }}>
                    {tab === 'umum' && (
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
                            <Field label="Nama Situs" error={form.errors.site_name}>{text('site_name', { placeholder: 'AvanaHR' })}</Field>
                            <Field label="Tagline" error={form.errors.tagline}>{text('tagline', { placeholder: 'HR modern untuk bisnis Indonesia' })}</Field>
                        </div>
                    )}

                    {tab === 'brand' && (
                        <div style={{ display: 'grid', gap: 24 }}>
                            <ImageUpload
                                label="Logo"
                                hint="PNG, JPG, WEBP atau SVG · maks 2 MB"
                                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                file={form.data.logo}
                                currentUrl={currentUrl('logo', settings.logo_url)}
                                error={form.errors.logo}
                                onPick={(file) => pickImage('logo', file)}
                                onClear={() => clearImage('logo', !!settings.logo_url)}
                            />
                            <ImageUpload
                                label="Favicon"
                                hint="ICO, PNG atau SVG · maks 1 MB · disarankan 512×512"
                                accept="image/png,image/svg+xml,image/x-icon,.ico"
                                file={form.data.favicon}
                                currentUrl={currentUrl('favicon', settings.favicon_url)}
                                error={form.errors.favicon}
                                square
                                onPick={(file) => pickImage('favicon', file)}
                                onClear={() => clearImage('favicon', !!settings.favicon_url)}
                            />
                            <ImageUpload
                                label="Gambar Share (OG Image)"
                                hint="PNG, JPG atau WEBP · maks 2 MB · disarankan 1200×630"
                                accept="image/png,image/jpeg,image/webp"
                                file={form.data.og_image}
                                currentUrl={currentUrl('og_image', settings.og_image_url)}
                                error={form.errors.og_image}
                                onPick={(file) => pickImage('og_image', file)}
                                onClear={() => clearImage('og_image', !!settings.og_image_url)}
                            />
                        </div>
                    )}

                    {tab === 'seo' && (
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
                            <Field label="Meta Title" error={form.errors.meta_title} full>{text('meta_title', { placeholder: 'AvanaHR — Sistem HR & Payroll' })}</Field>
                            <Field label="Meta Description" hint="Disarankan 150–160 karakter" error={form.errors.meta_description} full>
                                <textarea
                                    value={form.data.meta_description}
                                    onChange={(event) => form.setData('meta_description', event.target.value)}
                                    placeholder="Deskripsi singkat situs untuk hasil pencarian Google"
                                    style={withError(textareaStyle, !!form.errors.meta_description)}
                                />
                            </Field>
                            <Field label="Meta Keywords" hint="Pisahkan dengan koma" error={form.errors.meta_keywords} full>
                                <textarea
                                    value={form.data.meta_keywords}
                                    onChange={(event) => form.setData('meta_keywords', event.target.value)}
                                    placeholder="hris, payroll, absensi, cuti, karyawan"
                                    style={withError(textareaStyle, !!form.errors.meta_keywords)}
                                />
                            </Field>
                        </div>
                    )}

                    {tab === 'sosial' && (
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
                            <Field label="Facebook" error={form.errors.social_facebook}>{text('social_facebook', { placeholder: 'https://facebook.com/...' })}</Field>
                            <Field label="Instagram" error={form.errors.social_instagram}>{text('social_instagram', { placeholder: 'https://instagram.com/...' })}</Field>
                            <Field label="X / Twitter" error={form.errors.social_twitter}>{text('social_twitter', { placeholder: 'https://x.com/...' })}</Field>
                            <Field label="YouTube" error={form.errors.social_youtube}>{text('social_youtube', { placeholder: 'https://youtube.com/@...' })}</Field>
                            <Field label="LinkedIn" error={form.errors.social_linkedin}>{text('social_linkedin', { placeholder: 'https://linkedin.com/company/...' })}</Field>
                            <Field label="TikTok" error={form.errors.social_tiktok}>{text('social_tiktok', { placeholder: 'https://tiktok.com/@...' })}</Field>
                        </div>
                    )}

                    {tab === 'kontak' && (
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
                            <Field label="Email" error={form.errors.contact_email}>{text('contact_email', { placeholder: 'halo@avanahr.co.id', type: 'email' })}</Field>
                            <Field label="Telepon" error={form.errors.contact_phone}>{text('contact_phone', { placeholder: '(021) 5099-9000' })}</Field>
                            <Field label="WhatsApp" error={form.errors.contact_whatsapp}>{text('contact_whatsapp', { placeholder: '+62 812-0000-0000' })}</Field>
                            <Field label="Alamat" error={form.errors.contact_address} full>
                                <textarea
                                    value={form.data.contact_address}
                                    onChange={(event) => form.setData('contact_address', event.target.value)}
                                    placeholder="Jl. Jenderal Sudirman No. 1, Jakarta"
                                    style={withError(textareaStyle, !!form.errors.contact_address)}
                                />
                            </Field>
                        </div>
                    )}
                </div>

                {/* Save bar */}
                <div
                    style={{
                        position: 'sticky',
                        bottom: 0,
                        marginTop: 18,
                        padding: '14px 0',
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 10,
                    }}
                >
                    <button type="submit" disabled={form.processing} style={{ ...btnP, height: 44, padding: '0 22px', opacity: form.processing ? 0.7 : 1, cursor: form.processing ? 'not-allowed' : 'pointer' }}>
                        <AIcon name="check" size={16} color="#fff" />
                        {form.processing ? 'Menyimpan…' : 'Simpan Pengaturan'}
                    </button>
                </div>
            </form>
        </>
    );
}
