import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import MenuBuilderController from '@/actions/App/Http/Controllers/Avana/MenuBuilderController';
import { AIcon, ActionBtn, btnOut, btnP, C, card } from '@/lib/avana';

interface MenuRow {
    id: number;
    key: string;
    parent_id: number | null;
    section: string | null;
    label: string;
    icon: string | null;
    href: string | null;
    feature: string | null;
    modules: string[];
    admin_only: boolean;
    super_admin_only: boolean;
    is_active: boolean;
    is_system: boolean;
    sort_order: number;
}

interface TreeRow extends MenuRow {
    children: MenuRow[];
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    tree: TreeRow[];
    parents: { id: number; label: string }[];
    sections: string[];
    features: Option[];
    modules: string[];
}

type FlashProps = { flash?: { success?: string } };

const emptyForm = {
    id: null as number | null,
    label: '',
    parent_id: '' as string,
    section: '',
    href: '',
    icon: '',
    feature: '',
    modules: '',
    admin_only: false,
    super_admin_only: false,
};

export default function MenuBuilder({ tree, parents, sections, features, modules }: Props) {
    const { flash } = usePage<FlashProps>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const form = useForm({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const moduleHint = useMemo(() => modules.join(', '), [modules]);

    const openAdd = () => {
        form.clearErrors();
        form.setData({ ...emptyForm });
        setModalOpen(true);
    };

    const openEdit = (row: MenuRow) => {
        form.clearErrors();
        form.setData({
            id: row.id,
            label: row.label,
            parent_id: row.parent_id ? String(row.parent_id) : '',
            section: row.section ?? '',
            href: row.href ?? '',
            icon: row.icon ?? '',
            feature: row.feature ?? '',
            modules: (row.modules ?? []).join(', '),
            admin_only: row.admin_only,
            super_admin_only: row.super_admin_only,
        });
        setModalOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                form.reset();
            },
        };
        if (form.data.id) {
            form.put(MenuBuilderController.update(form.data.id).url, opts);
        } else {
            form.post(MenuBuilderController.store().url, opts);
        }
    };

    const move = (row: MenuRow, direction: 'up' | 'down') =>
        router.post(MenuBuilderController.move(row.id).url, { direction }, { preserveScroll: true });
    const toggle = (row: MenuRow) =>
        router.post(MenuBuilderController.toggle(row.id).url, {}, { preserveScroll: true });
    const remove = (row: MenuRow) => {
        if (confirm(`Hapus menu "${row.label}"?`)) {
            router.delete(MenuBuilderController.destroy(row.id).url, { preserveScroll: true });
        }
    };

    const renderRow = (row: MenuRow, child = false) => (
        <div
            key={row.id}
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                padding: '10px 12px',
                marginLeft: child ? 26 : 0,
                borderRadius: 9,
                border: `1px solid ${C.border}`,
                background: row.is_active ? '#fff' : '#F8FAFC',
                opacity: row.is_active ? 1 : 0.6,
                marginBottom: 6,
            }}
        >
            {row.icon && <AIcon name={row.icon} size={15} color={C.muted} />}
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13.5, fontWeight: 600, color: C.navy }}>
                    {row.label}
                    {row.is_system && <Badge text="Bawaan" color={C.muted} />}
                    {!row.is_active && <Badge text="Disembunyikan" color={C.amber} />}
                    {row.super_admin_only && <Badge text="Super Admin" color={C.primary} />}
                    {row.admin_only && <Badge text="Admin" color={C.primary} />}
                </div>
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 2 }}>
                    {row.href || '— grup —'}
                    {row.modules.length > 0 ? ` · modul: ${row.modules.join(', ')}` : ''}
                    {row.feature ? ` · fitur: ${row.feature}` : ''}
                </div>
            </div>
            <div style={{ display: 'inline-flex', gap: 5 }}>
                <ActionBtn icon="chevron-up" label="" variant="neutral" onClick={() => move(row, 'up')} />
                <ActionBtn icon="chevron-down" label="" variant="neutral" onClick={() => move(row, 'down')} />
                <ActionBtn
                    icon={row.is_active ? 'eye-off' : 'eye'}
                    label={row.is_active ? 'Sembunyikan' : 'Tampilkan'}
                    variant={row.is_active ? 'warning' : 'success'}
                    onClick={() => toggle(row)}
                />
                <ActionBtn icon="pencil" label="Edit" variant="primary" onClick={() => openEdit(row)} />
                {!row.is_system && (
                    <ActionBtn icon="trash-2" label="Hapus" variant="danger" onClick={() => remove(row)} />
                )}
            </div>
        </div>
    );

    return (
        <>
            <Head title="Menu Builder" />
            <div style={{ padding: '22px 26px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16, marginBottom: 18 }}>
                    <div>
                        <h1 style={{ fontSize: 20, fontWeight: 700, color: C.navy, marginBottom: 4 }}>Menu Builder</h1>
                        <p style={{ fontSize: 13, color: C.faint }}>
                            Atur sidebar: tambah, ubah nama, ikon, urutan, sembunyikan, & hak akses menu.
                        </p>
                    </div>
                    <button onClick={openAdd} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Menu
                    </button>
                </div>

                {tree.map((group) => (
                    <div key={group.id} style={{ ...card, padding: 14, marginBottom: 12 }}>
                        {group.section && (
                            <div style={{ fontSize: 11, fontWeight: 700, color: C.faint, textTransform: 'uppercase', letterSpacing: '.05em', marginBottom: 8 }}>
                                {group.section}
                            </div>
                        )}
                        {renderRow(group)}
                        {group.children.map((child) => renderRow(child, true))}
                    </div>
                ))}
            </div>

            {modalOpen && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={() => setModalOpen(false)} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <div style={{ position: 'relative', width: '100%', maxWidth: 480, maxHeight: '90vh', overflowY: 'auto', background: '#fff', borderRadius: 14, padding: 24 }}>
                        <div style={{ fontSize: 17, fontWeight: 600, color: C.navy, marginBottom: 16 }}>
                            {form.data.id ? 'Ubah Menu' : 'Tambah Menu'}
                        </div>
                        <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            <Field label="Nama Menu *" error={form.errors.label}>
                                <input value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} style={inp} />
                            </Field>
                            <Field label="Induk (kosongkan untuk grup utama)">
                                <select value={form.data.parent_id} onChange={(e) => form.setData('parent_id', e.target.value)} style={inp}>
                                    <option value="">— Grup utama —</option>
                                    {parents.map((p) => (
                                        <option key={p.id} value={String(p.id)}>{p.label}</option>
                                    ))}
                                </select>
                            </Field>
                            {form.data.parent_id === '' && (
                                <Field label="Judul Grup (section)">
                                    <input list="sections" value={form.data.section} onChange={(e) => form.setData('section', e.target.value)} style={inp} placeholder="mis. MANAJEMEN" />
                                    <datalist id="sections">
                                        {sections.map((s) => <option key={s} value={s} />)}
                                    </datalist>
                                </Field>
                            )}
                            <Field label="URL (kosong = grup/pemisah)" error={form.errors.href}>
                                <input value={form.data.href} onChange={(e) => form.setData('href', e.target.value)} style={inp} placeholder="/avana/..." />
                            </Field>
                            <Field label="Ikon (nama lucide)">
                                <input value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} style={inp} placeholder="mis. users, wallet, star" />
                            </Field>
                            <Field label="Fitur (opsional)">
                                <select value={form.data.feature} onChange={(e) => form.setData('feature', e.target.value)} style={inp}>
                                    <option value="">— tanpa gate fitur —</option>
                                    {features.map((f) => (
                                        <option key={f.value} value={f.value}>{f.label}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Modul akses (pisah koma)">
                                <input value={form.data.modules} onChange={(e) => form.setData('modules', e.target.value)} style={inp} placeholder="mis. crm, report" />
                                <div style={{ fontSize: 10.5, color: C.faint, marginTop: 4, maxHeight: 46, overflow: 'auto' }}>Tersedia: {moduleHint}</div>
                            </Field>
                            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13, color: C.muted }}>
                                <input type="checkbox" checked={form.data.admin_only} onChange={(e) => form.setData('admin_only', e.target.checked)} />
                                Hanya admin/HR
                            </label>
                            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13, color: C.muted }}>
                                <input type="checkbox" checked={form.data.super_admin_only} onChange={(e) => form.setData('super_admin_only', e.target.checked)} />
                                Hanya super admin
                            </label>
                            <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
                                <button type="button" onClick={() => setModalOpen(false)} style={{ ...btnOut, flex: 1, justifyContent: 'center' }}>Batal</button>
                                <button type="submit" disabled={form.processing} style={{ ...btnP, flex: 1, justifyContent: 'center' }}>
                                    <AIcon name="check" size={16} color="#fff" />
                                    Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <div style={{ fontSize: 12.5, color: C.muted, marginBottom: 5 }}>{label}</div>
            {children}
            {error && <div style={{ fontSize: 11.5, color: C.red, marginTop: 4 }}>{error}</div>}
        </div>
    );
}

function Badge({ text, color }: { text: string; color: string }) {
    return (
        <span style={{ marginLeft: 8, padding: '1px 7px', borderRadius: 6, fontSize: 10.5, fontWeight: 600, color, background: `${color}1a` }}>
            {text}
        </span>
    );
}

const inp: React.CSSProperties = {
    width: '100%',
    height: 40,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
};
