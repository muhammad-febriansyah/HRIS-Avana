import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent, ClipboardEvent, CSSProperties } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    ActionBtn,
    AIcon,
    btnOut,
    btnProcess,
    btnSave,
    C,
    card,
} from '@/lib/avana';
import type { EmployeeFormOptions, FlashProps } from './types';

interface BulkRow {
    full_name: string;
    email: string;
    branch_id: string;
    department_id: string;
    position_id: string;
    employment_status: string;
    status: string;
    password: string;
    errors?: Record<string, string>;
}

/** The row fields, left-to-right, that a pasted spreadsheet block can fill. */
type PasteableKey =
    | 'full_name'
    | 'email'
    | 'branch_id'
    | 'department_id'
    | 'position_id'
    | 'employment_status'
    | 'password';

/** Whether a pasted first cell is a template header rather than a name. */
const isHeaderCell = (value: string): boolean => {
    const v = value.trim().toLowerCase();

    return v === 'nama_lengkap' || v === 'nama lengkap' || v === 'nama';
};

/** Loose email shape check for flagging obviously invalid grid entries. */
const isValidEmail = (value: string): boolean =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

const emptyRow = (): BulkRow => ({
    full_name: '',
    email: '',
    branch_id: '',
    department_id: '',
    position_id: '',
    employment_status: 'permanent',
    status: 'active',
    password: '',
});

const cell: CSSProperties = {
    height: 38,
    padding: '0 10px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.text,
    background: '#fff',
    outline: 'none',
    width: '100%',
};

const th: CSSProperties = {
    padding: '11px 10px',
    textAlign: 'left',
    fontSize: 11,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
    whiteSpace: 'nowrap',
};

