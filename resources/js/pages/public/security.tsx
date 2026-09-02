import { Head, usePage } from '@inertiajs/react';
import { FaqSection } from '@/components/marketing/faq-section';
import { FinalCta } from '@/components/marketing/final-cta';
import { Container, Reveal } from '@/components/marketing/reveal';
import {
    ACCOUNT_SECTION,
    AUDIT_SECTION,
    BACKUP_SECTION,
    CLOSING_KEYWORDS,
    FILES_SECTION,
    FOUR_EYES_SECTION,
    MOBILE_SECTION,
    RBAC_SECTION,
    SECURITY_FAQS,
} from '@/components/marketing/security/content';
import { EncryptionSection } from '@/components/marketing/security/encryption-section';
import { FeatureSection } from '@/components/marketing/security/feature-section';
import { HeadersShowcase } from '@/components/marketing/security/headers-showcase';
import { RolloutStatus } from '@/components/marketing/security/rollout-status';
import { SecurityHero } from '@/components/marketing/security/security-hero';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';

const DESCRIPTION =
    'Autentikasi dua faktor, enkripsi data pribadi, kontrol hak akses, pemantauan perangkat, audit trail, dan four-eyes approval — lapisan keamanan Avana HR.';

const CHIP_CLASS =
    'rounded-lg border border-[#D8E1F5] bg-white px-3 py-1.5 text-[12.5px] font-medium text-[#3B455C]';

/**
 * Public marketing page for security & data protection.
 *
 * Chrome (navbar, footer, floating WhatsApp action) and the closing CTA and
 * FAQ blocks are the same components the landing page uses, so the page
 * reads as part of AvanaHR rather than a second site — same pattern as
 * `public/live-tracking`.
 */
