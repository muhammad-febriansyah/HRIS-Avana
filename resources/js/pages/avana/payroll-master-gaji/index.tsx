import { Head, Link } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import EmployeeSalaryController from '@/actions/App/Http/Controllers/Avana/EmployeeSalaryController';
import SalaryAssignmentController from '@/actions/App/Http/Controllers/Avana/SalaryAssignmentController';
import SalaryMasterController from '@/actions/App/Http/Controllers/Avana/SalaryMasterController';
import { AIcon, C } from '@/lib/avana';
import GajiKaryawanTab from '../payroll-gaji-karyawan/index';
import MassAssignmentTab from '../payroll-mass-assignment/index';
import MasterGajiTab from './master-tab';

/**
 * Master Gaji, Gaji Karyawan and Penetapan Gaji Massal are one screen with
 * three tabs: they are the same job in sequence — define the template, adjust
 * one person, roll it out to many — and splitting them across three menu items
 * made the order something HR had to remember rather than something the screen
 * shows.
 *
 * Each tab keeps its own URL, so a link, a redirect after saving and the browser
 * back button all still land exactly where they used to.
 */
type Tab = 'master' | 'karyawan' | 'massal';

interface Props {
    tab: Tab;
    // Whatever the active tab's controller supplied; each tab body types its own.
    [key: string]: unknown;
}

const TABS: { id: Tab; label: string; icon: string; href: () => string }[] = [
    {
        id: 'master',
        label: 'Master Gaji',
        icon: 'file-cog',
        href: () => SalaryMasterController.index().url,
    },
    {
        id: 'karyawan',
        label: 'Gaji Karyawan',
        icon: 'user-cog',
        href: () => EmployeeSalaryController.index().url,
    },
    {
        id: 'massal',
        label: 'Penetapan Massal',
        icon: 'users',
        href: () => SalaryAssignmentController.index().url,
    },
];

const TITLES: Record<Tab, string> = {
    master: 'Master Gaji',
    karyawan: 'Gaji Karyawan',
    massal: 'Penetapan Gaji Massal',
};

const SUBTITLES: Record<Tab, string> = {
    master: 'Template gaji yang dipakai bersama: komponen apa saja yang dibayar dan berapa nominalnya.',
    karyawan:
        'Gaji satu karyawan: ikut template, lalu ubah nominal yang memang berbeda untuk orang ini.',
    massal: 'Terapkan satu template ke banyak karyawan sekaligus — lewat preview, sebelum apa pun disimpan.',
};

const tabStyle = (active: boolean): CSSProperties => ({
    display: 'inline-flex',
    alignItems: 'center',
    gap: 7,
    padding: '9px 15px',
    borderRadius: 9,
    border: `1px solid ${active ? C.primary : C.line}`,
    background: active ? C.primary : '#fff',
    color: active ? '#fff' : C.text,
    fontSize: 13,
    fontWeight: 600,
    textDecoration: 'none',
    whiteSpace: 'nowrap',
});

export default function MasterGajiPage(props: Props) {
    const tab = (props.tab ?? 'master') as Tab;

    return (
        <>
            <Head title={TITLES[tab]} />
            <div style={{ padding: '28px 32px' }}>
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
                    <span style={{ color: C.muted }}>{TITLES[tab]}</span>
                </div>

                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Master Gaji
                </h1>
                <div
                    style={{ fontSize: 13.5, color: C.muted, marginBottom: 16 }}
                >
                    {SUBTITLES[tab]}
                </div>

                <div
                    style={{
                        display: 'flex',
                        gap: 8,
                        marginBottom: 18,
                        flexWrap: 'wrap',
                    }}
                >
                    {TABS.map((row) => (
                        <Link
                            key={row.id}
                            href={row.href()}
                            style={tabStyle(row.id === tab)}
                        >
                            <AIcon
                                name={row.icon}
                                size={15}
                                color={row.id === tab ? '#fff' : C.muted}
                            />
                            {row.label}
                        </Link>
                    ))}
                </div>

                {tab === 'master' && (
                    <MasterGajiTab
                        {...(props as unknown as React.ComponentProps<
                            typeof MasterGajiTab
                        >)}
                    />
                )}
                {tab === 'karyawan' && (
                    <GajiKaryawanTab
                        {...(props as unknown as React.ComponentProps<
                            typeof GajiKaryawanTab
                        >)}
                    />
                )}
                {tab === 'massal' && (
                    <MassAssignmentTab
                        {...(props as unknown as React.ComponentProps<
                            typeof MassAssignmentTab
                        >)}
                    />
                )}
            </div>
        </>
    );
}
