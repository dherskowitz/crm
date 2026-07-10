import { ReactRenderer } from '@tiptap/react';
import type { SuggestionOptions } from '@tiptap/suggestion';
import { forwardRef, useEffect, useImperativeHandle, useState } from 'react';
import { cn } from '@/lib/utils';

export type Mentionable = { id: number; name: string };

type MentionListProps = {
    items: Mentionable[];
    command: (item: { id: number; label: string }) => void;
};

export type MentionListHandle = {
    onKeyDown: (props: { event: KeyboardEvent }) => boolean;
};

const MentionList = forwardRef<MentionListHandle, MentionListProps>(
    (props, ref) => {
        const [selected, setSelected] = useState(0);

        useEffect(() => setSelected(0), [props.items]);

        const select = (index: number) => {
            const item = props.items[index];

            if (item) {
                props.command({ id: item.id, label: item.name });
            }
        };

        useImperativeHandle(ref, () => ({
            onKeyDown: ({ event }) => {
                if (event.key === 'ArrowUp') {
                    setSelected(
                        (s) =>
                            (s + props.items.length - 1) % props.items.length,
                    );

                    return true;
                }

                if (event.key === 'ArrowDown') {
                    setSelected((s) => (s + 1) % props.items.length);

                    return true;
                }

                if (event.key === 'Enter') {
                    select(selected);

                    return true;
                }

                return false;
            },
        }));

        if (props.items.length === 0) {
            return null;
        }

        return (
            <div className="z-50 max-h-48 w-56 overflow-y-auto rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-[var(--shadow-overlay)]">
                {props.items.map((item, index) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => select(index)}
                        className={cn(
                            'block w-full rounded-md px-2 py-1 text-left text-sm transition-colors duration-150 ease-[var(--ease-out)] outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            index === selected
                                ? 'bg-accent text-accent-foreground'
                                : 'hover:bg-accent/60',
                        )}
                    >
                        {item.name}
                    </button>
                ))}
            </div>
        );
    },
);

MentionList.displayName = 'MentionList';

/**
 * Build the Tiptap mention suggestion config from a list of mentionable users.
 * The inserted mention node carries the user id so the backend can read
 * referenced users straight from the document.
 */
export function mentionSuggestion(
    items: Mentionable[],
): Omit<SuggestionOptions, 'editor'> {
    return {
        items: ({ query }) =>
            items
                .filter((item) =>
                    item.name.toLowerCase().includes(query.toLowerCase()),
                )
                .slice(0, 5),
        render: () => {
            let component: ReactRenderer<MentionListHandle, MentionListProps>;
            let popup: HTMLDivElement;

            const position = (
                clientRect: (() => DOMRect | null) | null | undefined,
            ) => {
                const rect = clientRect?.();

                if (!rect || !popup) {
                    return;
                }

                popup.style.left = `${rect.left + window.scrollX}px`;
                popup.style.top = `${rect.bottom + window.scrollY + 4}px`;
            };

            return {
                onStart: (props) => {
                    component = new ReactRenderer(MentionList, {
                        props,
                        editor: props.editor,
                    });
                    popup = document.createElement('div');
                    popup.style.position = 'absolute';
                    popup.appendChild(component.element);
                    document.body.appendChild(popup);
                    position(props.clientRect);
                },
                onUpdate: (props) => {
                    component.updateProps(props);
                    position(props.clientRect);
                },
                onKeyDown: (props) => {
                    if (props.event.key === 'Escape') {
                        popup.remove();

                        return true;
                    }

                    return component.ref?.onKeyDown(props) ?? false;
                },
                onExit: () => {
                    popup.remove();
                    component.destroy();
                },
            };
        },
    };
}
