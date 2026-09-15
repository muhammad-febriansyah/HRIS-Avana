export type PublicProductNavigationItem = {
    label: string;
    slug: string;
};

export type PublicProductNavigationGroup = {
    label: string;
    moduleTitle: string;
    slug: string;
    items: PublicProductNavigationItem[];
};

export const PUBLIC_PRODUCT_NAVIGATION: PublicProductNavigationGroup[] = [
    {
        label: 'HR Core',
        moduleTitle: 'Core HR',
        slug: 'core-hr',
        items: [
            { label: 'Core HR', slug: 'core-hr' },
            { label: 'Struktur Organisasi', slug: 'struktur-organisasi' },
            { label: 'Data Karyawan', slug: 'data-karyawan' },
            { label: 'Administrasi Karier', slug: 'administrasi-karier' },
            { label: 'Helpdesk HR', slug: 'hr-helpdesk' },
            { label: 'ESS', slug: 'ess' },
            {
                label: 'Otomatisasi Alur Kerja',
                slug: 'otomatisasi-alur-kerja',
            },
        ],
    },
    {
        label: 'Payroll',
        moduleTitle: 'Payroll',
        slug: 'payroll',
        items: [{ label: 'Payroll', slug: 'payroll' }],
    },
    {
        label: 'Pelatihan',
        moduleTitle: 'Pelatihan',
        slug: 'pelatihan',
        items: [{ label: 'Pelatihan', slug: 'pelatihan' }],
    },
    {
        label: 'Recruitment',
        moduleTitle: 'Recruitment',
        slug: 'rekrutmen',
        items: [{ label: 'Recruitment', slug: 'rekrutmen' }],
    },
    {
        label: 'Time Management',
        moduleTitle: 'Time Management',
        slug: 'time-management',
        items: [{ label: 'Time Management', slug: 'time-management' }],
    },
    {
        label: 'Manajemen Talenta',
        moduleTitle: 'Manajemen Talenta',
        slug: 'manajemen-talenta',
        items: [{ label: 'Manajemen Talenta', slug: 'manajemen-talenta' }],
    },
    {
        label: 'Compensation & Benefits',
        moduleTitle: 'Compensation & Benefits',
        slug: 'compensation-benefits',
        items: [
            {
                label: 'Compensation & Benefits',
                slug: 'compensation-benefits',
            },
        ],
    },
    {
        label: 'Expenses & Reimbursement',
        moduleTitle: 'Settlement',
        slug: 'reimbursement',
        items: [
            {
                label: 'Expenses & Reimbursement',
                slug: 'reimbursement',
            },
        ],
    },
    {
        label: 'Loans Management',
        moduleTitle: 'Loans Management',
        slug: 'loans-management',
        items: [{ label: 'Loans Management', slug: 'loans-management' }],
    },
    {
        label: 'KPI',
        moduleTitle: 'KPI',
        slug: 'kpi',
        items: [{ label: 'KPI', slug: 'kpi' }],
    },
    {
        label: 'AI & Analytics',
        moduleTitle: 'AI & Analytics',
        slug: 'ai-analytics',
        items: [{ label: 'AI & Analytics', slug: 'ai-analytics' }],
    },
];

export function findPublicProductNavigationGroup(
    slug: string,
): PublicProductNavigationGroup | undefined {
    return PUBLIC_PRODUCT_NAVIGATION.find((group) =>
        group.items.some((item) => item.slug === slug),
    );
}
