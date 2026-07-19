<?php

namespace App\Support;

use App\Models\Company;
use App\Models\EmailSetting;
use App\Models\WebsiteSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves the branding (logo, name, contact footer) used in outbound emails,
 * scoped like {@see EmailSetting}: a tenant id yields that tenant's
 * own company branding (their logo + footer about them), while null yields the
 * platform default managed by the super admin in the website settings.
 *
 * Every field falls back to the platform default and finally to the bundled
 * AvanaHR logo so an email always renders a header and footer.
 */
final class MailBranding
{
    /**
     * The AvanaHR brand primary colour used for the email header and buttons.
     */
    public const PRIMARY_COLOR = '#2F54C9';

    /**
     * Path (relative to public/) of the bundled logo used when no scope has
     * uploaded one.
     */
    private const FALLBACK_LOGO = 'avana/logo-full.png';

    /**
     * Branding for an email sent in the given scope.
     *
     * @return array{
     *     brand_name: string,
     *     logo_url: string,
     *     address: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     primary_color: string
     * }
     */
    public static function for(?int $tenantId): array
    {
        if ($tenantId !== null) {
            $company = Company::forTenant($tenantId)->first();

            if ($company !== null) {
                return self::compose(
                    brandName: $company->name ?: $company->legal_name,
                    logoUrl: self::diskUrl($company->logo_path),
                    address: $company->address,
                    phone: $company->phone,
                    email: $company->email,
                );
            }
        }

        return self::platform();
    }

    /**
     * The platform (super admin) default branding from the website settings.
     *
     * @return array{brand_name: string, logo_url: string, address: ?string, phone: ?string, email: ?string, primary_color: string}
     */
    private static function platform(): array
    {
        $settings = WebsiteSetting::current();

        return self::compose(
            brandName: $settings->site_name,
            logoUrl: $settings->logoUrl(),
            address: $settings->contact_address,
            phone: $settings->contact_phone,
            email: $settings->contact_email,
        );
    }

    /**
     * Fill blanks with platform-safe defaults so the template always renders.
     *
     * @return array{brand_name: string, logo_url: string, address: ?string, phone: ?string, email: ?string, primary_color: string}
     */
    private static function compose(?string $brandName, ?string $logoUrl, ?string $address, ?string $phone, ?string $email): array
    {
        return [
            'brand_name' => $brandName ?: 'AvanaHR',
            'logo_url' => $logoUrl ?: asset(self::FALLBACK_LOGO),
            'address' => $address ?: null,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'primary_color' => self::PRIMARY_COLOR,
        ];
    }

    /**
     * Absolute URL for a public-disk path, or null when unset.
     */
    private static function diskUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
