import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import HelpdeskController from '@/actions/App/Http/Controllers/Avana/HelpdeskController';
import { AIcon, C } from '@/lib/avana';
import { HelpdeskForm } from './helpdesk-form';
import { emptyTicketForm } from './types';
import type {
    EmployeeOption,
    FlashProps,
    TicketFormData,
    UserOption,
} from './types';

interface HelpdeskCreateProps {
    employees: EmployeeOption[];
    users: UserOption[];
}

export default function HelpdeskCreate({
    employees,
    users,
}: HelpdeskCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<TicketFormData>({ ...emptyTicketForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(HelpdeskController.store());
    };

    return (
        <>
            <Head title="Buat Tiket" />
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
                        href={HelpdeskController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Helpdesk
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buat Tiket</span>
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
                    Buat Tiket Baru
                </h1>

                <HelpdeskForm
                    form={form}
                    employees={employees}
                    users={users}
                    submitLabel="Simpan Tiket"
                    submitIcon="plus"
                    cancelHref={HelpdeskController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
