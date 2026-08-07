import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import EmployeeController from '@/actions/App/Http/Controllers/Avana/EmployeeController';
import { AIcon, C } from '@/lib/avana';
import { EmployeeForm } from './employee-form';
import { NO_MANAGER, UNASSIGNED_MANAGER } from './types';
import type {
    CustomFieldDef,
    Employee,
    EmployeeFormData,
    EmployeeFormOptions,
    FlashProps,
} from './types';

interface EmployeesEditProps {
    employee: {
        data: Employee;
    };
    options: EmployeeFormOptions;
    customFields: CustomFieldDef[];
}

/** Map a possibly-null relation id to a string for the controlled selects. */
function relationId(relation?: { id: number } | null): string {
    return relation?.id ? String(relation.id) : '';
}

export default function EmployeesEdit({
    employee,
    options,
    customFields,
}: EmployeesEditProps) {
    const { data } = employee;
    const { flash } = usePage<FlashProps>().props;

    // The contract currently in force, so editing an employee corrects that
    // contract rather than opening another one.
    const activeContract =
        (data.contracts ?? []).find((row) => row.status === 'active') ??
        (data.contracts ?? [])[0] ??
        null;

    const form = useForm<EmployeeFormData>({
        full_name: data.full_name ?? '',
        email: data.email ?? '',
        phone: data.phone ?? '',
        nik: data.nik ?? '',
        gender: data.gender ?? '',
        birth_date: data.birth_date_raw ?? '',
        birth_place: data.birth_place ?? '',
        religion: data.religion ?? '',
        marital_status: data.marital_status ?? '',
        address: data.address ?? '',
        employment_status: data.employment_status ?? '',
        join_date: data.join_date_raw ?? '',
        branch_id: relationId(data.branch),
        work_location_id: relationId(data.work_location),
        department_id: relationId(data.department),
        position_id: relationId(data.position),
        job_level_id: relationId(data.job_level),
        salary_master_id: data.salary_master_id ? String(data.salary_master_id) : '',
        contract_number: activeContract?.contract_number ?? '',
        contract_type: activeContract?.contract_type ?? '',
        contract_start_date: activeContract?.start_date_raw ?? '',
        contract_end_date: activeContract?.end_date_raw ?? '',
        bpjs_kesehatan_number: data.bpjs_kesehatan_number ?? '',
        ptkp_status: data.ptkp_status ?? '',
        bpjs_ketenagakerjaan_number: data.bpjs_ketenagakerjaan_number ?? '',
        // Neither an approver puncak nor a not-yet-assigned employee has a
        // manager row, so the picker shows whichever sentinel says which of the
        // two they are — an empty field would read as "belum diisi" for both.
        manager_id: data.is_top_approver
            ? NO_MANAGER
            : relationId(data.manager) || UNASSIGNED_MANAGER,
        status: data.status ?? 'active',
        password: '',
        role_id: data.role_id ? String(data.role_id) : '',
        link_user_id: '',
        is_top_approver: data.is_top_approver ?? false,
        custom_data: data.custom_data ?? {},
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(EmployeeController.update(data.route_key));
    };

    return (
        <>
            <Head title="Ubah Karyawan" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <Link
                        href={EmployeeController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Karyawan
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <Link
                        href={EmployeeController.show(data.route_key)}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        {data.full_name}
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Ubah</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 24px',
                        letterSpacing: '-.01em',
                    }}
                >
                    Ubah Karyawan
                </h1>

                <EmployeeForm
                    form={form}
                    options={options}
                    customFields={customFields}
                    hasLogin={data.has_login}
                    submitLabel="Simpan Perubahan"
                    cancelHref={EmployeeController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
