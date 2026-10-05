import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';

const source = readFileSync(
    new URL('../../resources/js/lib/google-maps.ts', import.meta.url),
    'utf8',
).replace('import.meta.env.VITE_GOOGLE_MAPS_API_KEY', '"test-key"');
const { outputText } = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
});

function createLoader(window) {
    const scripts = [];
    const exports = {};
    const document = {
        createElement: () => ({ dataset: {}, remove() {} }),
        head: { appendChild: (script) => scripts.push(script) },
    };

    runInNewContext(outputText, { exports, window, document, Promise, Error });

    return { loadGoogleMaps: exports.loadGoogleMaps, scripts };
}

test('waits for callback and constructors before resolving Maps', async () => {
    const window = {};
    const { loadGoogleMaps, scripts } = createLoader(window);
    const loading = loadGoogleMaps();

    assert.equal(scripts.length, 1);
    assert.match(
        scripts[0].src,
        /loading=async.*callback=__avanaGoogleMapsReady/,
    );
    assert.equal(typeof window.__avanaGoogleMapsReady, 'function');

    window.google = {
        maps: {
            Map: class {},
            Marker: class {},
            DirectionsService: class {},
        },
    };
    window.__avanaGoogleMapsReady();

    assert.equal(await loading, window.google);
    assert.equal(window.__avanaGoogleMapsReady, undefined);
});

test('imports missing libraries when Google bootstrap exists', async () => {
    const imported = [];
    const window = {
        google: {
            maps: {
                importLibrary: async (name) => {
                    imported.push(name);

                    if (name === 'maps') {
                        window.google.maps.Map = class {};
                    }

                    if (name === 'marker') {
                        window.google.maps.Marker = class {};
                    }

                    if (name === 'routes') {
                        window.google.maps.DirectionsService = class {};
                    }
                },
            },
        },
    };
    const { loadGoogleMaps, scripts } = createLoader(window);

    assert.equal(await loadGoogleMaps(), window.google);
    assert.deepEqual(imported, ['maps', 'marker', 'routes']);
    assert.equal(scripts.length, 0);
});
