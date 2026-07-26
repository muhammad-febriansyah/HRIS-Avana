import { Head, Link } from '@inertiajs/react';
import { AIcon, C, card } from '@/lib/avana';
import { CategoryChip, RISK } from './components';
import type { Category } from './components';

interface Factor {
    key: string;
    label: string;
    weight: number;
    available: boolean;
    triggered: boolean;
    points: number;
    detail: string;
    disabled?: boolean;
}

interface AttritionShowProps {
    employee: {
        id: number;
        name: string;
        employee_number: string | null;
        department: string | null;
        branch: string | null;
        position: string | null;
        job_level: string | null;
        manager: string | null;
        join_date: string | null;
        initials: string;
        avatar_color: string;
    };
    result: {
        score: number;
        category: Category;
        coverage: number;
        factors: Factor[];
        top_factors: string[];
    };
}

export default function AttritionShow({
    employee,
    result,
}: AttritionShowProps) {
    const r = RISK[result.category];

    const facts: [string, string | null][] = [
        ['Departemen', employee.department],
        ['Cabang', employee.branch],
        ['Jabatan', employee.position],
        ['Level', employee.job_level],
        ['Atasan', employee.manager],
        ['Tanggal Masuk', employee.join_date],
    ];

    return (
        <>
            <Head title={`Risiko Resign · ${employee.name}`} />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 16,
                    }}
                >
                    <Link
                        href="/avana/attrition"
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Prediksi Resign
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{employee.name}</span>
                </div>

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1.6fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 18,
                        }}
                    >
                        <div style={{ ...card, padding: 24 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 12,
                                    marginBottom: 18,
                                }}
                            >
                                <div
                                    style={{
                                        width: 46,
                                        height: 46,
                                        borderRadius: 11,
                                        flex: 'none',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        background: employee.avatar_color,
                                        color: '#fff',
                                        fontSize: 15,
                                        fontWeight: 700,
                                    }}
                                >
                                    {employee.initials}
                                </div>
                                <div>
                                    <div
                                        style={{
                                            fontSize: 16,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {employee.name}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.faint,
                                        }}
                                    >
                                        {employee.employee_number} ·{' '}
                                        {employee.position ?? '—'}
                                    </div>
                                </div>
                            </div>

                            <div
                                style={{
                                    textAlign: 'center',
                                    padding: '8px 0 4px',
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 48,
                                        fontWeight: 800,
                                        color: r.color,
                                        lineHeight: 1,
                                        fontVariantNumeric: 'tabular-nums',
                                    }}
                                >
                                    {result.score}
                                    <span
                                        style={{
                                            fontSize: 20,
                                            color: C.faint,
                                            fontWeight: 600,
                                        }}
                                    >
                                        /100
                                    </span>
                                </div>
                                <div style={{ marginTop: 10 }}>
                                    <CategoryChip category={result.category} />
                                </div>
                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: C.faint,
                                        marginTop: 10,
                                    }}
                                >
                                    Cakupan data {result.coverage}% dari model
                                </div>
                            </div>

                            <div
                                style={{
                                    height: 10,
                                    borderRadius: 6,
                                    background: C.line,
                                    overflow: 'hidden',
                                    marginTop: 14,
                                }}
                            >
                                <div
                                    style={{
                                        width: `${result.score}%`,
                                        height: '100%',
                                        borderRadius: 6,
                                        background: r.color,
                                    }}
                                />
                            </div>
                        </div>

                        <div style={{ ...card, padding: '18px 20px' }}>
                            {facts.map(([k, v]) => (
                                <div
                                    key={k}
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        padding: '7px 0',
                                        fontSize: 13,
                                    }}
                                >
                                    <span style={{ color: C.muted }}>{k}</span>
                                    <span
                                        style={{
                                            color: C.text,
                                            fontWeight: 500,
                                        }}
                                    >
                                        {v ?? '—'}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div style={{ ...card, padding: '20px 22px' }}>
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Rincian Faktor Risiko
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 16,
                            }}
                        >
                            Faktor pemicu skor ditandai merah; faktor tanpa data
                            tidak dihitung.
                        </div>

                        <div
                            style={{ display: 'flex', flexDirection: 'column' }}
                        >
                            {result.factors.map((f) => {
                                const state = f.disabled
                                    ? 'off_disabled'
                                    : !f.available
                                      ? 'na'
                                      : f.triggered
                                        ? 'on'
                                        : 'off';
                                const dot =
                                    state === 'on'
                                        ? C.red
                                        : state === 'off'
                                          ? C.green
                                          : C.faint;
                                const detailText = f.disabled
                                    ? 'Dinonaktifkan di pengaturan'
                                    : state === 'na'
                                      ? 'Data belum tersedia'
                                      : f.detail;

                                return (
                                    <div
                                        key={f.key}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 12,
                                            padding: '11px 0',
                                            borderBottom: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 9,
                                                height: 9,
                                                borderRadius: '50%',
                                                flex: 'none',
                                                background: dot,
                                            }}
                                        />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color:
                                                        state === 'on' ||
                                                        state === 'off'
                                                            ? C.text
                                                            : C.faint,
                                                }}
                                            >
                                                {f.label}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: C.faint,
                                                    marginTop: 1,
                                                }}
                                            >
                                                {detailText}
                                            </div>
                                        </div>
                                        <div
                                            style={{
                                                textAlign: 'right',
                                                flex: 'none',
                                            }}
                                        >
                                            {state === 'na' ||
                                            state === 'off_disabled' ? (
                                                <span
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {state === 'off_disabled'
                                                        ? 'Off'
                                                        : 'N/A'}
                                                </span>
                                            ) : (
                                                <span
                                                    style={{
                                                        fontSize: 13,
                                                        fontWeight: 700,
                                                        color: f.triggered
                                                            ? C.red
                                                            : C.faint,
                                                        fontVariantNumeric:
                                                            'tabular-nums',
                                                    }}
                                                >
                                                    {f.triggered
                                                        ? `+${f.points}`
                                                        : '0'}
                                                    <span
                                                        style={{
                                                            color: C.faint,
                                                            fontWeight: 500,
                                                        }}
                                                    >
                                                        /{f.weight}
                                                    </span>
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
