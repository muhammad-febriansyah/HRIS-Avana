import { Head, usePage } from '@inertiajs/react';
import { Container, Reveal } from '@/components/marketing/reveal';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';

/** Static terms of service — same static-page pattern as the privacy policy. */
export default function TermsOfService() {
    const { website } = usePage().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';

    return (
        <>
            <Head title={`Syarat &amp; Ketentuan — ${brand}`} />

            <div className="min-h-dvh bg-white font-sans text-[#1A2333] antialiased">
                <SiteNavbar brand={brand} logo={logo} anchorPrefix="/" />

                <main className="py-16 lg:py-20">
                    <Container className="max-w-3xl">
                        <Reveal>
                            <h1 className="text-3xl font-bold text-avana-navy sm:text-4xl">
                                Syarat &amp; Ketentuan
                            </h1>
                            <p className="mt-3 text-sm text-avana-muted">
                                Terakhir diperbarui: Agustus 2026
                            </p>
                        </Reveal>

                        <Reveal delay={0.05}>
                            <div className="article-body">
                                <p>
                                    Dengan menggunakan {brand}, perusahaan
                                    (&quot;Tenant&quot;) dan penggunanya
                                    dianggap menyetujui syarat dan ketentuan
                                    berikut.
                                </p>

                                <h2>Penggunaan layanan</h2>
                                <ul>
                                    <li>
                                        Tenant bertanggung jawab atas keakuratan
                                        data yang diinput ke dalam sistem,
                                        termasuk data karyawan dan komponen
                                        payroll.
                                    </li>
                                    <li>
                                        Akun pengguna bersifat personal dan
                                        tidak boleh dibagikan ke pihak lain di
                                        luar organisasi Tenant.
                                    </li>
                                    <li>
                                        Fitur yang tersedia mengikuti paket
                                        langganan yang aktif; sebagian fitur
                                        (mis. Live Tracking, AI Assistant)
                                        perlu diaktifkan terlebih dahulu oleh
                                        admin tenant.
                                    </li>
                                </ul>

                                <h2>Langganan &amp; pembayaran</h2>
                                <p>
                                    Rincian harga dan komponen yang termasuk
                                    dalam paket dijelaskan pada halaman{' '}
                                    <a
                                        href="/#harga"
                                        className="text-avana-blue"
                                    >
                                        Harga
                                    </a>
                                    . Perubahan paket atau jumlah pengguna
                                    dapat memengaruhi tagihan periode
                                    berikutnya.
                                </p>

                                <h2>Data milik Tenant</h2>
                                <p>
                                    Data yang diinput Tenant (data karyawan,
                                    payroll, dokumen) tetap menjadi milik
                                    Tenant. Kebijakan penyimpanan dan
                                    perlindungan data dijelaskan lebih rinci
                                    pada{' '}
                                    <a
                                        href="/kebijakan-privasi"
                                        className="text-avana-blue"
                                    >
                                        Kebijakan Privasi
                                    </a>
                                    .
                                </p>

                                <h2>Perubahan ketentuan</h2>
                                <p>
                                    Ketentuan ini dapat diperbarui sewaktu-
                                    waktu. Perubahan signifikan akan
                                    diinformasikan kepada admin tenant melalui
                                    kanal kontak yang terdaftar.
                                </p>

                                <h2>Kontak</h2>
                                <p>
                                    Pertanyaan seputar ketentuan layanan dapat
                                    disampaikan ke{' '}
                                    {website.contact.email ??
                                        'kontak resmi AvanaHR'}
                                    .
                                </p>
                            </div>
                        </Reveal>
                    </Container>
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />

                <WhatsAppFab />
            </div>
        </>
    );
}
