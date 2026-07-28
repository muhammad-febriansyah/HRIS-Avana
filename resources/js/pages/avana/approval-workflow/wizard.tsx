import { router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import ApprovalWorkflowController from '@/actions/App/Http/Controllers/Avana/ApprovalWorkflowController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnOut, btnP, btnSave, C, card } from '@/lib/avana';
import type {
    ApproverTypeDef,
    ConditionDraft,
    ConditionField,
    ModuleDef,
    Option,
    StepDraft,
    WizardOptions,
    WorkflowRow,
} from './types';

/* ---------- local styles ---------- */

const fieldLabel: CSSProperties = {
    display: 'block',
    fontSize: 12,
    fontWeight: 500,
    marginBottom: 6,
    color: C.muted,
};
const input: CSSProperties = {
    width: '100%',
    height: 40,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.text,
    background: '#fff',
    outline: 'none',
};
const select: CSSProperties = { ...input, cursor: 'pointer' };

const WIZARD_STEPS = [
    { n: 1, label: 'Pilih Modul' },
    { n: 2, label: 'Atur Alur' },
    { n: 3, label: 'Assign Approver' },
    { n: 4, label: 'Selesai' },
];

const FIELD_LABELS: Record<ConditionField, string> = {
    days: 'Jumlah Hari',
    amount: 'Nominal (Rp)',
    leave_type: 'Jenis Cuti',
};

let uidSeq = 0;
function uid(): string {
    uidSeq += 1;

    return `d${uidSeq}`;
}

function emptyStep(defaultType: string): StepDraft {
    return {
        uid: uid(),
        approver_type: defaultType,
        approver_role_id: null,
        approver_department_id: null,
        approver_position_id: null,
        approver_user_id: null,
        condition: null,
    };
}

/* ---------- component ---------- */

interface WizardProps {
    mode: 'create' | 'edit';
    workflow: WorkflowRow | null;
    modules: ModuleDef[];
    approverTypes: ApproverTypeDef[];
    options: WizardOptions;
    onClose: () => void;
}

export default function Wizard({
    mode,
    workflow,
    modules,
    approverTypes,
    options,
    onClose,
}: WizardProps) {
    const firstApproverType = approverTypes[0]?.key ?? 'direct_manager';

    const [current, setCurrent] = useState(1);
    const [processing, setProcessing] = useState(false);

    const [requestType, setRequestType] = useState<string>(
        workflow?.request_type ?? '',
    );
    const [approvalMode, setApprovalMode] = useState<'sequential' | 'parallel'>(
        workflow?.approval_mode ?? 'sequential',
    );
    const [isActive, setIsActive] = useState<boolean>(
        workflow?.is_active ?? true,
    );

    const [steps, setSteps] = useState<StepDraft[]>(() => {
        if (workflow && workflow.steps.length > 0) {
            return workflow.steps.map((s) => ({
                uid: uid(),
                approver_type: s.approver_type,
                approver_role_id: s.approver_role_id,
                approver_department_id: s.approver_department_id,
                approver_position_id: s.approver_position_id,
                approver_user_id: s.approver_user_id,
                condition: null,
            }));
        }

        return [emptyStep(firstApproverType)];
    });

    const [conditions, setConditions] = useState<ConditionDraft[]>(() =>
        (workflow?.conditions ?? []).map((c) => ({
            uid: uid(),
            enabled: true,
            field: c.field,
            operator: c.operator,
            value: c.value,
            extra_approver_type: c.extra_approver_type,
            extra_approver_ref: c.extra_approver_ref,
        })),
    );

    const selectedModule = useMemo(
        () => modules.find((m) => m.key === requestType) ?? null,
        [modules, requestType],
    );

    /* ---------- step helpers ---------- */

    const patchStep = (uidKey: string, patch: Partial<StepDraft>) =>
        setSteps((prev) =>
            prev.map((s) => (s.uid === uidKey ? { ...s, ...patch } : s)),
        );

    const changeApproverType = (uidKey: string, type: string) =>
        patchStep(uidKey, {
            approver_type: type,
            approver_role_id: null,
            approver_department_id: null,
            approver_position_id: null,
            approver_user_id: null,
        });

    const setStepCount = (count: number) =>
        setSteps((prev) => {
            if (count === prev.length) {
                return prev;
            }

            if (count < prev.length) {
                return prev.slice(0, count);
            }

            const extra = Array.from({ length: count - prev.length }, () =>
                emptyStep(firstApproverType),
            );

            return [...prev, ...extra];
        });

    const addStep = () =>
        setSteps((prev) => [...prev, emptyStep(firstApproverType)]);

    const removeStep = (uidKey: string) =>
        setSteps((prev) =>
            prev.length <= 1 ? prev : prev.filter((s) => s.uid !== uidKey),
        );

    /* ---------- condition helpers ---------- */

    const addCondition = () =>
        setConditions((prev) => [
            ...prev,
            {
                uid: uid(),
                enabled: true,
                field: 'days',
                operator: '>',
                value: '',
                extra_approver_type: firstApproverType,
                extra_approver_ref: null,
            },
        ]);

    const patchCondition = (uidKey: string, patch: Partial<ConditionDraft>) =>
        setConditions((prev) =>
            prev.map((c) => (c.uid === uidKey ? { ...c, ...patch } : c)),
        );

    const removeCondition = (uidKey: string) =>
        setConditions((prev) => prev.filter((c) => c.uid !== uidKey));

    /* ---------- ref option resolver ---------- */

    const refOptions = (approverType: string): Option[] => {
        const def = approverTypes.find((a) => a.key === approverType);

        switch (def?.ref) {
            case 'role':
                return options.roles;
            case 'department':
                return options.departments;
            case 'position':
                return options.positions;
            case 'employee':
                return options.employees;
            default:
                return [];
        }
    };

    const refValueOf = (step: StepDraft): number | null => {
        const def = approverTypes.find((a) => a.key === step.approver_type);

        switch (def?.ref) {
            case 'role':
                return step.approver_role_id;
            case 'department':
                return step.approver_department_id;
            case 'position':
                return step.approver_position_id;
            case 'employee':
                return step.approver_user_id;
            default:
                return null;
        }
    };

    const setRefValue = (step: StepDraft, value: number | null) => {
        const def = approverTypes.find((a) => a.key === step.approver_type);

        switch (def?.ref) {
            case 'role':
                return patchStep(step.uid, { approver_role_id: value });
            case 'department':
                return patchStep(step.uid, { approver_department_id: value });
            case 'position':
                return patchStep(step.uid, { approver_position_id: value });
            case 'employee':
                return patchStep(step.uid, { approver_user_id: value });
            default:
                return undefined;
        }
    };

    const approverText = (step: StepDraft): string => {
        const def = approverTypes.find((a) => a.key === step.approver_type);

        if (!def || def.ref === null) {
            return def?.label ?? '—';
        }

        const opt = refOptions(step.approver_type).find(
            (o) => o.value === refValueOf(step),
        );

        return opt?.label ?? def.label;
    };

    /* ---------- navigation / validation ---------- */

    const canLeaveStep = (step: number): boolean => {
        if (step === 1 && !requestType) {
            toast.error('Pilih modul terlebih dahulu');

            return false;
        }

        if (step === 3) {
            const bad = steps.find((s) => {
                const def = approverTypes.find(
                    (a) => a.key === s.approver_type,
                );

                return def?.ref != null && refValueOf(s) == null;
            });

            if (bad) {
                toast.error('Lengkapi approver untuk setiap step');

                return false;
            }
        }

        return true;
    };

    const goNext = () => {
        if (!canLeaveStep(current)) {
            return;
        }

        setCurrent((c) => Math.min(4, c + 1));
    };
    const goBack = () => setCurrent((c) => Math.max(1, c - 1));

    const submit = () => {
        for (const step of [1, 3]) {
            if (!canLeaveStep(step)) {
                setCurrent(step);

                return;
            }
        }

        const payload = {
            name: selectedModule?.label ?? '',
            request_type: requestType,
            approval_mode: approvalMode,
            is_active: isActive,
            steps: steps.map((s) => ({
                approver_type: s.approver_type,
                approver_role_id: s.approver_role_id,
                approver_department_id: s.approver_department_id,
                approver_position_id: s.approver_position_id,
                approver_user_id: s.approver_user_id,
                condition: null,
            })),
            conditions: conditions
                .filter((c) => c.enabled && c.value !== '')
                .map((c) => ({
                    field: c.field,
                    operator: c.operator,
                    value: c.value,
                    extra_approver_type: c.extra_approver_type,
                    extra_approver_ref: c.extra_approver_ref,
                })),
        };

        const opts = {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            // The redirect target is this same page component, so Inertia keeps
            // the component mounted (local state survives). Reset back to the
            // list explicitly so the refreshed table shows.
            onSuccess: () => onClose(),
            onError: () =>
                toast.error('Gagal menyimpan alur. Periksa isian kembali.'),
        };

        if (mode === 'edit' && workflow) {
            router.put(
                ApprovalWorkflowController.update(workflow.id).url,
                payload,
                opts,
            );
        } else {
            router.post(ApprovalWorkflowController.store().url, payload, opts);
        }
    };

    /* ---------- render ---------- */

    return (
        <div style={{ padding: '28px 32px' }}>
            {/* header */}
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    marginBottom: 6,
                }}
            >
                <button
                    onClick={onClose}
                    style={{
                        ...btnOut,
                        height: 34,
                        padding: '0 12px',
                        fontSize: 12.5,
                    }}
                >
                    <AIcon name="arrow-left" size={15} />
                    Kembali
                </button>
            </div>
            <h1
                style={{
                    fontSize: 23,
                    fontWeight: 600,
                    color: C.navy,
                    margin: '10px 0 4px',
                    letterSpacing: '-.01em',
                }}
            >
                Setup Approval Workflow
                {selectedModule ? ` — ${selectedModule.label}` : ''}
            </h1>
            <div style={{ fontSize: 13.5, color: C.muted, marginBottom: 22 }}>
                Atur alur persetujuan untuk setiap jenis pengajuan sesuai
                kebijakan perusahaan.
            </div>

            {/* stepper */}
            <Stepper current={current} />

            <div style={{ ...card, padding: 24, marginTop: 20 }}>
                {current === 1 && (
                    <ModuleStep
                        modules={modules}
                        value={requestType}
                        onSelect={setRequestType}
                    />
                )}

                {current === 2 && (
                    <FlowStep
                        approvalMode={approvalMode}
                        setApprovalMode={setApprovalMode}
                        stepCount={steps.length}
                        setStepCount={setStepCount}
                    />
                )}

                {current === 3 && (
                    <ApproverStep
                        steps={steps}
                        approverTypes={approverTypes}
                        refOptions={refOptions}
                        refValueOf={refValueOf}
                        setRefValue={setRefValue}
                        changeApproverType={changeApproverType}
                        removeStep={removeStep}
                        addStep={addStep}
                    />
                )}

                {current === 4 && (
                    <FinishStep
                        conditions={conditions}
                        approverTypes={approverTypes}
                        options={options}
                        addCondition={addCondition}
                        patchCondition={patchCondition}
                        removeCondition={removeCondition}
                        summary={{
                            moduleLabel: selectedModule?.label ?? '—',
                            modeLabel:
                                approvalMode === 'parallel'
                                    ? 'Paralel'
                                    : 'Berurutan',
                            steps: steps.map((s, i) => ({
                                order: i + 1,
                                typeLabel:
                                    approverTypes.find(
                                        (a) => a.key === s.approver_type,
                                    )?.label ?? s.approver_type,
                                target: approverText(s),
                            })),
                        }}
                    />
                )}
            </div>

            {/* footer nav */}
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    marginTop: 20,
                }}
            >
                <div>
                    {current > 1 && (
                        <button onClick={goBack} style={btnOut}>
                            <AIcon name="arrow-left" size={15} />
                            Kembali
                        </button>
                    )}
                </div>
                {current < 4 ? (
                    <button onClick={goNext} style={btnP}>
                        Lanjut
                        <AIcon name="arrow-right" size={15} color="#fff" />
                    </button>
                ) : (
                    <button
                        onClick={submit}
                        disabled={processing}
                        style={{
                            ...btnSave,
                            opacity: processing ? 0.7 : 1,
                        }}
                    >
                        <AIcon name="check" size={15} color="#fff" />
                        Simpan Workflow
                    </button>
                )}
            </div>
        </div>
    );
}

