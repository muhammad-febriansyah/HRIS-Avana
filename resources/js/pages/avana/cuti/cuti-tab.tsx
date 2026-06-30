import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import { LeaveRequestForm } from './leave-form';
import { LeaveApprovalList } from './leave-list';
import { balanceColors, balanceIcons } from './types';
import type {
    EmployeeOption,
    LeaveBalance,
    LeaveFormData,
    LeaveRequest,
    LeaveTypeOption,
    PaginationMeta,
} from './types';

interface CutiTabProps {
    form: InertiaFormProps<LeaveFormData>;
    employees: EmployeeOption[];
    leaveTypes: LeaveTypeOption[];
    balances: LeaveBalance[];
    rows: LeaveRequest[];
    meta: PaginationMeta;
    pendingActive: boolean;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    setStatusFilter: (status: 'pending' | undefined) => void;
    goToPage: (page: number) => void;
    approveRequest: (id: number) => void;
    rejectRequest: (id: number) => void;
}

/** "Cuti" tab: saldo cards, the request form, and the team approval list. */
export function CutiTab({
    form,
    employees,
    leaveTypes,
    balances,
    rows,
    meta,
    pendingActive,
    onSubmit,
    setStatusFilter,
    goToPage,
    approveRequest,
    rejectRequest,
}: CutiTabProps) {
    return (
        <>
            {/* Saldo */}
            <div
                className="avn-3col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(3,1fr)',
                    gap: 16,
                    marginBottom: 18,
                }}
            >
                {balances.map((b, i) => {
                    const color = balanceColors[i % balanceColors.length];
                    const icon = balanceIcons[i % balanceIcons.length];

                    return (
                        <div
                            key={b.id}
                            style={{ ...card, padding: '18px 20px' }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                    }}
                                >
                                    <div
                                        style={{
                                            width: 38,
                                            height: 38,
                                            borderRadius: 10,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            background: color + '1a',
                                            color,
                                        }}
                                    >
                                        <AIcon
                                            name={icon}
                                            size={19}
                                            color={color}
                                        />
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {b.jenis}
                                    </div>
                                </div>
                            </div>
                            <div
                                style={{
                                    marginTop: 14,
                                    display: 'flex',
                                    alignItems: 'baseline',
                                    gap: 6,
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: 28,
                                        fontWeight: 700,
                                        color: C.navy,
                                    }}
                                >
                                    {b.sisa}
                                </span>
                                <span style={{ fontSize: 13, color: C.faint }}>
                                    / {b.total} hari tersisa
                                </span>
                            </div>
                            <div
                                style={{
                                    height: 7,
                                    background: C.line,
                                    borderRadius: 4,
                                    marginTop: 10,
                                    overflow: 'hidden',
                                }}
                            >
                                <div
                                    style={{
                                        height: '100%',
                                        width: b.pct,
                                        background: color,
                                        borderRadius: 4,
                                    }}
                                />
                            </div>
                        </div>
                    );
                })}
            </div>

            <div
                className="avn-abs"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '380px 1fr',
                    gap: 18,
                    alignItems: 'start',
                }}
            >
                <LeaveRequestForm
                    form={form}
                    employees={employees}
                    leaveTypes={leaveTypes}
                    balances={balances}
                    onSubmit={onSubmit}
                />

                <LeaveApprovalList
                    rows={rows}
                    meta={meta}
                    pendingActive={pendingActive}
                    setStatusFilter={setStatusFilter}
                    goToPage={goToPage}
                    approveRequest={approveRequest}
                    rejectRequest={rejectRequest}
                />
            </div>
        </>
    );
}
