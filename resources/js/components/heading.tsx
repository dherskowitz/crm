import { cn } from '@/lib/utils';

export default function Heading({
    title,
    description,
    variant = 'default',
    bordered = false,
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    /** Adds a hairline divider beneath the title bar to separate it from the body. */
    bordered?: boolean;
}) {
    return (
        <header
            className={cn(
                variant === 'small' ? '' : 'space-y-0.5',
                bordered
                    ? 'mb-6 border-b border-border pb-4'
                    : variant === 'small'
                      ? ''
                      : 'mb-8',
            )}
        >
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-semibold'
                        : 'text-2xl font-semibold tracking-tight text-balance'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
