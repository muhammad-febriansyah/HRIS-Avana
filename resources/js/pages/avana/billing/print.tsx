import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { C } from '@/lib/avana';
import { formatDate, formatRupiah, INVOICE_STATUS_LABEL } from './types';
import type { InvoicePrintProps } from './types';

/**
 * Standalone printable invoice sheet. Drops the AvanaHR sidebar chrome via the
 * Inertia per-page `layout` override so the browser Print-to-PDF is clean.
 */
export default function InvoicePrint({ invoice }: InvoicePrintProps) {
    return (
        <>
            <Head title={`Invoice ${invoice.invoice_number}`} />
            <div style={{ minHeight: '100vh', background: '#f1f5f9', padding: '32px 16px' }}>
                <div style={{ maxWidth: 720, margin: '0 auto' }}>
                    <div className="no-print" style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 14 }}>
                        <button
                            onClick={() => window.print()}
                            style={{ height: 40, padding: '0 18px', background: C.primary, color: '#fff', border: 'none', borderRadius: 8, fontSize: 13.5, fontWeight: 600, cursor: 'pointer' }}
                        >
                            Cetak / Simpan PDF
                        </button>
                    </div>

                    <div style={{ background: '#fff', borderRadius: 12, padding: 40, boxShadow: '0 1px 3px rgba(15,23,42,.08)' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 32 }}>
                            <div>
                                <div style={{ fontSize: 22, fontWeight: 700, color: C.primary }}>AvanaHR</div>
                                <div style={{ fontSize: 12.5, color: C.muted, marginTop: 4 }}>Platform HRIS & Payroll</div>
                            </div>
                            <div style={{ textAlign: 'right' }}>
                                <div style={{ fontSize: 20, fontWeight: 700, color: C.navy }}>INVOICE</div>
                                <div style={{ fontSize: 13, color: C.muted, marginTop: 4 }}>{invoice.invoice_number}</div>
                                <div style={{ marginTop: 6 }}>
                                    <span style={{ fontSize: 12, fontWeight: 600, color: invoice.status === 'paid' ? C.green : C.amber }}>
                                        {INVOICE_STATUS_LABEL[invoice.status] ?? invoice.status}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 28 }}>
                            <Block label="Ditagihkan kepada">
                                <div style={{ fontSize: 14, fontWeight: 600, color: C.navy }}>{invoice.tenant_company ?? invoice.tenant ?? '—'}</div>
                            </Block>
                            <Block label="Tanggal">
                                <div style={{ fontSize: 13, color: C.text }}>Terbit: {formatDate(invoice.issue_date)}</div>
                                <div style={{ fontSize: 13, color: C.text }}>Jatuh tempo: {formatDate(invoice.due_date)}</div>
                                {invoice.period_start ? (
                                    <div style={{ fontSize: 12.5, color: C.muted }}>Periode: {formatDate(invoice.period_start)} – {formatDate(invoice.period_end)}</div>
                                ) : null}
                            </Block>
                        </div>

                        <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: 20 }}>
                            <thead>
                                <tr style={{ borderBottom: `2px solid ${C.border}` }}>
                                    <th style={{ textAlign: 'left', padding: '10px 8px', fontSize: 11.5, color: C.faint, textTransform: 'uppercase' }}>Deskripsi</th>
                                    <th style={{ textAlign: 'right', padding: '10px 8px', fontSize: 11.5, color: C.faint, textTransform: 'uppercase' }}>Qty</th>
                                    <th style={{ textAlign: 'right', padding: '10px 8px', fontSize: 11.5, color: C.faint, textTransform: 'uppercase' }}>Harga</th>
                                    <th style={{ textAlign: 'right', padding: '10px 8px', fontSize: 11.5, color: C.faint, textTransform: 'uppercase' }}>Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoice.items.map((item) => (
                                    <tr key={item.id} style={{ borderBottom: `1px solid ${C.line}` }}>
                                        <td style={{ padding: '11px 8px', fontSize: 13, color: C.text }}>{item.description}</td>
                                        <td style={{ padding: '11px 8px', fontSize: 13, color: C.text, textAlign: 'right' }}>{item.quantity}</td>
                                        <td style={{ padding: '11px 8px', fontSize: 13, color: C.text, textAlign: 'right' }}>{formatRupiah(item.unit_price)}</td>
                                        <td style={{ padding: '11px 8px', fontSize: 13, color: C.text, textAlign: 'right' }}>{formatRupiah(item.amount)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <div style={{ width: 260 }}>
                                <Row label="Subtotal" value={formatRupiah(invoice.subtotal)} />
                                <Row label="Pajak" value={formatRupiah(invoice.tax)} />
                                <div style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 0', borderTop: `2px solid ${C.border}`, marginTop: 6 }}>
                                    <span style={{ fontSize: 14, fontWeight: 700, color: C.navy }}>Total</span>
                                    <span style={{ fontSize: 16, fontWeight: 700, color: C.navy }}>{formatRupiah(invoice.total)}</span>
                                </div>
                            </div>
                        </div>

                        {invoice.notes ? (
                            <div style={{ marginTop: 24, fontSize: 12.5, color: C.muted, borderTop: `1px solid ${C.line}`, paddingTop: 14 }}>{invoice.notes}</div>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

function Block({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <div style={{ fontSize: 11.5, color: C.faint, textTransform: 'uppercase', marginBottom: 6 }}>{label}</div>
            {children}
        </div>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '5px 0', fontSize: 13, color: C.text }}>
            <span style={{ color: C.muted }}>{label}</span>
            <span>{value}</span>
        </div>
    );
}
