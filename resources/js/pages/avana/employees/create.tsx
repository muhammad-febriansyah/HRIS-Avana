import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import EmployeeController from '@/actions/App/Http/Controllers/Avana/EmployeeController';
import { AIcon, C } from '@/lib/avana';
import { EmployeeForm } from './employee-form';
import type {
    EmployeeFormData,
    EmployeeFormOptions,
    FlashProps,
} from './types';

interface EmployeesCreateProps {
    options: EmployeeFormOptions;
}

export default function EmployeesCreate({ options }: EmployeesCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<EmployeeFormData>({
        full_name: '',
        email: '',
        phone: '',
        nik: '',
        gender: '',
        birth_date: '',
        birth_place: '',
        religion: '',
        marital_status: '',
        address: '',
        employment_status: '',
        join_date: '',
        branch_id: '',
        department_id: '',
        position_id: '',
        job_level_id: '',
        manager_id: '',
        status: 'active',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(EmployeeController.store());
    };

    return (
        <>
            <Head title="Tambah Karyawan" />
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
                    <span style={{ color: C.muted }}>Tambah Karyawan</span>
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
                    Tambah Karyawan Baru
                </h1>

                <EmployeeForm
                    form={form}
                    options={options}
                    submitLabel="Simpan Karyawan"
                    cancelHref={EmployeeController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
