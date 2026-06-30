import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import LeaveController from '@/actions/App/Http/Controllers/Avana/LeaveController';
import LeaveTypeController from '@/actions/App/Http/Controllers/Avana/LeaveTypeController';
import OvertimeController from '@/actions/App/Http/Controllers/Avana/OvertimeController';
import PermissionRequestController from '@/actions/App/Http/Controllers/Avana/PermissionRequestController';
import WfhController from '@/actions/App/Http/Controllers/Avana/WfhController';
import { AIcon, btnOut, C } from '@/lib/avana';
import { tabBarStyle } from './components';
import { CutiTab } from './cuti-tab';
import { IzinTab } from './izin-tab';
import { LemburTab } from './lembur-tab';
import { tabs } from './types';
import type {
    CutiProps,
    FlashProps,
    IzinFormData,
    LeaveFormData,
    OvertimeFormData,
    TabKey,
    WfhFormData,
} from './types';
import { WfhTab } from './wfh-tab';

export default function AvanaCuti({
    requests,
    filters,
    leaveTypes,
    employees,
    balances,
    overtimeRequests,
    permissionRequests,
    wfhRequests,
}: CutiProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = requests.meta;
    const pendingActive = filters.status === 'pending';
    const [tab, setTab] = useState<TabKey>('cuti');

    const form = useForm<LeaveFormData>({
        employee_id: '',
        leave_type_id: '',
        start_date: '',
        end_date: '',
        reason: '',
    });

    const overtimeForm = useForm<OvertimeFormData>({
        employee_id: '',
        date: '',
        hours: '',
        reason: '',
    });

    const izinForm = useForm<IzinFormData>({
        employee_id: '',
        date: '',
        type: 'izin_jam',
        start_time: '',
        end_time: '',
        reason: '',
    });

    const wfhForm = useForm<WfhFormData>({
        employee_id: '',
        start_date: '',
        end_date: '',
        reason: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const submitLeave = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(LeaveController.store());
    };

    const submitOvertime = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        overtimeForm.submit(OvertimeController.store(), {
            preserveScroll: true,
            onSuccess: () => overtimeForm.reset(),
        });
    };

    const submitIzin = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        izinForm.submit(PermissionRequestController.store(), {
            preserveScroll: true,
            onSuccess: () => izinForm.reset(),
        });
    };

    const submitWfh = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        wfhForm.submit(WfhController.store(), {
            preserveScroll: true,
            onSuccess: () => wfhForm.reset(),
        });
    };

    const approveOvertime = (id: number) =>
        router.post(
            OvertimeController.approve(id).url,
            {},
            { preserveScroll: true },
        );
    const rejectOvertime = (id: number) =>
        router.post(
            OvertimeController.reject(id).url,
            {},
            { preserveScroll: true },
        );
    const approveIzin = (id: number) =>
        router.post(
            PermissionRequestController.approve(id).url,
            {},
            { preserveScroll: true },
        );
    const rejectIzin = (id: number) =>
        router.post(
            PermissionRequestController.reject(id).url,
            {},
            { preserveScroll: true },
        );
    const approveWfh = (id: number) =>
        router.post(WfhController.approve(id).url, {}, { preserveScroll: true });
    const rejectWfh = (id: number) =>
        router.post(WfhController.reject(id).url, {}, { preserveScroll: true });

    const setStatusFilter = (status: 'pending' | undefined) => {
        router.get(
            window.location.pathname,
            { ...filters, status, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const approveRequest = (id: number) => {
        router.post(LeaveController.approve(id).url, {}, { preserveScroll: true });
    };

    const rejectRequest = (id: number) => {
        router.post(LeaveController.reject(id).url, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Cuti & Lembur" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 7,
                            }}
                        >
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Cuti &amp; Lembur
                            </span>
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            Cuti &amp; Lembur
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Ajukan cuti dan kelola persetujuan tim Anda
                        </div>
                    </div>
                    <Link
                        href={LeaveTypeController.index().url}
                        style={{ ...btnOut, textDecoration: 'none' }}
                    >
                        <AIcon name="settings-2" size={16} color={C.muted} />
                        Kelola Jenis Cuti
                    </Link>
                </div>

                {/* Tab bar */}
                <div style={tabBarStyle}>
                    {tabs.map((item) => {
                        const active = tab === item.key;

                        return (
                            <button
                                key={item.key}
                                onClick={() => setTab(item.key)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    height: 36,
                                    padding: '0 16px',
                                    border: 'none',
                                    borderRadius: 8,
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: active ? '#fff' : C.muted,
                                    background: active ? C.primary : 'transparent',
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                <AIcon
                                    name={item.icon}
                                    size={15}
                                    color={active ? '#fff' : C.muted}
                                />
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {tab === 'cuti' && (
                    <CutiTab
                        form={form}
                        employees={employees}
                        leaveTypes={leaveTypes}
                        balances={balances}
                        rows={requests.data}
                        meta={meta}
                        pendingActive={pendingActive}
                        onSubmit={submitLeave}
                        setStatusFilter={setStatusFilter}
                        goToPage={goToPage}
                        approveRequest={approveRequest}
                        rejectRequest={rejectRequest}
                    />
                )}

                {tab === 'lembur' && (
                    <LemburTab
                        form={overtimeForm}
                        employees={employees}
                        items={overtimeRequests}
                        onSubmit={submitOvertime}
                        onApprove={approveOvertime}
                        onReject={rejectOvertime}
                    />
                )}

                {tab === 'izin' && (
                    <IzinTab
                        form={izinForm}
                        employees={employees}
                        items={permissionRequests}
                        onSubmit={submitIzin}
                        onApprove={approveIzin}
                        onReject={rejectIzin}
                    />
                )}

                {tab === 'wfh' && (
                    <WfhTab
                        form={wfhForm}
                        employees={employees}
                        items={wfhRequests}
                        onSubmit={submitWfh}
                        onApprove={approveWfh}
                        onReject={rejectWfh}
                    />
                )}
            </div>
        </>
    );
}
