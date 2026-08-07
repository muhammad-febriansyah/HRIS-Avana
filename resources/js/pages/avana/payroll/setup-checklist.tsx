import { Link } from '@inertiajs/react';
import { AIcon, C, card } from '@/lib/avana';
import type { ChecklistStep } from './types';

/**
 * The payroll setup steps, in the order the setup documentation walks them,
 * each marked done from real tenant data. Rendered only while something is
 * still missing — a fully configured tenant never sees it.
 */
export function SetupChecklist({ steps }: { steps: ChecklistStep[] }) {
    const doneCount = steps.filter((step) => step.done).length;

    if (doneCount === steps.length) {
        return null;
    }

    return (
        <div style={{ ...card, marginBottom: 16 }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '14px 18px',
                    borderBottom: `1px solid ${C.line}`,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        fontSize: 14,
                        fontWeight: 600,
                        color: C.navy,
                    }}
                >
                    <AIcon name="list-checks" size={17} color={C.primary} />
                    Persiapan Payroll
                </div>
                <div style={{ fontSize: 12.5, color: C.muted }}>
                    {doneCount} dari {steps.length} langkah selesai
                </div>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns:
                        'repeat(auto-fill, minmax(210px, 1fr))',
                    gap: 10,
                    padding: '14px 18px',
                }}
            >
                {steps.map((step, index) => {
                    const inner = (
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'flex-start',
                                gap: 10,
                                padding: '10px 12px',
                                borderRadius: 10,
                                border: `1px solid ${step.done ? '#BBF7D0' : C.border}`,
                                background: step.done ? '#F0FDF4' : '#fff',
                                height: '100%',
                            }}
                        >
                            <div
                                style={{
                                    width: 22,
                                    height: 22,
                                    minWidth: 22,
                                    borderRadius: '50%',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: 11.5,
                                    fontWeight: 700,
                                    color: step.done ? '#fff' : C.muted,
                                    background: step.done
                                        ? '#16A34A'
                                        : '#EEF1F7',
                                    marginTop: 1,
                                }}
                            >
                                {step.done ? (
                                    <AIcon name="check" size={13} color="#fff" />
                                ) : (
                                    index + 1
                                )}
                            </div>
                            <div style={{ minWidth: 0 }}>
                                <div
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.text,
                                    }}
                                >
                                    {step.label}
                                </div>
                                {step.hint && (
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: step.done
                                                ? '#15803D'
                                                : C.muted,
                                            marginTop: 2,
                                        }}
                                    >
                                        {step.hint}
                                    </div>
                                )}
                            </div>
                        </div>
                    );

                    return step.href ? (
                        <Link
                            key={step.key}
                            href={step.href}
                            style={{ textDecoration: 'none' }}
                        >
                            {inner}
                        </Link>
                    ) : (
                        <div key={step.key}>{inner}</div>
                    );
                })}
            </div>
        </div>
    );
}
