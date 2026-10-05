let googleMapsPromise: Promise<typeof google> | null = null;

function mapsReady(): boolean {
    return (
        typeof window.google?.maps?.Map === 'function' &&
        typeof window.google.maps.Marker === 'function' &&
        typeof window.google.maps.DirectionsService === 'function'
    );
}

/** Load Maps once and resolve only after its constructors are available. */
export function loadGoogleMaps(): Promise<typeof google> {
    if (typeof window === 'undefined') {
        return Promise.reject(
            new Error('Google Maps hanya tersedia di browser.'),
        );
    }

    if (mapsReady()) {
        return Promise.resolve(window.google);
    }

    if (googleMapsPromise) {
        return googleMapsPromise;
    }

    const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

    if (!apiKey) {
        return Promise.reject(
            new Error('VITE_GOOGLE_MAPS_API_KEY belum dikonfigurasi.'),
        );
    }

    googleMapsPromise = new Promise<typeof google>((resolve, reject) => {
        const finish = (): void => {
            if (mapsReady()) {
                resolve(window.google);
            } else {
                reject(new Error('Google Maps gagal dimuat lengkap.'));
            }
        };

        if (typeof window.google?.maps?.importLibrary === 'function') {
            void Promise.all([
                window.google.maps.importLibrary('maps'),
                window.google.maps.importLibrary('marker'),
                window.google.maps.importLibrary('routes'),
            ]).then(finish, () =>
                reject(new Error('Google Maps library gagal dimuat.')),
            );

            return;
        }

        const callbackWindow = window as typeof window & {
            __avanaGoogleMapsReady?: () => void;
        };
        const script = document.createElement('script');

        callbackWindow.__avanaGoogleMapsReady = () => {
            delete callbackWindow.__avanaGoogleMapsReady;
            finish();
        };

        script.async = true;
        script.dataset.googleMaps = 'true';
        script.src =
            `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}` +
            '&v=weekly&loading=async&libraries=maps,marker,routes&callback=__avanaGoogleMapsReady';
        script.onerror = () => {
            delete callbackWindow.__avanaGoogleMapsReady;
            script.remove();
            reject(new Error('Google Maps gagal dimuat.'));
        };
        document.head.appendChild(script);
    }).catch((error: unknown) => {
        googleMapsPromise = null;

        throw error;
    });

    return googleMapsPromise;
}
