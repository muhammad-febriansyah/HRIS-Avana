import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { CSSProperties, FormEvent } from 'react';
import FaqController from '@/actions/App/Http/Controllers/Avana/FaqController';
import { AIcon, C, card } from '@/lib/avana';
import { toast } from 'sonner';

interface Faq { id: number; question: string; answer: string; }
interface PageProps extends Record<string, unknown> { faqs: Faq[]; flash?: { success?: string }; }
interface FaqForm { question: string; answer: string; }

const emptyForm: FaqForm = { question: '', answer: '' };
const inputStyle: CSSProperties = { width: '100%', border: '1px solid #DCE2EC', borderRadius: 9, padding: '10px 12px', fontSize: 14, color: C.text, outline: 'none', boxSizing: 'border-box' };

export default function Faqs({ faqs }: PageProps) {
    const { flash } = usePage<PageProps>().props;
    const [editing, setEditing] = useState<Faq | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [confirming, setConfirming] = useState<Faq | null>(null);
    const form = useForm<FaqForm>(emptyForm);

    useEffect(() => { if (flash?.success) toast.success(flash.success, { id: flash.success }); }, [flash?.success]);
    const closeModal = () => { setModalOpen(false); setEditing(null); form.reset(); form.clearErrors(); };
    const openCreate = () => { setEditing(null); form.setData(emptyForm); form.clearErrors(); setModalOpen(true); };
    const openEdit = (faq: Faq) => { setEditing(faq); form.setData({ question: faq.question, answer: faq.answer }); form.clearErrors(); setModalOpen(true); };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: closeModal };
        if (editing) { form.put(FaqController.update(editing.id).url, options); } else { form.post(FaqController.store().url, options); }
    };
    const remove = () => {
        if (!confirming) return;
        router.delete(FaqController.destroy(confirming.id).url, { preserveScroll: true, onSuccess: () => setConfirming(null) });
    };

    return <>
        <Head title="FAQ" />
        <div style={{ padding: '28px 32px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap', marginBottom: 24 }}>
                <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 7, color: C.faint, fontSize: 12.5, marginBottom: 8 }}><span>Platform</span><AIcon name="chevron-right" size={13} /><span style={{ color: C.muted }}>FAQ</span></div>
                    <h1 style={{ margin: 0, color: C.navy, fontSize: 25, fontWeight: 650 }}>FAQ</h1>
                    <p style={{ margin: '7px 0 0', color: C.muted, fontSize: 14 }}>Kelola pertanyaan dan jawaban yang tampil pada halaman publik AvanaHR.</p>
                </div>
                <button onClick={openCreate} style={{ height: 42, padding: '0 16px', background: C.primary, color: '#fff', border: 0, borderRadius: 9, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 8 }}><AIcon name="plus" size={17} color="#fff" /> Tambah FAQ</button>
            </div>
            <div style={{ ...card, overflow: 'hidden' }}>
                <div style={{ padding: '18px 20px', borderBottom: '1px solid #E8ECF2', display: 'flex', justifyContent: 'space-between' }}><div><div style={{ color: C.navy, fontWeight: 600 }}>Daftar FAQ</div><div style={{ color: C.muted, fontSize: 13, marginTop: 4 }}>{faqs.length} pertanyaan tersimpan</div></div><AIcon name="circle-help" size={22} color={C.primary} /></div>
                {faqs.length === 0 ? <div style={{ padding: 40, textAlign: 'center', color: C.muted }}>Belum ada FAQ.</div> : faqs.map((faq, index) => <div key={faq.id} style={{ padding: '18px 20px', borderBottom: index === faqs.length - 1 ? 0 : '1px solid #E8ECF2', display: 'flex', gap: 16, alignItems: 'flex-start' }}>
                    <div style={{ width: 30, height: 30, borderRadius: 8, background: 'rgba(47,84,201,.1)', color: C.primary, display: 'grid', placeItems: 'center', fontWeight: 700, fontSize: 13, flex: 'none' }}>{index + 1}</div>
                    <div style={{ flex: 1, minWidth: 0 }}><div style={{ color: C.navy, fontWeight: 600, lineHeight: 1.45 }}>{faq.question}</div><div style={{ color: C.muted, fontSize: 13.5, lineHeight: 1.6, marginTop: 6 }}>{faq.answer}</div></div>
                    <div style={{ display: 'flex', gap: 6 }}><button aria-label="Edit FAQ" onClick={() => openEdit(faq)} style={{ border: 0, background: 'transparent', color: C.primary, cursor: 'pointer', padding: 7 }}><AIcon name="pencil" size={16} /></button><button aria-label="Hapus FAQ" onClick={() => setConfirming(faq)} style={{ border: 0, background: 'transparent', color: C.red, cursor: 'pointer', padding: 7 }}><AIcon name="trash-2" size={16} /></button></div>
                </div>)}
            </div>
        </div>
        {modalOpen && <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'grid', placeItems: 'center', padding: 20 }}><div onClick={closeModal} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} /><form onSubmit={submit} style={{ position: 'relative', width: '100%', maxWidth: 600, background: '#fff', borderRadius: 14, padding: 26, boxShadow: '0 20px 50px rgba(15,23,42,.25)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 20 }}><div><h2 style={{ margin: 0, color: C.navy, fontSize: 19 }}>{editing ? 'Edit FAQ' : 'Tambah FAQ'}</h2><p style={{ margin: '5px 0 0', color: C.muted, fontSize: 13 }}>Isi pertanyaan dan jawaban FAQ.</p></div><button type="button" onClick={closeModal} style={{ border: 0, background: 'transparent', cursor: 'pointer' }}><AIcon name="x" size={19} /></button></div>
            <label style={{ display: 'block', color: C.text, fontSize: 13, fontWeight: 600, marginBottom: 7 }}>Question</label><input value={form.data.question} onChange={e => form.setData('question', e.target.value)} style={inputStyle} placeholder="Masukkan pertanyaan" />{form.errors.question && <div style={{ color: C.red, fontSize: 12, marginTop: 5 }}>{form.errors.question}</div>}
            <label style={{ display: 'block', color: C.text, fontSize: 13, fontWeight: 600, margin: '16px 0 7px' }}>Answer</label><textarea value={form.data.answer} onChange={e => form.setData('answer', e.target.value)} style={{ ...inputStyle, minHeight: 150, resize: 'vertical' }} placeholder="Masukkan jawaban" />{form.errors.answer && <div style={{ color: C.red, fontSize: 12, marginTop: 5 }}>{form.errors.answer}</div>}
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 22 }}><button type="button" onClick={closeModal} style={{ height: 42, padding: '0 16px', border: '1px solid #DCE2EC', background: '#fff', color: C.text, borderRadius: 9, cursor: 'pointer' }}>Batal</button><button disabled={form.processing} style={{ height: 42, padding: '0 18px', border: 0, background: C.primary, color: '#fff', borderRadius: 9, cursor: 'pointer', fontWeight: 600 }}>{form.processing ? 'Menyimpan...' : 'Simpan'}</button></div>
        </form></div>}
        {confirming && <div style={{ position: 'fixed', inset: 0, zIndex: 90, display: 'grid', placeItems: 'center', padding: 20 }}><div onClick={() => setConfirming(null)} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} /><div style={{ position: 'relative', width: '100%', maxWidth: 400, background: '#fff', borderRadius: 14, padding: 26 }}><h2 style={{ margin: 0, color: C.navy, fontSize: 19 }}>Hapus FAQ?</h2><p style={{ color: C.muted, fontSize: 14, lineHeight: 1.5 }}>FAQ ini akan dihapus secara permanen.</p><div style={{ display: 'flex', gap: 10, marginTop: 20 }}><button onClick={() => setConfirming(null)} style={{ flex: 1, height: 42, border: '1px solid #DCE2EC', background: '#fff', borderRadius: 9, cursor: 'pointer' }}>Batal</button><button onClick={remove} style={{ flex: 1, height: 42, border: 0, background: C.red, color: '#fff', borderRadius: 9, cursor: 'pointer', fontWeight: 600 }}>Hapus</button></div></div></div>}
    </>;
}
