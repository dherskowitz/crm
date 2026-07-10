import Mention from '@tiptap/extension-mention';
import { EditorContent, useEditor } from '@tiptap/react';
import type { JSONContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect } from 'react';
import { mentionSuggestion } from '@/components/mention-suggestion';
import type { Mentionable } from '@/components/mention-suggestion';
import { cn } from '@/lib/utils';

export const EMPTY_DOC: JSONContent = { type: 'doc', content: [] };

/**
 * Rich-text note editor. Notes are stored as the Tiptap JSON document, never
 * HTML, so rendering is safe without sanitisation.
 */
export function NoteEditor({
    onChange,
    resetSignal,
    mentionables = [],
    placeholder = 'Write a note…',
}: {
    onChange: (doc: JSONContent) => void;
    resetSignal?: number;
    mentionables?: Mentionable[];
    placeholder?: string;
}) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            Mention.configure({
                HTMLAttributes: { class: 'font-medium text-primary-emphasis' },
                suggestion: mentionSuggestion(mentionables),
            }),
        ],
        content: EMPTY_DOC,
        editorProps: {
            attributes: {
                class: 'min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
                'aria-label': placeholder,
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getJSON()),
    });

    useEffect(() => {
        if (editor && resetSignal !== undefined) {
            editor.commands.clearContent();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [resetSignal]);

    return <EditorContent editor={editor} />;
}

/**
 * Read-only renderer for a stored Tiptap note document.
 */
export function NoteViewer({
    doc,
    className,
}: {
    doc: JSONContent | null | undefined;
    className?: string;
}) {
    const editor = useEditor(
        {
            extensions: [
                StarterKit,
                Mention.configure({
                    HTMLAttributes: {
                        class: 'font-medium text-primary-emphasis',
                    },
                }),
            ],
            content: doc ?? EMPTY_DOC,
            editable: false,
        },
        [doc],
    );

    return (
        <EditorContent
            editor={editor}
            className={cn('text-sm leading-relaxed', className)}
        />
    );
}
