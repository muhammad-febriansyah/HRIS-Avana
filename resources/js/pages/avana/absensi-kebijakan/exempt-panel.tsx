import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import AttendancePolicyController from '@/actions/App/Http/Controllers/Avana/AttendancePolicyController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnSave, C, card } from '@/lib/avana';

/** One employee in the "tidak wajib absen" list or its picker. */
export interface ExemptEmployee {
    id: number;
    route_key: string;
    name: string;
    employee_number: string | null;
}

export interface ExemptEmployees {
    list: ExemptEmployee[];
    candidates: ExemptEmployee[];
}

/**
 * Employees the company does not expect at the clock — an owner, a director.
 * They are never reminded, never listed as "belum absen" and never marked
 * Alpa, so payroll never docks them for a day off the app.
 */
export function ExemptPanel({ employees }: { employees: ExemptEmployees }) {
    const form = useForm({ employee_ids: [] as number[] });
    const selected =
        form.data.employee_ids.length > 0
            ? String(form.data.employee_ids[0])
            : '';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(AttendancePolicyController.storeExemption.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                toast.success('Karyawan ditandai tidak wajib absen');
            },
        });
    };

    const remove = (employee: ExemptEmployee) => {
        router.delete(
            AttendancePolicyController.destroyExemption.url(employee.route_key),
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(`${employee.name} kembali wajib absen`),
            },
        );
    };

    const label = (employee: ExemptEmployee) =>
        employee.employee_number
            ? `${employee.name} (${employee.employee_number})`
            : employee.name;

    return (
        <div style={{ ...card, padding: 20, marginBottom: 16 }}>
            <div
                style={{
                    fontSize: 15,
                    fontWeight: 700,
                    color: C.text,
                    marginBottom: 4,
                }}
            >
                Tidak Wajib Absen
            </div>
            <div style={{ fontSize: 13, color: C.muted, marginBottom: 16 }}>
                Untuk pemilik atau direksi yang tidak absen lewat aplikasi.
                Mereka tidak menerima pengingat clock-in, tidak muncul sebagai
                &ldquo;Belum Absen&rdquo;, tidak dicatat Alpa, dan gajinya tidak
                kena Potongan Alpa.
            </div>

            <form
                onSubmit={submit}
                style={{
                    display: 'flex',
                    gap: 10,
                    alignItems: 'flex-start',
                    flexWrap: 'wrap',
                    marginBottom: 16,
                }}
            >
                <div style={{ flex: '1 1 260px', minWidth: 0 }}>
                    <SearchableSelect
                        value={selected}
                        onChange={(value: string) =>
                            form.setData(
                                'employee_ids',
                                value ? [Number(value)] : [],
                            )
                        }
                        options={employees.candidates.map((employee) => ({
                            value: String(employee.id),
                            label: label(employee),
                        }))}
                        placeholder="Pilih karyawan…"
                        searchPlaceholder="Cari nama atau NIK karyawan…"
                        allowClear
                    />
                    {form.errors.employee_ids && (
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.red,
                                marginTop: 6,
                            }}
                        >
                            {form.errors.employee_ids}
                        </div>
                    )}
                </div>
                <button
                    type="submit"
                    disabled={form.processing || selected === ''}
                    style={{
                        ...btnSave,
                        height: 40,
                        opacity: form.processing || selected === '' ? 0.6 : 1,
                    }}
                >
                    <AIcon name="plus" size={15} /> Tambahkan
                </button>
            </form>

            {employees.list.length === 0 ? (
                <div style={{ fontSize: 13, color: C.faint }}>
                    Belum ada — semua karyawan berakun wajib absen.
                </div>
            ) : (
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 8,
                    }}
                >
                    {employees.list.map((employee) => (
                        <div
                            key={employee.id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 12,
                                padding: '10px 12px',
                                borderRadius: 10,
                                border: `1px solid ${C.border}`,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.text,
                                    minWidth: 0,
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                }}
                            >
                                {label(employee)}
                            </span>
                            <button
                                type="button"
                                onClick={() => remove(employee)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    padding: '7px 12px',
                                    borderRadius: 8,
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    color: C.red,
                                    fontSize: 12.5,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    flexShrink: 0,
                                }}
                            >
                                <AIcon name="trash-2" size={14} />
                                Hapus
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
