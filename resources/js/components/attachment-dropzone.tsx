import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import AttachmentController from '@/actions/App/Http/Controllers/AttachmentController';
import { cn } from '@/lib/utils';

export function AttachmentDropzone({
    leadId,
    onUploaded,
}: {
    leadId: number;
    onUploaded?: () => void;
}) {
    const [uploading, setUploading] = useState(false);

    const onDrop = useCallback(
        (files: File[]) => {
            if (files.length === 0) {
                return;
            }

            setUploading(true);
            router.post(
                AttachmentController.store(leadId).url,
                { file: files[0] },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => onUploaded?.(),
                    onFinish: () => setUploading(false),
                },
            );
        },
        [leadId, onUploaded],
    );

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        multiple: false,
    });

    return (
        <div
            {...getRootProps()}
            className={cn(
                'cursor-pointer rounded-md border border-dashed border-border px-3 py-4 text-center text-xs text-muted-foreground transition-colors duration-150 ease-[var(--ease-out)] hover:border-ring/50 hover:text-foreground',
                isDragActive && 'border-ring bg-accent text-foreground',
            )}
        >
            <input {...getInputProps()} />
            {uploading
                ? 'Uploading…'
                : isDragActive
                  ? 'Drop the file here'
                  : 'Drop a file or click to upload'}
        </div>
    );
}
