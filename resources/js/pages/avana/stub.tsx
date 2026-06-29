import { Head } from '@inertiajs/react';
import { AIcon, C } from '@/lib/avana';

export default function AvanaStub({ title = 'Laporan' }: { title?: string }) {
    return (
        <>
            <Head title={title} />
            <div style={{ padding: '28px 32px' }}>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: '0 0 6px' }}>{title}</h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 24 }}>Halaman ini sedang disiapkan.</div>
                <div style={{ background: '#fff', border: '1px dashed #D5DCEA', borderRadius: 12, padding: 60, textAlign: 'center' }}>
                    <AIcon name="hammer" size={30} color={C.faint} />
                    <div style={{ fontSize: 15, fontWeight: 600, color: C.text, marginTop: 12 }}>Segera hadir</div>
                </div>
            </div>
        </>
    );
}
