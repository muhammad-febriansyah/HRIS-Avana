import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

test('attendance monitor uses marker popups without demo banner', () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/pages/avana/absensi/monitor.tsx',
            import.meta.url,
        ),
        'utf8',
    );
    const syntax = ts.createSourceFile(
        'monitor.tsx',
        source,
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TSX,
    );
    let mapElement;

    function visit(node) {
        if (
            ts.isJsxSelfClosingElement(node) &&
            node.tagName.getText(syntax) === 'LocationMap'
        ) {
            mapElement = node;
        }

        ts.forEachChild(node, visit);
    }

    visit(syntax);

    assert.ok(mapElement);
    assert.equal(
        mapElement.attributes.properties.some(
            (attribute) => attribute.name?.text === 'labels',
        ),
        false,
    );
    assert.doesNotMatch(
        source,
        /Demo Google Maps — aktif otomatis saat data kosong di localhost/,
    );
});
