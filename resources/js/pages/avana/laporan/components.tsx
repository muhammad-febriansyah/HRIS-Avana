import type { CSSProperties } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import type { ReportCard } from './types';

/** Primary "Unduh CSV" download button style. */
export const btnP: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 8,
    height: 40,
    padding: '0 16px',
    background: C.primary,
    color: '#fff',
    border: 'none',
    borderRadius: 8,
    fontSize: 13.5,
    fontWeight: 600,
    cursor: 'pointer',
    textDecoration: 'none',
    transition: 'background .15s,box-shadow .15s',
};

/** A single report card: icon, copy, headline stat and CSV download link. */
export function ReportCardItem({ report }: { report: ReportCard }) {
    return (
        <div
            style={{
                ...card,
                padding: '22px 24px',
                display: 'flex',
                flexDirection: 'column',
                gap: 16,
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-start',
                    gap: 14,
                }}
            >
                <div
                    style={{
                        width: 46,
                        height: 46,
                        borderRadius: 12,
                        flex: 'none',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: report.color + '1a',
                        color: report.color,
                    }}
                >
                    <AIcon name={report.icon} size={22} color={report.color} />
                </div>
                <div>
                    <div
                        style={{
                            fontSize: 16,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        {report.title}
                    </div>
                    <div
                        style={{
                            fontSize: 13,
                            color: C.muted,
                            marginTop: 4,
                            lineHeight: 1.5,
                        }}
                    >
                        {report.desc}
                    </div>
                </div>
            </div>

            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-end',
                    justifyContent: 'space-between',
                    gap: 12,
                    marginTop: 'auto',
                }}
            >
                <div>
                    <div
                        style={{
                            fontSize: 12,
                            color: C.faint,
                        }}
                    >
                        {report.statLabel}
                    </div>
                    <div
                        style={{
                            fontSize: 22,
                            fontWeight: 700,
                            color: C.navy,
                            marginTop: 2,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {report.statValue}
                    </div>
                </div>
                <a
                    href={'/avana/laporan/export/' + report.type}
                    download
                    style={btnP}
                >
                    <AIcon name="download" size={16} color="#fff" />
                    Unduh CSV
                </a>
            </div>
        </div>
    );
}
