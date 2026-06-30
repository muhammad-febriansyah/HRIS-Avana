import { Head, Link } from '@inertiajs/react';
import LetterTemplateController from '@/actions/App/Http/Controllers/Avana/LetterTemplateController';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import type { SuratPrintProps } from './types';

export default function SuratPrint({ letter, company }: SuratPrintProps) {
    return (
        <div
            style={{
                minHeight: '100vh',
                background: C.surface,
                padding: '24px 16px',
            }}
        >
            <Head title={letter.title} />

            {/* Print rules: hide the toolbar and flatten the background. */}
            <style>{`
                @media print {
                    .surat-toolbar { display: none !important; }
                    body { background: #fff !important; }
                    .surat-sheet { box-shadow: none !important; border: none !important; margin: 0 !important; }
                }
            `}</style>

            {/* Toolbar (screen only) */}
            <div
                className="surat-toolbar"
                style={{
                    maxWidth: 794,
                    margin: '0 auto 16px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                    flexWrap: 'wrap',
                }}
            >
                <Link
                    href={LetterTemplateController.index()}
                    style={{ ...btnOut, textDecoration: 'none' }}
                >
                    <AIcon name="arrow-left" size={16} color={C.text} />
                    Kembali
                </Link>
                <button onClick={() => window.print()} style={btnP}>
                    <AIcon name="printer" size={16} color="#fff" />
                    Cetak / Simpan PDF
                </button>
            </div>

            {/* Letter sheet */}
            <div
                className="surat-sheet"
                style={{
                    maxWidth: 794,
                    margin: '0 auto',
                    background: '#fff',
                    border: `1px solid ${C.border}`,
                    borderRadius: 6,
                    boxShadow: '0 1px 3px rgba(15,23,42,.08)',
                    padding: '56px 64px',
                    color: C.text,
                }}
            >
                {/* Company header */}
                <div
                    style={{
                        textAlign: 'center',
                        borderBottom: `2px solid ${C.navy}`,
                        paddingBottom: 16,
                        marginBottom: 28,
                    }}
                >
                    <div
                        style={{
                            fontSize: 20,
                            fontWeight: 700,
                            color: C.navy,
                            letterSpacing: '.01em',
                        }}
                    >
                        {company.name || 'Perusahaan'}
                    </div>
                </div>

                {/* Letter meta */}
                {(letter.letter_number || letter.generated_at_label) && (
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            flexWrap: 'wrap',
                            gap: 8,
                            fontSize: 13.5,
                            marginBottom: 24,
                        }}
                    >
                        <span>
                            {letter.letter_number
                                ? `No: ${letter.letter_number}`
                                : ''}
                        </span>
                        <span>{letter.generated_at_label ?? ''}</span>
                    </div>
                )}

                <h1
                    style={{
                        fontSize: 17,
                        fontWeight: 700,
                        textAlign: 'center',
                        textTransform: 'uppercase',
                        letterSpacing: '.03em',
                        color: C.navy,
                        margin: '0 0 28px',
                    }}
                >
                    {letter.title}
                </h1>

                {/* Rendered body (template output is generated server-side) */}
                <div
                    style={{
                        fontSize: 14,
                        lineHeight: 1.8,
                        whiteSpace: 'pre-wrap',
                    }}
                    dangerouslySetInnerHTML={{ __html: letter.body }}
                />
            </div>
        </div>
    );
}
