import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    forwardRef,
    useEffect,
    useImperativeHandle
    
} from 'react';
import type {CSSProperties} from 'react';
import { AIcon, C } from '@/lib/avana';

export interface RichEditorHandle {
    /** Insert plain text (e.g. a `{{placeholder}}`) at the cursor. */
    insertText: (text: string) => void;
}

interface RichEditorProps {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    hasError?: boolean;
    minHeight?: number;
}

interface ToolbarAction {
    icon: string;
    title: string;
    isActive: (editor: NonNullable<ReturnType<typeof useEditor>>) => boolean;
    run: (editor: NonNullable<ReturnType<typeof useEditor>>) => void;
}

const TOOLBAR_ACTIONS: ToolbarAction[] = [
    {
        icon: 'bold',
        title: 'Tebal',
        isActive: (e) => e.isActive('bold'),
        run: (e) => e.chain().focus().toggleBold().run(),
    },
    {
        icon: 'italic',
        title: 'Miring',
        isActive: (e) => e.isActive('italic'),
        run: (e) => e.chain().focus().toggleItalic().run(),
    },
    {
        icon: 'strikethrough',
        title: 'Coret',
        isActive: (e) => e.isActive('strike'),
        run: (e) => e.chain().focus().toggleStrike().run(),
    },
    {
        icon: 'heading-2',
        title: 'Judul',
        isActive: (e) => e.isActive('heading', { level: 2 }),
        run: (e) => e.chain().focus().toggleHeading({ level: 2 }).run(),
    },
    {
        icon: 'heading-3',
        title: 'Sub-judul',
        isActive: (e) => e.isActive('heading', { level: 3 }),
        run: (e) => e.chain().focus().toggleHeading({ level: 3 }).run(),
    },
    {
        icon: 'list',
        title: 'Daftar poin',
        isActive: (e) => e.isActive('bulletList'),
        run: (e) => e.chain().focus().toggleBulletList().run(),
    },
    {
        icon: 'list-ordered',
        title: 'Daftar bernomor',
        isActive: (e) => e.isActive('orderedList'),
        run: (e) => e.chain().focus().toggleOrderedList().run(),
    },
    {
        icon: 'quote',
        title: 'Kutipan',
        isActive: (e) => e.isActive('blockquote'),
        run: (e) => e.chain().focus().toggleBlockquote().run(),
    },
];

const toolbarStyle: CSSProperties = {
    display: 'flex',
    flexWrap: 'wrap',
    gap: 4,
    padding: 6,
    borderBottom: `1px solid ${C.border}`,
    background: C.surface,
};

/** Headless TipTap rich-text editor styled to match the AvanaHR form fields. */
export const RichEditor = forwardRef<RichEditorHandle, RichEditorProps>(
    function RichEditor(
        { value, onChange, placeholder, hasError, minHeight = 280 },
        ref,
    ) {
        const editor = useEditor({
            extensions: [
                StarterKit.configure({
                    heading: { levels: [2, 3] },
                }),
            ],
            content: value,
            onUpdate: ({ editor }) => onChange(editor.getHTML()),
        });

        useImperativeHandle(
            ref,
            () => ({
                insertText: (text: string) => {
                    editor?.chain().focus().insertContent(text).run();
                },
            }),
            [editor],
        );

        // Sync external resets (e.g. form clear) without clobbering live typing.
        useEffect(() => {
            if (editor && !editor.isFocused && value !== editor.getHTML()) {
                editor.commands.setContent(value, { emitUpdate: false });
            }
        }, [value, editor]);

        const showPlaceholder = editor?.isEmpty && placeholder;

        return (
            <div
                className="avn-rte"
                style={{
                    border: `1px solid ${hasError ? C.red : C.border}`,
                    borderRadius: 8,
                    overflow: 'hidden',
                    background: '#fff',
                    boxShadow: hasError
                        ? '0 0 0 3px rgba(220,38,38,.08)'
                        : undefined,
                }}
            >
                <div style={toolbarStyle}>
                    {TOOLBAR_ACTIONS.map((action) => {
                        const active = editor ? action.isActive(editor) : false;

                        return (
                            <button
                                key={action.icon}
                                type="button"
                                title={action.title}
                                onClick={() => editor && action.run(editor)}
                                style={{
                                    width: 30,
                                    height: 30,
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    border: 'none',
                                    borderRadius: 6,
                                    cursor: 'pointer',
                                    color: active ? C.primary : '#5B6472',
                                    background: active
                                        ? 'rgba(47,84,201,.1)'
                                        : 'transparent',
                                }}
                            >
                                <AIcon name={action.icon} size={16} />
                            </button>
                        );
                    })}
                </div>
                <div
                    style={
                        {
                            position: 'relative',
                            padding: '11px 13px',
                            '--avn-rte-min': `${minHeight - 60}px`,
                        } as CSSProperties
                    }
                >
                    {showPlaceholder && (
                        <div
                            style={{
                                position: 'absolute',
                                top: 11,
                                left: 13,
                                right: 13,
                                color: '#94a3b8',
                                fontSize: 13.5,
                                lineHeight: 1.65,
                                pointerEvents: 'none',
                            }}
                        >
                            {placeholder}
                        </div>
                    )}
                    <EditorContent editor={editor} />
                </div>
            </div>
        );
    },
);

export default RichEditor;
