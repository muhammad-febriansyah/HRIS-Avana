import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import EmployeeController from '@/actions/App/Http/Controllers/Avana/EmployeeController';
import { AIcon, C } from '@/lib/avana';
import { EmployeeForm } from './employee-form';
import type {
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
}

/** Map a possibly-null relation id to a string for the controlled selects. */
function relationId(relation?: { id: number } | null): string {
    return relation?.id ? String(relation.id) : '';
}

export default function EmployeesEdit({
    employee,
    options,
}: EmployeesEditProps) {
    const { data } = employee;
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<EmployeeFormData>({
        full_name: data.full_name ?? '',
        email: data.email ?? '',
        phone: data.phone ?? '',
        nik: data.nik ?? '',
        gender: data.gender ?? '',
        birth_date: data.birth_date ?? '',
        birth_place: data.birth_place ?? '',
        religion: data.religion ?? '',
        marital_status: data.marital_status ?? '',
        address: data.address ?? '',
        employment_status: data.employment_status ?? '',
        join_date: data.join_date_raw ?? '',
        branch_id: relationId(data.branch),
        department_id: relationId(data.department),
        position_id: relationId(data.position),
        job_level_id: relationId(data.job_level),
        manager_id: relationId(data.manager),
        status: data.status ?? 'active',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(EmployeeController.update(data.id));
    };

    return (
        <>
            <Head title="Ubah Karyawan" />
            <div style={{ padding: '28px 32px', maxWidth: 880 }}>
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
                        href={EmployeeController.show(data.id)}
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
                    submitLabel="Simpan Perubahan"
                    cancelHref={EmployeeController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
