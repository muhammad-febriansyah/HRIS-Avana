import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

type FlashProps = {
    toast?: FlashToast;
    success?: string | null;
    error?: string | null;
    warning?: string | null;
};

/**
 * Shows a toast for every successful Inertia visit that carries flash data —
 * `flash.toast` (typed `{type, message}`) or the plain `flash.success` /
 * `flash.error` / `flash.warning` strings most pages flash from a controller
 * redirect (see `HandleInertiaRequests`).
 *
 * Wired to the router's `success` event rather than a `useEffect` keyed off
 * `usePage().props.flash` on purpose: two saves in a row often flash the
 * exact same message ("Pengaturan website berhasil disimpan" twice), and a
 * dependency array sees that as "unchanged" — the second toast would
 * silently never show. An event fires once per visit regardless of whether
 * its payload matches the last one.
 */
export function useFlashToast(): void {
    useEffect(() => {
        return router.on('success', (event) => {
            const flash = (
                event as unknown as {
                    detail: { page: { props: { flash?: FlashProps } } };
                }
            ).detail?.page?.props?.flash;

            if (!flash) {
                return;
            }

            if (flash.toast) {
                toast[flash.toast.type](flash.toast.message);

                return;
            }

            if (flash.success) {
                toast.success(flash.success);
            }

            if (flash.error) {
                toast.error(flash.error);
            }

            if (flash.warning) {
                toast.warning(flash.warning);
            }
        });
    }, []);
}