/** Read the XSRF token Laravel sets as a cookie, for the fetch upload. */
const csrf = (): string =>
    decodeURIComponent(
        (document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? '',
    );

export default function EmployeesBulkCreate({
    options,
}: {
    options: EmployeeFormOptions;
}) {
    const { flash } = usePage<FlashProps>().props;
    const form = useForm<{ employees: BulkRow[] }>({
        employees: [emptyRow(), emptyRow(), emptyRow()],
    });
    const { data, setData, processing, errors } = form;
    const rows = data.employees;

    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [summary, setSummary] = useState<{
        total: number;
        valid: number;
    } | null>(null);

    // Case-insensitive name -> id lookups so pasted branch/department/position
    // labels (as typed in Excel) resolve to the option ids the form stores.
    const nameToId = (
        items: { id: number; name: string }[],
    ): Map<string, string> =>
        new Map(
            items.map((it) => [it.name.trim().toLowerCase(), String(it.id)]),
        );
    const branchMap = useMemo(
        () => nameToId(options.branches),
        [options.branches],
    );
    const departmentMap = useMemo(
        () => nameToId(options.departments),
        [options.departments],
    );
    const positionMap = useMemo(
        () => nameToId(options.positions),
        [options.positions],
    );

    // Employment status accepts the option label ("Tetap"), its stored value
    // ("permanent"), or a common Indonesian synonym.
    const employmentMap = useMemo(() => {
        const map = new Map<string, string>();

        for (const option of options.employmentStatuses) {
            map.set(option.label.trim().toLowerCase(), option.value);
            map.set(option.value.toLowerCase(), option.value);
        }

        const synonyms: Record<string, string> = {
            'masa percobaan': 'probation',
            kontrak: 'contract',
            tetap: 'permanent',
            resign: 'resigned',
        };

        for (const [key, value] of Object.entries(synonyms)) {
            map.set(key, value);
        }

        return map;
    }, [options.employmentStatuses]);

    // Client-side email checks mirroring the upload preview: flag rows whose
    // email is malformed or duplicated within the grid. DB-level "already used"
    // stays a server concern, enforced on submit.
    const emailIssues = useMemo(() => {
        const counts = new Map<string, number>();

        for (const row of data.employees) {
            const key = row.email.trim().toLowerCase();

            if (key !== '') {
                counts.set(key, (counts.get(key) ?? 0) + 1);
            }
        }

        const issues = new Map<number, string>();
        data.employees.forEach((row, index) => {
            const email = row.email.trim();

            if (email === '') {
                return;
            }

            if (!isValidEmail(email)) {
                issues.set(index, 'Format email tidak valid');
            } else if ((counts.get(email.toLowerCase()) ?? 0) > 1) {
                issues.set(index, 'Email duplikat di file');
            }
        });

        return issues;
    }, [data.employees]);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    // Map a row field to the error key the server reports it under.
    const errorKeyOf = (key: keyof BulkRow): string =>
        key === 'branch_id'
            ? 'branch'
            : key === 'department_id'
              ? 'department'
              : key === 'position_id'
                ? 'position'
                : key === 'employment_status'
                  ? 'status'
                  : key;

    const updateRow = (index: number, key: keyof BulkRow, value: string) =>
        setData(
            'employees',
            rows.map((row, i) => {
                if (i !== index) {
                    return row;
                }

                const nextErrors = { ...(row.errors ?? {}) };
                delete nextErrors[errorKeyOf(key)]; // clear the flag once the user fixes the field

                return { ...row, [key]: value, errors: nextErrors };
            }),
        );

    const addRow = () => setData('employees', [...rows, emptyRow()]);
    const removeRow = (index: number) =>
        setData(
            'employees',
            rows.filter((_, i) => i !== index),
        );

    const isBlank = (row: BulkRow): boolean =>
        row.full_name.trim() === '' && row.email.trim() === '';

    // ---- Paste from Excel / Google Sheets ----
    // Left-to-right order of the pasteable columns, matching the table headers.
    const pasteColumns: PasteableKey[] = [
        'full_name',
        'email',
        'branch_id',
        'department_id',
        'position_id',
        'employment_status',
        'password',
    ];

    // Turn one pasted cell into the value the grid stores plus an optional
    // error. Name columns resolve to ids (and flag "tak dikenal" when the name
    // is non-empty but unknown, mirroring the upload preview); employment
    // status maps to its value and keeps the current one when unrecognised;
    // text columns pass through trimmed.
    const resolvePastedCell = (
        key: PasteableKey,
        raw: string,
        current: string,
    ): { value: string; error?: string } => {
        const value = raw.trim();

        const resolveName = (
            map: Map<string, string>,
            message: string,
        ): { value: string; error?: string } => {
            if (value === '') {
                return { value: '' };
            }

            const id = map.get(value.toLowerCase());

            return id !== undefined
                ? { value: id }
                : { value: '', error: message };
        };

        switch (key) {
            case 'branch_id':
                return resolveName(branchMap, 'Cabang tak dikenal');
            case 'department_id':
                return resolveName(departmentMap, 'Departemen tak dikenal');
            case 'position_id':
                return resolveName(positionMap, 'Jabatan tak dikenal');
            case 'employment_status': {
                if (value === '') {
                    return { value: current };
                }

                const mapped = employmentMap.get(value.toLowerCase());

                return mapped !== undefined
                    ? { value: mapped }
                    : { value: current, error: 'Status tak dikenal' };
            }
            default:
                return { value };
        }
    };

    // Spread a copied block of cells across the grid, starting at the cell the
    // user pasted into and adding rows as needed. Single-cell pastes fall
    // through to the browser's default so normal editing still works.
    const handlePaste =
        (rowIndex: number, colKey: PasteableKey) =>
        (event: ClipboardEvent<HTMLInputElement>) => {
            const text = event.clipboardData.getData('text/plain');
            const matrix = text
                .replace(/\r\n?/g, '\n')
                .replace(/\n+$/, '')
                .split('\n')
                .map((line) => line.split('\t'));

            const isMultiCell =
                matrix.length > 1 || (matrix[0]?.length ?? 0) > 1;

            if (text === '' || !isMultiCell) {
                return; // let the browser paste into the single field
            }

            event.preventDefault();
            const startCol = pasteColumns.indexOf(colKey);

            // Drop a leading header row ("Nama Lengkap" / "Nama" / …) when the block
            // is pasted into the name column, so copying straight from a template
            // that still has its header doesn't create a junk first row.
            if (
                startCol === 0 &&
                matrix.length > 1 &&
                isHeaderCell(matrix[0][0] ?? '')
            ) {
                matrix.shift();
            }

            const next: BulkRow[] = rows.map((row) => ({
                ...row,
                errors: { ...(row.errors ?? {}) },
            }));

            matrix.forEach((cells, r) => {
                const targetRow = rowIndex + r;

                while (targetRow >= next.length) {
                    next.push({ ...emptyRow(), errors: {} });
                }

                cells.forEach((cell, c) => {
                    const targetCol = startCol + c;

                    if (targetCol >= pasteColumns.length) {
                        return; // ignore extra columns beyond the grid
                    }

                    const key = pasteColumns[targetCol];
                    const { value, error } = resolvePastedCell(
                        key,
                        cell,
                        next[targetRow][key] as string,
                    );
                    next[targetRow][key] = value;

                    if (error) {
                        next[targetRow].errors![errorKeyOf(key)] = error;
                    } else {
                        delete next[targetRow].errors![errorKeyOf(key)];
                    }
                });
            });

            setData('employees', next);
            const added = Math.max(0, rowIndex + matrix.length - rows.length);
            toast.success(
                `${matrix.length} baris ditempel${added > 0 ? ` · +${added} baris baru` : ''}`,
            );
        };

    // ---- Excel upload -> preview ----
    const onUpload = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        setUploading(true);
        const body = new FormData();
        body.append('file', file);

        try {
            const res = await fetch('/avana/employees/bulk/preview', {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
            });

            if (!res.ok) {
                const e = await res.json().catch(() => ({}));
                toast.error(
                    e.message ??
                        'Gagal membaca file. Pastikan format sesuai template.',
                );

                return;
            }

            const json = await res.json();
            const mapped: BulkRow[] = (json.rows ?? []).map(
                (r: Record<string, unknown>) => ({
                    full_name: (r.full_name as string) ?? '',
                    email: (r.email as string) ?? '',
                    branch_id: r.branch_id ? String(r.branch_id) : '',
                    department_id: r.department_id
                        ? String(r.department_id)
                        : '',
                    position_id: r.position_id ? String(r.position_id) : '',
                    employment_status:
                        (r.employment_status as string) ?? 'permanent',
                    status: (r.status as string) ?? 'active',
                    password: (r.password as string) ?? '',
                    errors: (r.errors as Record<string, string>) ?? {},
                }),
            );
            setData('employees', mapped.length ? mapped : [emptyRow()]);
            setSummary(json.summary ?? null);
            toast.success(
                `${json.summary?.valid ?? 0}/${json.summary?.total ?? 0} baris valid`,
            );
        } catch {
            toast.error('Gagal mengunggah file.');
        } finally {
            setUploading(false);
        }
    };

    const submit = () => {
        const filled = rows.filter((row) => !isBlank(row));
        const source = filled.length > 0 ? filled : rows;
        const employees = source.map((row) => ({
            full_name: row.full_name,
            email: row.email || null,
            branch_id: row.branch_id || null,
            department_id: row.department_id || null,
            position_id: row.position_id || null,
            employment_status: row.employment_status,
            status: row.status,
            password: row.password || null,
        }));
        form.transform(() => ({ employees }));
        form.post('/avana/employees/bulk', { preserveScroll: true });
    };

    // Combine client-side preview errors, live email checks, and server
    // validation errors.
    const cellError = (index: number, key: string): string | undefined => {
        const explicit =
            rows[index]?.errors?.[errorKeyOf(key as keyof BulkRow)] ??
            (errors as Record<string, string>)[`employees.${index}.${key}`];

        if (explicit) {
            return explicit;
        }

        return key === 'email' ? emailIssues.get(index) : undefined;
    };

    const styleFor = (index: number, key: string): CSSProperties =>
        cellError(index, key)
            ? { ...cell, borderColor: C.red, background: '#FEF2F2' }
            : cell;

    const filledCount =
        rows.filter((row) => !isBlank(row)).length || rows.length;

    const selectCell = (
        index: number,
        key: keyof BulkRow,
        blank: string,
        items: { id: number; name: string }[],
    ) => (
        <select
            value={rows[index][key] as string}
            onChange={(e) => updateRow(index, key, e.target.value)}
            style={{
                ...styleFor(index, key),
                cursor: 'pointer',
                minWidth: 130,
            }}
        >
            <option value="">{blank}</option>
            {items.map((it) => (
                <option key={it.id} value={String(it.id)}>
                    {it.name}
                </option>
            ))}
        </select>
    );

    return (
        <>
            <Head title="Tambah Karyawan Massal" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <Link
                        href="/avana/employees"
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Karyawan
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Massal</span>
                </div>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        gap: 16,
                        flexWrap: 'wrap',
                        marginBottom: 18,
                    }}
                >
                    <div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: '0 0 4px',
                                letterSpacing: '-.01em',
                            }}
                        >
                            Tambah Karyawan Massal
                        </h1>
                        <div style={{ fontSize: 14, color: C.muted }}>
                            Unduh template, isi di Excel, unggah — atau isi
                            manual di tabel. Semua kolom bisa beda tiap baris.
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <a
                            href="/avana/employees/bulk/template"
                            download
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="download" size={16} />
                            Unduh Template
                        </a>
                        <button
                            type="button"
                            onClick={() => fileInput.current?.click()}
                            disabled={uploading}
                            style={{
                                ...btnProcess,
                                opacity: uploading ? 0.7 : 1,
                                cursor: uploading ? 'wait' : 'pointer',
                            }}
                        >
                            <AIcon
                                name={uploading ? 'loader' : 'upload'}
                                size={16}
                                color="#fff"
                            />
                            {uploading ? 'Membaca…' : 'Unggah Excel'}
                        </button>
                        <input
                            ref={fileInput}
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={onUpload}
                            style={{ display: 'none' }}
                        />
                    </div>
                </div>

                {/* Import summary after an upload */}
                {summary && (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            padding: '12px 16px',
                            marginBottom: 16,
                            borderRadius: 12,
                            background:
                                summary.valid === summary.total
                                    ? '#ECFDF5'
                                    : '#FFF7ED',
                            border: `1px solid ${summary.valid === summary.total ? 'rgba(22,163,74,.3)' : 'rgba(217,119,6,.3)'}`,
                        }}
                    >
                        <AIcon
                            name={
                                summary.valid === summary.total
                                    ? 'circle-check'
                                    : 'triangle-alert'
                            }
                            size={18}
                            color={
                                summary.valid === summary.total
                                    ? C.green
                                    : C.amber
                            }
                        />
                        <span
                            style={{
                                fontSize: 13.5,
                                color: C.navy,
                                fontWeight: 500,
                            }}
                        >
                            {summary.total} baris terbaca · {summary.valid}{' '}
                            valid
                            {summary.valid < summary.total
                                ? ` · ${summary.total - summary.valid} perlu diperbaiki (baris merah)`
                                : ''}
                        </span>
                    </div>
                )}

                {/* Per-employee rows */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '10px 16px',
                            borderBottom: `1px solid ${C.line}`,
                            background: '#F7F9FC',
                            fontSize: 12.5,
                            color: C.muted,
                        }}
                    >
                        <AIcon
                            name="clipboard-paste"
                            size={15}
                            color={C.primary}
                        />
                        <span>
                            Tip: salin beberapa kolom dari Excel / Google
                            Sheets, klik sel{' '}
                            <b style={{ color: C.text }}>Nama</b>, lalu tempel (
                            <b style={{ color: C.text }}>Ctrl/Cmd+V</b>) untuk
                            mengisi banyak baris sekaligus. Nama cabang,
                            departemen, jabatan & status dikenali otomatis.
                        </span>
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 900,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={{ ...th, width: 30 }}>#</th>
                                    <th style={th}>Nama Lengkap *</th>
                                    <th style={th}>Email</th>
                                    <th style={th}>Cabang</th>
                                    <th style={th}>Departemen</th>
                                    <th style={th}>Jabatan</th>
                                    <th style={th}>Status</th>
                                    <th style={th}>Password</th>
                                    <th style={{ ...th, width: 40 }} />
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, index) => {
                                    const rowErrors = [
                                        ...Object.values(row.errors ?? {}),
                                        emailIssues.get(index),
                                    ].filter((message): message is string =>
                                        Boolean(message),
                                    );
                                    const hasError = rowErrors.length > 0;

                                    return (
                                        <tr
                                            key={index}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                                background: hasError
                                                    ? '#FFFBFB'
                                                    : undefined,
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: '8px 10px',
                                                    textAlign: 'center',
                                                }}
                                            >
                                                {hasError ? (
                                                    <span
                                                        title={rowErrors.join(
                                                            ', ',
                                                        )}
                                                        style={{
                                                            display:
                                                                'inline-block',
                                                            width: 8,
                                                            height: 8,
                                                            borderRadius: '50%',
                                                            background: C.red,
                                                        }}
                                                    />
                                                ) : (
                                                    <span
                                                        style={{
                                                            fontSize: 12,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {index + 1}
                                                    </span>
                                                )}
                                            </td>
                                            <td style={{ padding: '8px 10px' }}>
                                                <input
                                                    value={row.full_name}
                                                    onChange={(e) =>
                                                        updateRow(
                                                            index,
                                                            'full_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onPaste={handlePaste(
                                                        index,
                                                        'full_name',
                                                    )}
                                                    placeholder="Nama karyawan"
                                                    title={cellError(
                                                        index,
                                                        'full_name',
                                                    )}
                                                    style={{
                                                        ...styleFor(
                                                            index,
                                                            'full_name',
                                                        ),
                                                        minWidth: 150,
                                                    }}
                                                />
                                            </td>
                                            <td style={{ padding: '8px 10px' }}>
                                                <input
                                                    type="email"
                                                    value={row.email}
                                                    onChange={(e) =>
                                                        updateRow(
                                                            index,
                                                            'email',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onPaste={handlePaste(
                                                        index,
                                                        'email',
                                                    )}
                                                    placeholder="email@perusahaan.co.id"
                                                    title={cellError(
                                                        index,
                                                        'email',
                                                    )}
                                                    style={{
                                                        ...styleFor(
                                                            index,
                                                            'email',
                                                        ),
                                                        minWidth: 170,
                                                    }}
                                                />
                                            </td>
                                            <td style={{ padding: '8px 10px' }}>
                                                {selectCell(
                                                    index,
                                                    'branch_id',
                                                    '— Cabang —',
                                                    options.branches,
                                                )}
                                            </td>
                                            <td style={{ padding: '8px 10px' }}>
                                                {selectCell(
                                                    index,
                                                    'department_id',
                                                    '— Dept —',
                                                    options.departments,
                                                )}
                                            </td>
                                            <td style={{ padding: '8px 10px' }}>
                                                {selectCell(
                                                    index,
                                                    'position_id',
                                                    '— Jabatan —',
                                                    options.positions,
                                                )}
                                            </td>
                                            <td style={{ padding: '8px 10px' }}>
                                                <select
                                                    value={
                                                        row.employment_status
                                                    }
                                                    onChange={(e) =>
                                                        updateRow(
                                                            index,
                                                            'employment_status',
                                                            e.target.value,
                                                        )
                                                    }
                                                    style={{
                                                        ...styleFor(
                                                            index,
                                                            'employment_status',
                                                        ),
                                                        cursor: 'pointer',
                                                        minWidth: 130,
                                                    }}
                                                >
                                                    {options.employmentStatuses.map(
                                                        (o) => (
                                                            <option
                                                                key={o.value}
                                                                value={o.value}
                                                            >
                                                                {o.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </td>
                                            <td style={{ padding: '8px 10px' }}>
                                                <input
                                                    type="text"
                                                    autoComplete="new-password"
                                                    value={row.password}
                                                    onChange={(e) =>
                                                        updateRow(
                                                            index,
                                                            'password',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onPaste={handlePaste(
                                                        index,
                                                        'password',
                                                    )}
                                                    placeholder="opsional"
                                                    title={cellError(
                                                        index,
                                                        'password',
                                                    )}
                                                    style={{
                                                        ...styleFor(
                                                            index,
                                                            'password',
                                                        ),
                                                        minWidth: 110,
                                                    }}
                                                />
                                            </td>
                                            <td
                                                style={{
                                                    padding: '8px 10px',
                                                    textAlign: 'center',
                                                }}
                                            >
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    title="Hapus baris"
                                                    onClick={() =>
                                                        removeRow(index)
                                                    }
                                                    disabled={rows.length <= 1}
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div
                        style={{
                            padding: '12px 16px',
                            borderTop: `1px solid ${C.border}`,
                        }}
                    >
                        <button
                            type="button"
                            onClick={addRow}
                            style={{ ...btnOut, height: 38 }}
                        >
                            <AIcon name="plus" size={16} />
                            Tambah Baris
                        </button>
                    </div>
                </div>

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 10,
                        marginTop: 18,
                    }}
                >
                    <button
                        type="button"
                        onClick={() => router.visit('/avana/employees')}
                        style={btnOut}
                    >
                        <AIcon name="x" size={16} />
                        Batal
                    </button>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={processing}
                        style={{
                            ...btnSave,
                            opacity: processing ? 0.7 : 1,
                            cursor: processing ? 'not-allowed' : 'pointer',
                        }}
                    >
                        <AIcon name="save" size={16} color="#fff" />
                        Simpan {filledCount} Karyawan
                    </button>
                </div>
            </div>
        </>
    );
}
