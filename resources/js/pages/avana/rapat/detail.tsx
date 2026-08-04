import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import MeetingController from '@/actions/App/Http/Controllers/Avana/MeetingController';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import {
    AIcon,
    btnDanger,
    btnExport,
    btnOut,
    btnProcess,
    btnSave,
    C,
    card,
    hexA,
} from '@/lib/avana';
import { InsightPanel } from './insight-panel';
import {
    ActionItemMeter,
    MeetingStatTiles,
    TalkShareChart,
    TurnTimeline,
} from './meeting-stats';
import type {
    ActionItemRow,
    FlashProps,
    MeetingDetailProps,
    SpeakerRow,
    TranscriptLine,
} from './types';
import { formatDateTime, STATUS_LABELS } from './types';

const inputStyle: CSSProperties = {
    width: '100%',
    height: 40,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

const sectionTitle: CSSProperties = {
    fontSize: 15,
    fontWeight: 600,
    color: C.navy,
    marginBottom: 4,
};

const sectionHint: CSSProperties = {
    fontSize: 12.5,
    color: C.muted,
    marginBottom: 14,
};

/**
 * Prose is capped at a reading measure rather than the card's width.
 *
 * A summary set across 110 characters makes the eye lose its place on the
 * return sweep, which is exactly the wrong thing for the one part of the page
 * people actually read end to end.
 */
const proseStyle: CSSProperties = {
    fontSize: 15,
    lineHeight: 1.75,
    color: C.text,
    maxWidth: '66ch',
};

/** Split model prose into paragraphs so blank lines become real spacing. */
function paragraphsOf(text: string): string[] {
    return text
        .split(/\n\s*\n/)
        .map((part) => part.trim())
        .filter((part) => part !== '');
}

/**
 * Consecutive lines from one speaker, so their name is printed once instead of
 * on every utterance. A two-minute recording produced forty rows each labelled
 * "Pembicara 1", which buried what was said under who said it.
 */
function groupBySpeaker(
    lines: TranscriptLine[],
): { speaker: string; speakerIndex: number; lines: TranscriptLine[] }[] {
    const blocks: {
        speaker: string;
        speakerIndex: number;
        lines: TranscriptLine[];
    }[] = [];

    for (const line of lines) {
        const last = blocks[blocks.length - 1];

        if (last && last.speakerIndex === line.speaker_index) {
            last.lines.push(line);
            continue;
        }

        blocks.push({
            speaker: line.speaker,
            speakerIndex: line.speaker_index,
            lines: [line],
        });
    }

    return blocks;
}

export default function MeetingDetail({
    meeting,
    stats,
    transcript,
    speakers,
    actionItems,
    insights,
    employees,
    can,
    proModel,
}: MeetingDetailProps) {
    const { flash } = usePage<FlashProps>().props;

    /**
     * Land on a transcript line and mark it briefly, so a click on the timeline
     * lands somewhere the eye can find rather than merely scrolling.
     */
    const seekToLine = (lineId: number): void => {
        const node = document.getElementById(`ucapan-${lineId}`);

        if (node === null) {
            return;
        }

        node.scrollIntoView({ behavior: 'smooth', block: 'center' });
        node.style.background = C.line;
        window.setTimeout(() => {
            node.style.background = 'transparent';
        }, 1600);
    };

    // Local copy so the whole panel can be edited then saved in one request —
    // the names only make sense reviewed together.
    const [draftSpeakers, setDraftSpeakers] = useState<SpeakerRow[]>(speakers);
    const [savedSpeakers, setSavedSpeakers] = useState<SpeakerRow[]>(speakers);
    const [savingSpeakers, setSavingSpeakers] = useState(false);

    // Reset the draft when the server sends a new version of the panel (after a
    // save, or a re-run of the summary). Done during render rather than in an
    // effect so the edited names never flash on screen before being replaced.
    if (savedSpeakers !== speakers) {
        setSavedSpeakers(speakers);
        setDraftSpeakers(speakers);
    }

    const newItem = useForm<{
        text: string;
        assignee_employee_id: string;
        due_date: string;
    }>({ text: '', assignee_employee_id: '', due_date: '' });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const unconfirmed = draftSpeakers.filter(
        (speaker) => speaker.guessed_by_ai,
    ).length;

    const saveSpeakers = () => {
        setSavingSpeakers(true);

        router.put(
            MeetingController.updateSpeakers(meeting.id).url,
            {
                speakers: draftSpeakers.map((speaker) => ({
                    speaker_index: speaker.speaker_index,
                    employee_id: speaker.employee_id,
                    display_name: speaker.display_name,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setSavingSpeakers(false),
            },
        );
    };

    const patchSpeaker = (index: number, patch: Partial<SpeakerRow>) => {
        setDraftSpeakers((current) =>
            current.map((speaker) =>
                speaker.speaker_index === index
                    ? { ...speaker, ...patch }
                    : speaker,
            ),
        );
    };

    const toggleItem = (item: ActionItemRow) => {
        router.put(
            MeetingController.updateActionItem({
                meeting: meeting.id,
                actionItem: item.id,
            }).url,
            {
                status: item.status === 'done' ? 'open' : 'done',
                assignee_employee_id: item.assignee_employee_id,
                due_date: item.due_date,
            },
            { preserveScroll: true },
        );
    };

    const addItem = () => {
        newItem.post(MeetingController.storeActionItem(meeting.id).url, {
            preserveScroll: true,
            onSuccess: () => newItem.reset(),
        });
    };

    return (
        <>
            <Head title={meeting.title} />

            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <button
                            type="button"
                            style={{
                                ...btnOut,
                                height: 32,
                                padding: '0 11px',
                                marginBottom: 10,
                            }}
                            onClick={() =>
                                router.visit(MeetingController.index().url)
                            }
                        >
                            <AIcon name="chevron-left" size={14} />
                            Semua rapat
                        </button>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            {meeting.title}
                        </h1>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 5,
                            }}
                        >
                            {formatDateTime(meeting.started_at)} ·{' '}
                            {meeting.duration_minutes} menit ·{' '}
                            {meeting.location ?? 'tanpa lokasi'}
                            {meeting.recorded_by
                                ? ` · direkam ${meeting.recorded_by}`
                                : ''}
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        {meeting.has_audio && (
                            <a
                                href={
                                    MeetingController.download(meeting.id).url
                                }
                                style={{
                                    ...btnExport,
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon name="download" size={15} color="#fff" />
                                Unduh Audio
                            </a>
                        )}
                        {can.update && (
                            <button
                                type="button"
                                style={btnProcess}
                                onClick={() =>
                                    router.post(
                                        MeetingController.reprocess(meeting.id)
                                            .url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <AIcon
                                    name="refresh-cw"
                                    size={15}
                                    color="#fff"
                                />
                                Proses Ulang
                            </button>
                        )}
                        {can.archive && (
                            <button
                                type="button"
                                style={btnDanger}
                                onClick={() =>
                                    router.delete(
                                        MeetingController.destroy(meeting.id)
                                            .url,
                                    )
                                }
                            >
                                <AIcon name="trash-2" size={15} color="#fff" />
                                Hapus
                            </button>
                        )}
                    </div>
                </div>

                {meeting.status !== 'ready' && (
                    <div
                        style={{
                            ...card,
                            padding: '14px 18px',
                            marginBottom: 18,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            background:
                                meeting.status === 'failed'
                                    ? hexA(C.red, 0.06)
                                    : '#FDF4E7',
                        }}
                    >
                        <AIcon
                            name={
                                meeting.status === 'failed'
                                    ? 'circle-alert'
                                    : 'loader'
                            }
                            size={16}
                            color={
                                meeting.status === 'failed' ? C.red : C.amber
                            }
                        />
                        <span style={{ fontSize: 13.5, color: C.text }}>
                            {meeting.status === 'failed'
                                ? (meeting.failure_reason ??
                                  'Ringkasan gagal dibuat.')
                                : `Status: ${STATUS_LABELS[meeting.status]}. Ringkasan muncul setelah proses selesai.`}
                        </span>
                    </div>
                )}

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'minmax(0, 1.35fr) minmax(280px, 1fr)',
                        gap: 20,
                        alignItems: 'start',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 20,
                            minWidth: 0,
                        }}
                    >
                        {/* Numbers before prose: they take a second to read
                            and frame everything under them. */}
                        <MeetingStatTiles stats={stats} />

                        <TurnTimeline stats={stats} onSeek={seekToLine} />

                        <div style={{ ...card, padding: 22 }}>
                            <div style={sectionTitle}>Ringkasan</div>
                            <div style={sectionHint}>
                                Dibuat otomatis dengan model{' '}
                                {meeting.summary_model ?? '-'} ·{' '}
                                {meeting.summary_tokens.toLocaleString('id-ID')}{' '}
                                token
                            </div>
                            {meeting.summary || meeting.decisions.length > 0 ? (
                                <>
                                    {meeting.summary && (
                                        <div style={proseStyle}>
                                            {paragraphsOf(meeting.summary).map(
                                                (paragraph, index) => (
                                                    <p
                                                        key={index}
                                                        style={{
                                                            margin:
                                                                index === 0
                                                                    ? '0 0 14px'
                                                                    : '0 0 14px',
                                                            whiteSpace:
                                                                'pre-wrap',
                                                        }}
                                                    >
                                                        {paragraph}
                                                    </p>
                                                ),
                                            )}
                                        </div>
                                    )}

                                    {meeting.decisions.length > 0 && (
                                        <div style={{ marginTop: 18 }}>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    fontWeight: 700,
                                                    letterSpacing: '.06em',
                                                    color: C.faint,
                                                    marginBottom: 10,
                                                }}
                                            >
                                                KEPUTUSAN
                                            </div>
                                            {meeting.decisions.map(
                                                (decision, index) => (
                                                    <div
                                                        key={index}
                                                        style={{
                                                            display: 'flex',
                                                            gap: 9,
                                                            alignItems:
                                                                'flex-start',
                                                            marginBottom: 8,
                                                        }}
                                                    >
                                                        <span
                                                            style={{
                                                                paddingTop: 2,
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="circle-check"
                                                                size={14}
                                                                color={C.green}
                                                            />
                                                        </span>
                                                        <span
                                                            style={{
                                                                fontSize: 14.5,
                                                                color: C.text,
                                                                lineHeight: 1.65,
                                                                maxWidth:
                                                                    '62ch',
                                                            }}
                                                        >
                                                            {decision}
                                                        </span>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div style={{ fontSize: 13.5, color: C.faint }}>
                                    Belum ada ringkasan.
                                </div>
                            )}
                        </div>

                        <div style={{ ...card, padding: 22 }}>
                            <div style={sectionTitle}>
                                Analisis Mendalam (AI)
                            </div>
                            <div style={sectionHint}>
                                Memakai model {proModel} dan memotong token
                                perusahaan tiap kali dibuat, jadi hasilnya
                                disimpan — tekan Perbarui hanya bila memang
                                ingin dihitung ulang.
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 12,
                                }}
                            >
                                {insights.map((insight) => (
                                    <InsightPanel
                                        key={insight.type}
                                        insight={insight}
                                        disabled={meeting.status !== 'ready'}
                                        onGenerate={(refresh) =>
                                            router.post(
                                                MeetingController.generateInsight(
                                                    {
                                                        meeting: meeting.id,
                                                        type: insight.type,
                                                    },
                                                ).url,
                                                { refresh },
                                                { preserveScroll: true },
                                            )
                                        }
                                    />
                                ))}
                            </div>
                        </div>

                        <div style={{ ...card, padding: 22 }}>
                            <div style={sectionTitle}>Transkrip</div>
                            <div style={sectionHint}>
                                {transcript.length} ucapan ·{' '}
                                {draftSpeakers.length} pembicara
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    // Turns need more air between them than the
                                    // lines inside one turn, or the grouping
                                    // reads as a single block again.
                                    gap: 20,
                                    maxHeight: 620,
                                    overflowY: 'auto',
                                }}
                            >
                                {transcript.length === 0 && (
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            color: C.faint,
                                        }}
                                    >
                                        Tidak ada ucapan yang tertranskrip.
                                    </div>
                                )}
                                {groupBySpeaker(transcript).map((block) => (
                                    <div
                                        key={`${block.speakerIndex}-${block.lines[0].id}`}
                                        style={{ minWidth: 0 }}
                                    >
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                fontWeight: 700,
                                                color: C.primary,
                                                marginBottom: 6,
                                            }}
                                        >
                                            {block.speaker}
                                            <span
                                                style={{
                                                    fontWeight: 500,
                                                    color: C.faint,
                                                    fontVariantNumeric:
                                                        'tabular-nums',
                                                }}
                                            >
                                                {' '}
                                                · {block.lines[0].timecode}
                                            </span>
                                        </div>
                                        {/* The whole turn reads as one
                                            passage: a timecode per line stays
                                            available in the gutter, but the
                                            name is printed once. */}
                                        {block.lines.map((line) => (
                                            <div
                                                key={line.id}
                                                id={`ucapan-${line.id}`}
                                                style={{
                                                    display: 'flex',
                                                    gap: 12,
                                                    alignItems: 'flex-start',
                                                    borderRadius: 6,
                                                    transition:
                                                        'background 400ms',
                                                }}
                                            >
                                                <span
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                        fontVariantNumeric:
                                                            'tabular-nums',
                                                        paddingTop: 4,
                                                        minWidth: 42,
                                                    }}
                                                >
                                                    {line.timecode}
                                                </span>
                                                <div
                                                    style={{
                                                        fontSize: 14.5,
                                                        color: C.text,
                                                        lineHeight: 1.7,
                                                        maxWidth: '62ch',
                                                        minWidth: 0,
                                                    }}
                                                >
                                                    {line.text}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 20,
                            minWidth: 0,
                        }}
                    >
                        {/* Sits above the naming panel: seeing who dominated
                            is what makes a reader want to put names to the
                            speaker numbers in the first place. */}
                        <TalkShareChart stats={stats} />

                        <div style={{ ...card, padding: 22 }}>
                            <div style={sectionTitle}>Pembicara</div>
                            <div style={sectionHint}>
                                {unconfirmed > 0
                                    ? `${unconfirmed} nama masih tebakan AI — konfirmasi agar transkrip menyebut nama yang benar.`
                                    : 'Semua nama sudah dikonfirmasi.'}
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 14,
                                }}
                            >
                                {draftSpeakers.map((speaker) => (
                                    <div key={speaker.speaker_index}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'space-between',
                                                gap: 8,
                                                marginBottom: 6,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    fontSize: 12.5,
                                                    fontWeight: 600,
                                                    color: C.text,
                                                }}
                                            >
                                                Pembicara{' '}
                                                {speaker.speaker_index + 1}
                                                <span
                                                    style={{
                                                        color: C.faint,
                                                        fontWeight: 400,
                                                    }}
                                                >
                                                    {' '}
                                                    · {speaker.lines} ucapan
                                                </span>
                                            </span>
                                            {speaker.guessed_by_ai && (
                                                <span
                                                    style={{
                                                        fontSize: 11,
                                                        fontWeight: 600,
                                                        color: C.amber,
                                                        background: '#FDF4E7',
                                                        padding: '2px 7px',
                                                        borderRadius: 999,
                                                    }}
                                                >
                                                    tebakan AI
                                                </span>
                                            )}
                                        </div>
                                        {/* Searchable: a tenant with a few
                                            hundred staff makes a plain select
                                            a scroll hunt, and the reader
                                            already knows the name they want. */}
                                        <div style={{ marginBottom: 6 }}>
                                            <SearchableSelect
                                                value={
                                                    speaker.employee_id !== null
                                                        ? String(
                                                              speaker.employee_id,
                                                          )
                                                        : ''
                                                }
                                                options={employees.map(
                                                    (employee) => ({
                                                        value: String(
                                                            employee.id,
                                                        ),
                                                        label: employee.full_name,
                                                    }),
                                                )}
                                                onChange={(value) =>
                                                    patchSpeaker(
                                                        speaker.speaker_index,
                                                        {
                                                            employee_id:
                                                                value === ''
                                                                    ? null
                                                                    : Number(
                                                                          value,
                                                                      ),
                                                        },
                                                    )
                                                }
                                                placeholder="— pilih karyawan —"
                                                searchPlaceholder="Cari karyawan…"
                                                disabled={!can.update}
                                                allowClear
                                            />
                                        </div>
                                        <input
                                            style={inputStyle}
                                            disabled={!can.update}
                                            placeholder="atau tulis nama/label bebas"
                                            value={speaker.display_name ?? ''}
                                            onChange={(event) =>
                                                patchSpeaker(
                                                    speaker.speaker_index,
                                                    {
                                                        display_name:
                                                            event.target.value,
                                                    },
                                                )
                                            }
                                        />
                                    </div>
                                ))}

                                {draftSpeakers.length === 0 && (
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            color: C.faint,
                                        }}
                                    >
                                        Belum ada pembicara terdeteksi.
                                    </div>
                                )}
                            </div>

                            {can.update && draftSpeakers.length > 0 && (
                                <button
                                    type="button"
                                    style={{
                                        ...btnSave,
                                        marginTop: 16,
                                        opacity: savingSpeakers ? 0.6 : 1,
                                    }}
                                    disabled={savingSpeakers}
                                    onClick={saveSpeakers}
                                >
                                    <AIcon name="save" size={15} color="#fff" />
                                    Simpan Nama
                                </button>
                            )}
                        </div>

                        <div style={{ ...card, padding: 22 }}>
                            <div style={sectionTitle}>Tindak Lanjut</div>
                            <div style={sectionHint}>
                                Diusulkan AI, boleh diubah dan ditambah.
                            </div>

                            <ActionItemMeter stats={stats} />

                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 10,
                                    marginBottom: can.update ? 16 : 0,
                                }}
                            >
                                {actionItems.length === 0 && (
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            color: C.faint,
                                        }}
                                    >
                                        Belum ada tindak lanjut.
                                    </div>
                                )}
                                {actionItems.map((item) => (
                                    <div
                                        key={item.id}
                                        style={{
                                            display: 'flex',
                                            gap: 10,
                                            alignItems: 'flex-start',
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            style={{ marginTop: 3 }}
                                            disabled={!can.update}
                                            checked={item.status === 'done'}
                                            onChange={() => toggleItem(item)}
                                        />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 14,
                                                    color:
                                                        item.status === 'done'
                                                            ? C.faint
                                                            : C.text,
                                                    lineHeight: 1.6,
                                                    textDecoration:
                                                        item.status === 'done'
                                                            ? 'line-through'
                                                            : 'none',
                                                }}
                                            >
                                                {item.text}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    marginTop: 2,
                                                }}
                                            >
                                                {item.assignee ??
                                                    'tanpa penanggung jawab'}
                                                {item.due_date
                                                    ? ` · ${item.due_date}`
                                                    : ''}
                                                {item.source === 'manual'
                                                    ? ' · manual'
                                                    : ''}
                                            </div>
                                        </div>
                                        {can.update && (
                                            <button
                                                type="button"
                                                style={{
                                                    background: 'none',
                                                    border: 'none',
                                                    cursor: 'pointer',
                                                    padding: 2,
                                                }}
                                                onClick={() =>
                                                    router.delete(
                                                        MeetingController.destroyActionItem(
                                                            {
                                                                meeting:
                                                                    meeting.id,
                                                                actionItem:
                                                                    item.id,
                                                            },
                                                        ).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <AIcon
                                                    name="x"
                                                    size={14}
                                                    color={C.faint}
                                                />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {can.update && (
                                <div
                                    style={{
                                        borderTop: `1px solid ${C.line}`,
                                        paddingTop: 14,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 10,
                                    }}
                                >
                                    <input
                                        style={inputStyle}
                                        placeholder="Tambah tindak lanjut…"
                                        value={newItem.data.text}
                                        onChange={(event) =>
                                            newItem.setData(
                                                'text',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <SearchableSelect
                                        value={
                                            newItem.data.assignee_employee_id
                                        }
                                        options={employees.map((employee) => ({
                                            value: String(employee.id),
                                            label: employee.full_name,
                                        }))}
                                        onChange={(value) =>
                                            newItem.setData(
                                                'assignee_employee_id',
                                                value,
                                            )
                                        }
                                        placeholder="— penanggung jawab —"
                                        searchPlaceholder="Cari karyawan…"
                                        allowClear
                                    />
                                    <DatePicker
                                        value={newItem.data.due_date}
                                        onChange={(v) =>
                                            newItem.setData('due_date', v)
                                        }
                                        placeholder="Jatuh tempo"
                                        width="100%"
                                    />
                                    <button
                                        type="button"
                                        style={{
                                            ...btnOut,
                                            justifyContent: 'center',
                                        }}
                                        disabled={
                                            newItem.processing ||
                                            newItem.data.text.trim() === ''
                                        }
                                        onClick={addItem}
                                    >
                                        <AIcon name="plus" size={15} />
                                        Tambah
                                    </button>
                                </div>
                            )}
                        </div>

                        {can.update && (
                            <div style={{ ...card, padding: 22 }}>
                                <div style={sectionTitle}>Akses</div>
                                <div style={sectionHint}>
                                    Siapa yang boleh membaca transkrip ini di
                                    aplikasi HP.
                                </div>
                                <select
                                    style={{
                                        ...inputStyle,
                                        cursor: 'pointer',
                                    }}
                                    value={meeting.visibility}
                                    onChange={(event) =>
                                        router.put(
                                            MeetingController.updateVisibility(
                                                meeting.id,
                                            ).url,
                                            { visibility: event.target.value },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <option value="participants">
                                        Hanya peserta rapat
                                    </option>
                                    <option value="tenant">
                                        Semua karyawan
                                    </option>
                                </select>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
