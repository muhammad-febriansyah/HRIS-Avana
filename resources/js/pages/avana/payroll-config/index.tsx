import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, C, card } from '@/lib/avana';
import BpjsTab from './bpjs-tab';
import Pph21Tab from './pph21-tab';
import TaxProfileTab from './tax-profile-tab';
import { SECTIONS } from './types';
import type { FlashProps, PayrollConfigProps } from './types';

export default function PayrollConfig({
    programs,
    ptkpRates,
    pkpRates,
    profileStats,
    taxProfiles,
    taxSubjects,
    ptkpStatuses,
    settings,
}: PayrollConfigProps) {
    const { flash } = usePage<FlashProps>().props;

    const [activeKey, setActiveKey] = useState<
        'bpjs' | 'pph21' | 'tax-profile'
    >('bpjs');

    const toggleSegregation = (value: boolean) => {
        router.put(
            '/avana/payroll/konfigurasi/settings',
            { enforce_payroll_segregation: value },
            { preserveScroll: true },
        );
    };

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    return (
        <>
            <Head title="Konfigurasi BPJS & Pajak" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
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
                        <span>Payroll</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>BPJS &amp; Pajak</span>
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
                        Konfigurasi BPJS &amp; Pajak
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Kelola program iuran BPJS serta Tarif PTKP &amp; PKP
                        progresif (PPh 21 Pasal 17).
                    </div>
                </div>

                {/* Kontrol payroll — segregation of duties */}
                <div
                    style={{
                        ...card,
                        padding: '16px 20px',
                        marginBottom: 20,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 16,
                        flexWrap: 'wrap',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            gap: 12,
                            alignItems: 'flex-start',
                        }}
                    >
                        <AIcon
                            name="shield-check"
                            size={20}
                            color={C.primary}
                        />
                        <div>
                            <div
                                style={{
                                    fontSize: 14.5,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Segregation of Duties
                            </div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    marginTop: 2,
                                    maxWidth: 560,
                                }}
                            >
                                Jika aktif, pengguna yang menjalankan payroll
                                tidak boleh menyetujui hasilnya sendiri —
                                approval harus dari pengguna lain.
                            </div>
                        </div>
                    </div>
                    <label
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 8,
                            cursor: 'pointer',
                            fontSize: 13.5,
                            fontWeight: 600,
                            color: settings.enforce_payroll_segregation
                                ? C.green
                                : C.muted,
                        }}
                    >
                        <input
                            type="checkbox"
                            checked={settings.enforce_payroll_segregation}
                            onChange={(e) =>
                                toggleSegregation(e.target.checked)
                            }
                        />
                        {settings.enforce_payroll_segregation
                            ? 'Aktif'
                            : 'Nonaktif'}
                    </label>
                </div>

                {/* Stat strip */}
                <div
                    style={{
                        display: 'flex',
                        gap: 14,
                        flexWrap: 'wrap',
                        marginBottom: 20,
                    }}
                >
                    {[
                        {
                            label: 'Program BPJS',
                            value: programs.length,
                            icon: 'shield-plus',
                        },
                        {
                            label: 'Tarif PTKP / PKP',
                            value: ptkpRates.length + pkpRates.length,
                            icon: 'percent',
                        },
                        {
                            label: 'Profil BPJS Karyawan',
                            value: profileStats.bpjs_profiles,
                            icon: 'users',
                        },
                        {
                            label: 'Profil Pajak Karyawan',
                            value: profileStats.tax_profiles,
                            icon: 'receipt',
                        },
                    ].map((stat) => (
                        <div
                            key={stat.label}
                            style={{
                                ...card,
                                flex: '1 1 180px',
                                padding: '14px 16px',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <div
                                style={{
                                    width: 38,
                                    height: 38,
                                    borderRadius: 9,
                                    background: 'rgba(47,84,201,.1)',
                                    color: C.primary,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon
                                    name={stat.icon}
                                    size={18}
                                    color={C.primary}
                                />
                            </div>
                            <div>
                                <div
                                    style={{
                                        fontSize: 19,
                                        fontWeight: 700,
                                        color: C.navy,
                                    }}
                                >
                                    {stat.value.toLocaleString('id-ID')}
                                </div>
                                <div style={{ fontSize: 12, color: C.muted }}>
                                    {stat.label}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Tab bar */}
                <div
                    style={{
                        display: 'flex',
                        gap: 4,
                        flexWrap: 'wrap',
                        borderBottom: `1px solid ${C.border}`,
                        marginBottom: 18,
                    }}
                >
                    {SECTIONS.map((item) => {
                        const active = item.key === activeKey;

                        return (
                            <button
                                key={item.key}
                                onClick={() => setActiveKey(item.key)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    padding: '11px 14px',
                                    background: 'none',
                                    border: 'none',
                                    borderBottom: `2px solid ${active ? C.primary : 'transparent'}`,
                                    marginBottom: -1,
                                    fontSize: 13.5,
                                    fontWeight: active ? 600 : 500,
                                    color: active ? C.primary : C.muted,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                <AIcon
                                    name={item.icon}
                                    size={16}
                                    color={active ? C.primary : C.faint}
                                />
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {/* Active section */}
                {activeKey === 'bpjs' ? (
                    <BpjsTab programs={programs} />
                ) : activeKey === 'pph21' ? (
                    <Pph21Tab ptkpRates={ptkpRates} pkpRates={pkpRates} />
                ) : (
                    <TaxProfileTab
                        taxProfiles={taxProfiles}
                        taxSubjects={taxSubjects}
                        ptkpStatuses={ptkpStatuses}
                    />
                )}
            </div>
        </>
    );
}