/* ============================================================
 * sub-steps
 * ============================================================ */

function Stepper({ current }: { current: number }) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 4,
                flexWrap: 'wrap',
            }}
        >
            {WIZARD_STEPS.map((s, i) => {
                const done = current > s.n;
                const active = current === s.n;
                const color = done || active ? C.primary : C.faint;

                return (
                    <div
                        key={s.n}
                        style={{ display: 'flex', alignItems: 'center' }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                            }}
                        >
                            <div
                                style={{
                                    width: 26,
                                    height: 26,
                                    borderRadius: 100,
                                    background: done
                                        ? C.primary
                                        : active
                                          ? 'rgba(47,84,201,.12)'
                                          : C.surface,
                                    border: `1.5px solid ${done || active ? C.primary : C.border}`,
                                    color: done ? '#fff' : color,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: 12,
                                    fontWeight: 700,
                                }}
                            >
                                {done ? (
                                    <AIcon
                                        name="check"
                                        size={14}
                                        color="#fff"
                                    />
                                ) : (
                                    s.n
                                )}
                            </div>
                            <span
                                style={{
                                    fontSize: 13,
                                    fontWeight: active ? 600 : 500,
                                    color: active ? C.navy : C.muted,
                                }}
                            >
                                {s.label}
                            </span>
                        </div>
                        {i < WIZARD_STEPS.length - 1 && (
                            <div
                                style={{
                                    width: 48,
                                    height: 2,
                                    margin: '0 10px',
                                    background:
                                        current > s.n ? C.primary : C.border,
                                }}
                            />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function ModuleStep({
    modules,
    value,
    onSelect,
}: {
    modules: ModuleDef[];
    value: string;
    onSelect: (key: string) => void;
}) {
    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
                gap: 14,
            }}
        >
            {modules.map((m) => {
                const selected = value === m.key;

                return (
                    <button
                        key={m.key}
                        onClick={() => onSelect(m.key)}
                        style={{
                            position: 'relative',
                            textAlign: 'left',
                            padding: 16,
                            borderRadius: 12,
                            border: `1.5px solid ${selected ? C.primary : C.border}`,
                            background: selected
                                ? 'rgba(47,84,201,.04)'
                                : '#fff',
                            cursor: 'pointer',
                            transition: '.15s',
                        }}
                    >
                        {selected && (
                            <div
                                style={{
                                    position: 'absolute',
                                    top: 12,
                                    right: 12,
                                    color: C.primary,
                                }}
                            >
                                <AIcon
                                    name="check-circle-2"
                                    size={18}
                                    color={C.primary}
                                />
                            </div>
                        )}
                        <div
                            style={{
                                width: 40,
                                height: 40,
                                borderRadius: 10,
                                background: `${m.color}1a`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginBottom: 12,
                            }}
                        >
                            <AIcon name={m.icon} size={20} color={m.color} />
                        </div>
                        <div
                            style={{
                                fontSize: 14,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            {m.label}
                        </div>
                        <div
                            style={{
                                fontSize: 12,
                                color: C.muted,
                                marginTop: 3,
                            }}
                        >
                            {m.description}
                        </div>
                    </button>
                );
            })}
        </div>
    );
}

function FlowStep({
    approvalMode,
    setApprovalMode,
    stepCount,
    setStepCount,
}: {
    approvalMode: 'sequential' | 'parallel';
    setApprovalMode: (m: 'sequential' | 'parallel') => void;
    stepCount: number;
    setStepCount: (n: number) => void;
}) {
    const modeCards = [
        {
            key: 'sequential' as const,
            icon: 'list-ordered',
            title: 'Berurutan (Sequential)',
            desc: 'Disetujui sesuai urutan langkah',
        },
        {
            key: 'parallel' as const,
            icon: 'git-fork',
            title: 'Paralel (Parallel)',
            desc: 'Disetujui oleh beberapa approver secara bersamaan',
        },
    ];

    return (
        <div>
            <div
                style={{
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                    marginBottom: 14,
                }}
            >
                Tipe Workflow
            </div>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
                    gap: 14,
                }}
            >
                {modeCards.map((m) => {
                    const selected = approvalMode === m.key;

                    return (
                        <button
                            key={m.key}
                            onClick={() => setApprovalMode(m.key)}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                                textAlign: 'left',
                                padding: 16,
                                borderRadius: 12,
                                border: `1.5px solid ${selected ? C.primary : C.border}`,
                                background: selected
                                    ? 'rgba(47,84,201,.04)'
                                    : '#fff',
                                cursor: 'pointer',
                            }}
                        >
                            <div
                                style={{
                                    width: 38,
                                    height: 38,
                                    borderRadius: 10,
                                    background: selected
                                        ? 'rgba(47,84,201,.12)'
                                        : C.surface,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon
                                    name={m.icon}
                                    size={19}
                                    color={selected ? C.primary : C.muted}
                                />
                            </div>
                            <div>
                                <div
                                    style={{
                                        fontSize: 13.5,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    {m.title}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: C.muted,
                                        marginTop: 2,
                                    }}
                                >
                                    {m.desc}
                                </div>
                            </div>
                        </button>
                    );
                })}
            </div>

            <div style={{ marginTop: 24, maxWidth: 220 }}>
                <label style={fieldLabel}>Jumlah Step</label>
                <select
                    value={stepCount}
                    onChange={(e) => setStepCount(Number(e.target.value))}
                    style={select}
                >
                    {Array.from({ length: 10 }, (_, i) => i + 1).map((n) => (
                        <option key={n} value={n}>
                            {n} step
                        </option>
                    ))}
                </select>
                <div style={{ fontSize: 12, color: C.faint, marginTop: 6 }}>
                    Tentukan jumlah level persetujuan
                </div>
            </div>
        </div>
    );
}

function ApproverStep({
    steps,
    approverTypes,
    refOptions,
    refValueOf,
    setRefValue,
    changeApproverType,
    removeStep,
    addStep,
}: {
    steps: StepDraft[];
    approverTypes: ApproverTypeDef[];
    refOptions: (t: string) => Option[];
    refValueOf: (s: StepDraft) => number | null;
    setRefValue: (s: StepDraft, v: number | null) => void;
    changeApproverType: (uid: string, t: string) => void;
    removeStep: (uid: string) => void;
    addStep: () => void;
}) {
    return (
        <div>
            <div
                style={{
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                    marginBottom: 14,
                }}
            >
                Konfigurasi Approver
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                {steps.map((s, i) => {
                    const def = approverTypes.find(
                        (a) => a.key === s.approver_type,
                    );
                    const needsRef = def?.ref != null;

                    return (
                        <div
                            key={s.uid}
                            style={{
                                display: 'flex',
                                alignItems: 'flex-end',
                                gap: 12,
                                padding: 14,
                                border: `1px solid ${C.border}`,
                                borderRadius: 12,
                                background: '#FAFBFD',
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                    paddingBottom: 8,
                                }}
                            >
                                <span
                                    title="Geser untuk mengurutkan"
                                    style={{
                                        color: C.faint,
                                        cursor: 'grab',
                                        display: 'flex',
                                    }}
                                >
                                    <AIcon
                                        name="grip-vertical"
                                        size={16}
                                        color={C.faint}
                                    />
                                </span>
                                <span
                                    style={{
                                        width: 26,
                                        height: 26,
                                        borderRadius: 100,
                                        background: 'rgba(47,84,201,.12)',
                                        color: C.primary,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: 12,
                                        fontWeight: 700,
                                    }}
                                >
                                    {i + 1}
                                </span>
                                <span
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.navy,
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    Step {i + 1}
                                </span>
                            </div>

                            <div style={{ flex: 1, minWidth: 180 }}>
                                <label style={fieldLabel}>Tipe Approver</label>
                                <select
                                    value={s.approver_type}
                                    onChange={(e) =>
                                        changeApproverType(
                                            s.uid,
                                            e.target.value,
                                        )
                                    }
                                    style={select}
                                >
                                    {approverTypes.map((a) => (
                                        <option key={a.key} value={a.key}>
                                            {a.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {needsRef && (
                                <div style={{ flex: 1, minWidth: 180 }}>
                                    <label style={fieldLabel}>
                                        Pilih {def?.label}
                                    </label>
                                    <SearchableSelect
                                        value={
                                            refValueOf(s) == null
                                                ? ''
                                                : String(refValueOf(s))
                                        }
                                        onChange={(v) =>
                                            setRefValue(
                                                s,
                                                v === '' ? null : Number(v),
                                            )
                                        }
                                        options={refOptions(
                                            s.approver_type,
                                        ).map((o) => ({
                                            value: String(o.value),
                                            label: o.label,
                                        }))}
                                        placeholder="Pilih…"
                                        searchPlaceholder="Cari…"
                                        allowClear
                                        style={select}
                                    />
                                </div>
                            )}

                            <div style={{ flex: 1, minWidth: 160 }}>
                                <label style={fieldLabel}>
                                    Kondisi (Opsional)
                                </label>
                                <select style={select} disabled value="always">
                                    <option value="always">
                                        Selalu diperlukan
                                    </option>
                                </select>
                            </div>

                            <button
                                onClick={() => removeStep(s.uid)}
                                disabled={steps.length <= 1}
                                title="Hapus step"
                                style={{
                                    height: 40,
                                    width: 40,
                                    flex: 'none',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    background: '#fff',
                                    color: steps.length <= 1 ? C.faint : C.red,
                                    cursor:
                                        steps.length <= 1
                                            ? 'not-allowed'
                                            : 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon
                                    name="trash-2"
                                    size={15}
                                    color={steps.length <= 1 ? C.faint : C.red}
                                />
                            </button>
                        </div>
                    );
                })}
            </div>

            <button
                onClick={addStep}
                style={{
                    marginTop: 12,
                    width: '100%',
                    height: 42,
                    border: `1.5px dashed ${C.border}`,
                    borderRadius: 10,
                    background: '#fff',
                    color: C.primary,
                    fontSize: 13,
                    fontWeight: 600,
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: 7,
                }}
            >
                <AIcon name="plus" size={15} color={C.primary} />
                Tambah Step
            </button>
        </div>
    );
}

function FinishStep({
    conditions,
    approverTypes,
    options,
    addCondition,
    patchCondition,
    removeCondition,
    summary,
}: {
    conditions: ConditionDraft[];
    approverTypes: ApproverTypeDef[];
    options: WizardOptions;
    addCondition: () => void;
    patchCondition: (uid: string, patch: Partial<ConditionDraft>) => void;
    removeCondition: (uid: string) => void;
    summary: {
        moduleLabel: string;
        modeLabel: string;
        steps: { order: number; typeLabel: string; target: string }[];
    };
}) {
    const [showDetail, setShowDetail] = useState(false);

    const extraRefOptions = (approverType: string): Option[] => {
        const def = approverTypes.find((a) => a.key === approverType);

        switch (def?.ref) {
            case 'role':
                return options.roles;
            case 'department':
                return options.departments;
            case 'position':
                return options.positions;
            case 'employee':
                return options.employees;
            default:
                return [];
        }
    };

    const conditionText = (c: ConditionDraft): string => {
        const fieldText =
            c.field === 'leave_type'
                ? `jenis cuti ${options.leaveTypes.find((t) => String(t.value) === c.value)?.label ?? c.value}`
                : `${FIELD_LABELS[c.field].toLowerCase()} ${c.operator} ${c.value}${c.field === 'days' ? ' hari' : ''}`;
        const typeLabel =
            approverTypes.find((a) => a.key === c.extra_approver_type)?.label ??
            c.extra_approver_type;
        const refLabel = extraRefOptions(c.extra_approver_type).find(
            (o) => o.value === c.extra_approver_ref,
        )?.label;

        return `Jika ${fieldText} → tambah approver ${refLabel ?? typeLabel}`;
    };

    const activeConditions = conditions.filter(
        (c) => c.enabled && c.value !== '',
    );

    return (
        <div>
            <div
                style={{
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                    marginBottom: 4,
                }}
            >
                Kondisi Tambahan (Opsional)
            </div>
            <div style={{ fontSize: 12.5, color: C.muted, marginBottom: 16 }}>
                Atur kondisi khusus jika diperlukan, misalnya berdasarkan jumlah
                hari cuti.
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {conditions.map((c) => {
                    const needsRef =
                        approverTypes.find(
                            (a) => a.key === c.extra_approver_type,
                        )?.ref != null;

                    return (
                        <div
                            key={c.uid}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                flexWrap: 'wrap',
                                padding: 14,
                                border: `1px solid ${C.border}`,
                                borderRadius: 12,
                                background: c.enabled ? '#fff' : '#FAFBFD',
                            }}
                        >
                            <Toggle
                                on={c.enabled}
                                onClick={() =>
                                    patchCondition(c.uid, {
                                        enabled: !c.enabled,
                                    })
                                }
                            />
                            <span style={{ fontSize: 13, color: C.muted }}>
                                Jika
                            </span>
                            <select
                                value={c.field}
                                onChange={(e) =>
                                    patchCondition(c.uid, {
                                        field: e.target
                                            .value as ConditionDraft['field'],
                                        value: '',
                                    })
                                }
                                style={{ ...select, width: 'auto' }}
                            >
                                {(
                                    Object.keys(
                                        FIELD_LABELS,
                                    ) as ConditionField[]
                                ).map((f) => (
                                    <option key={f} value={f}>
                                        {FIELD_LABELS[f]}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={c.operator}
                                onChange={(e) =>
                                    patchCondition(c.uid, {
                                        operator: e.target
                                            .value as ConditionDraft['operator'],
                                    })
                                }
                                style={{ ...select, width: 64 }}
                            >
                                {['>', '>=', '=', '<', '<='].map((op) => (
                                    <option key={op} value={op}>
                                        {op}
                                    </option>
                                ))}
                            </select>
                            {c.field === 'leave_type' ? (
                                <select
                                    value={c.value}
                                    onChange={(e) =>
                                        patchCondition(c.uid, {
                                            value: e.target.value,
                                        })
                                    }
                                    style={{ ...select, width: 'auto' }}
                                >
                                    <option value="">Pilih jenis…</option>
                                    {options.leaveTypes.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <>
                                    <input
                                        type="number"
                                        value={c.value}
                                        onChange={(e) =>
                                            patchCondition(c.uid, {
                                                value: e.target.value,
                                            })
                                        }
                                        placeholder="0"
                                        style={{ ...input, width: 90 }}
                                    />
                                    {c.field === 'days' && (
                                        <span
                                            style={{
                                                fontSize: 13,
                                                color: C.muted,
                                            }}
                                        >
                                            hari
                                        </span>
                                    )}
                                </>
                            )}
                            <span style={{ fontSize: 13, color: C.muted }}>
                                maka tambah approver:
                            </span>
                            <select
                                value={c.extra_approver_type}
                                onChange={(e) =>
                                    patchCondition(c.uid, {
                                        extra_approver_type: e.target.value,
                                        extra_approver_ref: null,
                                    })
                                }
                                style={{ ...select, width: 'auto' }}
                            >
                                {approverTypes.map((a) => (
                                    <option key={a.key} value={a.key}>
                                        {a.label}
                                    </option>
                                ))}
                            </select>
                            {needsRef && (
                                <select
                                    value={
                                        c.extra_approver_ref == null
                                            ? ''
                                            : String(c.extra_approver_ref)
                                    }
                                    onChange={(e) =>
                                        patchCondition(c.uid, {
                                            extra_approver_ref:
                                                e.target.value === ''
                                                    ? null
                                                    : Number(e.target.value),
                                        })
                                    }
                                    style={{ ...select, width: 'auto' }}
                                >
                                    <option value="">Pilih…</option>
                                    {extraRefOptions(c.extra_approver_type).map(
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
                            )}
                            <button
                                onClick={() => removeCondition(c.uid)}
                                title="Hapus kondisi"
                                style={{
                                    marginLeft: 'auto',
                                    height: 34,
                                    width: 34,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    background: '#fff',
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon name="trash-2" size={14} color={C.red} />
                            </button>
                        </div>
                    );
                })}
            </div>

            <button
                onClick={addCondition}
                style={{
                    marginTop: 12,
                    width: '100%',
                    height: 42,
                    border: `1.5px dashed ${C.border}`,
                    borderRadius: 10,
                    background: '#fff',
                    color: C.primary,
                    fontSize: 13,
                    fontWeight: 600,
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: 7,
                }}
            >
                <AIcon name="plus" size={15} color={C.primary} />
                Tambah Kondisi
            </button>

            {/* Ringkasan */}
            <div
                style={{
                    marginTop: 20,
                    padding: 16,
                    borderRadius: 12,
                    background: 'rgba(47,84,201,.05)',
                    border: `1px solid rgba(47,84,201,.15)`,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 12,
                        flexWrap: 'wrap',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <div
                            style={{
                                width: 34,
                                height: 34,
                                borderRadius: 9,
                                background: 'rgba(47,84,201,.12)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flex: 'none',
                            }}
                        >
                            <AIcon name="info" size={17} color={C.primary} />
                        </div>
                        <div>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Ringkasan Workflow
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 2,
                                }}
                            >
                                {summary.moduleLabel} — {summary.steps.length}{' '}
                                Step (
                                {summary.steps
                                    .map((st) => st.target)
                                    .join(' → ')}
                                )
                            </div>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowDetail((v) => !v)}
                        style={{
                            ...btnOut,
                            height: 34,
                            padding: '0 12px',
                            fontSize: 12.5,
                        }}
                    >
                        <AIcon name="eye" size={14} />
                        {showDetail ? 'Sembunyikan' : 'Lihat Detail'}
                    </button>
                </div>

                {showDetail && (
                    <div
                        style={{
                            marginTop: 14,
                            padding: 14,
                            borderRadius: 10,
                            background: '#fff',
                            border: `1px solid ${C.border}`,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 13.5,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 10,
                            }}
                        >
                            Detail Konfigurasi
                        </div>
                        {[
                            ['Modul', summary.moduleLabel],
                            ['Tipe Workflow', summary.modeLabel],
                            ['Jumlah Step', String(summary.steps.length)],
                        ].map(([k, v]) => (
                            <div
                                key={k}
                                style={{
                                    display: 'flex',
                                    fontSize: 13,
                                    marginBottom: 6,
                                }}
                            >
                                <span
                                    style={{
                                        width: 130,
                                        color: C.muted,
                                        flex: 'none',
                                    }}
                                >
                                    {k}
                                </span>
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
                                    {v}
                                </span>
                            </div>
                        ))}
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kondisi Khusus
                        </div>
                        {activeConditions.length === 0 ? (
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.faint,
                                    marginTop: 4,
                                }}
                            >
                                Tidak ada
                            </div>
                        ) : (
                            <ul
                                style={{
                                    margin: '6px 0 0',
                                    paddingLeft: 18,
                                    fontSize: 12.5,
                                    color: C.text,
                                    lineHeight: 1.7,
                                }}
                            >
                                {activeConditions.map((c) => (
                                    <li key={c.uid}>{conditionText(c)}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function Toggle({ on, onClick }: { on: boolean; onClick: () => void }) {
    return (
        <button
            onClick={onClick}
            style={{
                width: 38,
                height: 22,
                borderRadius: 100,
                border: 'none',
                background: on ? C.primary : C.border,
                position: 'relative',
                cursor: 'pointer',
                flex: 'none',
                transition: '.15s',
            }}
        >
            <span
                style={{
                    position: 'absolute',
                    top: 2,
                    left: on ? 18 : 2,
                    width: 18,
                    height: 18,
                    borderRadius: 100,
                    background: '#fff',
                    transition: '.15s',
                    boxShadow: '0 1px 2px rgba(0,0,0,.2)',
                }}
            />
        </button>
    );
}