export default function Security() {
    const { website } = usePage().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const title = `Keamanan — ${brand}`;

    return (
        <>
            <Head title="Keamanan">
                <meta name="description" content={DESCRIPTION} />
                <meta property="og:type" content="website" />
                <meta property="og:title" content={title} />
                <meta property="og:description" content={DESCRIPTION} />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={title} />
                <meta name="twitter:description" content={DESCRIPTION} />
            </Head>

            <div
                id="top"
                className="min-h-dvh overflow-x-clip bg-white font-sans text-[#1A2333] antialiased"
            >
                <SiteNavbar
                    brand={brand}
                    logo={logo}
                    anchorPrefix="/"
                    activePage="security"
                />

                <main>
                    <SecurityHero />

                    <FeatureSection
                        id="akun-sesi"
                        eyebrow="1. Keamanan Akun & Sesi"
                        title="Akses Akun yang Lebih Aman di Setiap Perangkat."
                        description="Perlindungan sejak login hingga sesi berakhir — deteksi login mencurigakan sampai catatan setiap perangkat."
                        points={ACCOUNT_SECTION.points}
                    />

                    <HeadersShowcase />

                    <EncryptionSection />

                    <FeatureSection
                        id="backup-anomali"
                        tone="muted"
                        eyebrow="4. Backup & Deteksi Anomali"
                        title="Menjaga Ketersediaan Data, Sekaligus Mendeteksi Aktivitas Mencurigakan."
                        description="Backup terjadwal menjaga data tetap aman, sambil memantau pola aktivitas yang mencurigakan."
                        image={BACKUP_SECTION.image}
                        imageAlt={BACKUP_SECTION.imageAlt}
                        imageSide="left"
                        points={BACKUP_SECTION.points}
                    />

                    <FeatureSection
                        id="file-privat"
                        eyebrow="5. File & Dokumen Privat"
                        title="Dokumen Sensitif Tidak Tersedia Lewat Akses Publik Langsung."
                        description="Dokumen pribadi disimpan di area privat, hanya bisa dibuka lewat jalur aplikasi yang tervalidasi."
                        image={FILES_SECTION.image}
                        imageAlt={FILES_SECTION.imageAlt}
                        imageSide="right"
                        points={FILES_SECTION.points}
                    >
                        <ul className="flex flex-wrap gap-2">
                            {FILES_SECTION.fileTypes.map((type) => (
                                <li key={type} className={CHIP_CLASS}>
                                    {type}
                                </li>
                            ))}
                        </ul>
                    </FeatureSection>

                    <FeatureSection
                        id="hak-akses"
                        tone="muted"
                        eyebrow="6. Role-Based Access Control"
                        title="Setiap Pengguna Hanya Mendapatkan Akses yang Dibutuhkan."
                        description="Akses diatur hingga tingkat aksi per modul lewat matrix permission — bukan sekadar nama role."
                        image={RBAC_SECTION.image}
                        imageAlt={RBAC_SECTION.imageAlt}
                        imageSide="left"
                        points={RBAC_SECTION.points}
                    >
                        <ul className="flex flex-wrap gap-2">
                            {RBAC_SECTION.actions.map((action) => (
                                <li key={action} className={CHIP_CLASS}>
                                    {action}
                                </li>
                            ))}
                        </ul>
                    </FeatureSection>

                    <FeatureSection
                        id="four-eyes"
                        eyebrow="7. Four-Eyes Approval"
                        title="Transaksi Penting Butuh Kontrol Lebih dari Satu Pihak."
                        description="Satu orang tidak bisa mengontrol penuh satu transaksi — sistem memeriksa siapa yang bertindak di tiap tahap."
                        image={FOUR_EYES_SECTION.image}
                        imageAlt={FOUR_EYES_SECTION.imageAlt}
                        imageSide="right"
                        points={FOUR_EYES_SECTION.points}
                    >
                        <ul className="flex flex-wrap gap-2">
                            {FOUR_EYES_SECTION.flows.map((flow) => (
                                <li key={flow} className={CHIP_CLASS}>
                                    {flow}
                                </li>
                            ))}
                        </ul>
                    </FeatureSection>

                    <FeatureSection
                        id="audit-trail"
                        tone="muted"
                        eyebrow="8. Audit Trail & Accountability"
                        title="Setiap Aktivitas Penting Memiliki Jejak."
                        description="Setiap aktivitas penting tercatat dan bisa ditelusuri lewat halaman Audit Trail."
                        image={AUDIT_SECTION.image}
                        imageAlt={AUDIT_SECTION.imageAlt}
                        imageSide="left"
                        points={AUDIT_SECTION.points}
                    />

                    <FeatureSection
                        id="mobile-absensi"
                        eyebrow="9. Proteksi Tambahan pada Mobile & Absensi"
                        title="Perlindungan Tidak Berhenti di Aplikasi Web."
                        description="Absensi wajah memakai face verification 1:1 — mencocokkan wajah dengan identitas terdaftar, bukan cuma deteksi wajah."
                        image={MOBILE_SECTION.image}
                        imageAlt={MOBILE_SECTION.imageAlt}
                        imageSide="right"
                        points={MOBILE_SECTION.points}
                    />

                    <RolloutStatus />

                    <section className="border-y border-[#EDF1F8] bg-[#0E1A3A] py-14">
                        <Container>
                            <Reveal className="mx-auto max-w-3xl text-center">
                                <h2 className="text-[22px] leading-snug font-bold text-white sm:text-[26px]">
                                    Data HR Anda Layak Mendapatkan
                                    Perlindungan yang Serius.
                                </h2>
                                <p className="mt-3 text-[14.5px] leading-relaxed text-blue-100/80">
                                    Keamanan menjadi bagian dari bagaimana
                                    sistem bekerja — bukan sekadar fitur
                                    tambahan.
                                </p>
                                <ul className="mt-6 flex flex-wrap items-center justify-center gap-x-2 gap-y-2 text-[12.5px] font-medium text-blue-100/70">
                                    {CLOSING_KEYWORDS.map((keyword, i) => (
                                        <li
                                            key={keyword}
                                            className="flex items-center gap-2"
                                        >
                                            {keyword}
                                            {i < CLOSING_KEYWORDS.length - 1 && (
                                                <span
                                                    aria-hidden
                                                    className="text-blue-100/30"
                                                >
                                                    &middot;
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </Reveal>
                        </Container>
                    </section>

                    <FaqSection
                        items={SECURITY_FAQS}
                        title="Pertanyaan Seputar Keamanan Avana HR"
                    />

                    <FinalCta
                        title="Siap Melihat Bagaimana Avana HR Menjaga Data Perusahaan Anda?"
                        body="Jadwalkan demo dan lihat langsung lapisan keamanan Avana HR — dari autentikasi, enkripsi, hak akses, hingga audit trail."
                        supporting="Tim kami dapat menjelaskan bagaimana setiap kontrol keamanan ini diterapkan pada kebutuhan perusahaan Anda."
                        showPrice={false}
                    />
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />

                <WhatsAppFab />
            </div>
        </>
    );
}
