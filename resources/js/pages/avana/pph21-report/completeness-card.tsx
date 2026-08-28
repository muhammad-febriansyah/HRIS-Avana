import { useState } from 'react';
import { AIcon, C } from '@/lib/avana';
import { Panel, ProgressRow, btnSmNeutral, btnSmPrimary } from './components';
import type { Completeness } from './types';

/**
 * Tax-data completeness for the period, with the employees behind every
 * shortfall — a bar that only counts a gap cannot close it.
 */
export function CompletenessCard({
    completeness,
}: {
    completeness: Completeness;
}) {
    const [open, setOpen] = useState(false);
    const hasIssues = completeness.issue_total > 0;

    return (
        <Panel
            title="Kelengkapan Data Pajak"
            right={
                hasIssues ? (
                    <button
                        type="button"
                        data-testid="toggle-kelengkapan"
                        style={btnSmNeutral}
                        onClick={() => setOpen((v) => !v)}
                    >
                        {open
                            ? 'Sembunyikan'
                            : `Lihat ${completeness.issue_total} karyawan`}
                        <AIcon
                            name={open ? 'chevron-up' : 'chevron-right'}
                            size={13}
                            color={C.navy}
                        />
                    </button>
                ) : undefined
            }
        >
            <div style={{ padding: '16px 20px' }}>
                {completeness.bars.length === 0 ? (
                    <div style={{ fontSize: 13, color: C.faint }}>
                        Belum ada payroll pada masa pajak ini.
                    </div>
                ) : (
                    completeness.bars.map((bar) => (
                        <ProgressRow key={bar.label} bar={bar} />
                    ))
                )}

                {!hasIssues && completeness.bars.length > 0 && (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 12.5,
                            color: C.green,
                            marginTop: 4,
                        }}
                    >
                        <AIcon name="circle-check" size={14} color={C.green} />
                        Semua data pajak karyawan lengkap.
                    </div>
                )}

                {open && hasIssues && (
                    <div
                        data-testid="issue-list"
                        style={{
                            marginTop: 12,
                            paddingTop: 12,
                            borderTop: `1px solid ${C.line}`,
                            maxHeight: 260,
                            overflowY: 'auto',
                        }}
                    >
                        {completeness.issues.map((issue) => (
                            <div
                                key={issue.employee_id}
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'center',
                                    gap: 10,
                                    padding: '7px 0',
                                    fontSize: 12.5,
                                }}
                            >
                                <div>
                                    <div style={{ color: C.text }}>
                                        {issue.name}
                                    </div>
                                    <div
                                        style={{
                                            color: C.faint,
                                            fontSize: 11.5,
                                        }}
                                    >
                                        {issue.employee_number ?? '—'}
                                    </div>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 5,
                                        flexWrap: 'wrap',
                                        justifyContent: 'flex-end',
                                    }}
                                >
                                    {issue.missing.map((field) => (
                                        <span
                                            key={field}
                                            style={{
                                                padding: '3px 8px',
                                                borderRadius: 100,
                                                background:
                                                    'rgba(217,119,6,.11)',
                                                color: C.amber,
                                                fontSize: 11,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {field}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        ))}
                        {completeness.issue_total >
                            completeness.issues.length && (
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: C.faint,
                                    paddingTop: 8,
                                }}
                            >
                                Menampilkan {completeness.issues.length} dari{' '}
                                {completeness.issue_total} karyawan — lengkapi
                                sisanya di BPJS &amp; Pajak → Profil Pajak
                                Karyawan.
                            </div>
                        )}
                    </div>
                )}

                {/* Outside the scrolling list: the fix for every row above is
                    the same screen, and a call to action buried under a scroll
                    is one nobody finds. */}
                {open && hasIssues && (
                    <a
                        href="/avana/payroll/konfigurasi"
                        style={{ ...btnSmPrimary, marginTop: 14 }}
                    >
                        Buka Profil Pajak Karyawan
                        <AIcon name="arrow-right" size={13} color="#fff" />
                    </a>
                )}
            </div>
        </Panel>
    );
}
