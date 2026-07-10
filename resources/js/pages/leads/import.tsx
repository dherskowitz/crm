import { Head, useForm } from '@inertiajs/react';
import LeadImportController from '@/actions/App/Http/Controllers/LeadImportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { index as leadsIndex } from '@/routes/leads';

type Props = {
    fields: string[];
    headers?: string[];
    storedPath?: string;
};

const NONE = '__none__';

export default function LeadImport({ fields, headers, storedPath }: Props) {
    const upload = useForm<{ file: File | null }>({ file: null });

    const mapping = useForm<Record<string, string>>(
        Object.fromEntries(fields.map((f) => [f, NONE])),
    );

    const submitUpload = () => {
        upload.post(LeadImportController.preview().url, {
            forceFormData: true,
        });
    };

    const submitMapping = () => {
        mapping.transform((data) => ({
            path: storedPath,
            mapping: Object.fromEntries(
                Object.entries(data).map(([k, v]) => [
                    k,
                    v === NONE ? null : v,
                ]),
            ),
        }));
        mapping.post(LeadImportController.store().url);
    };

    return (
        <div className="px-4 py-6">
            <Head title="Import leads" />
            <Heading
                title="Import leads"
                description="Upload a CSV to migrate existing leads. Duplicates are skipped automatically."
                bordered
            />

            <ol className="mt-6 flex items-center gap-2 text-sm">
                <li
                    className={
                        !headers
                            ? 'flex items-center gap-2 font-medium text-foreground'
                            : 'flex items-center gap-2 text-muted-foreground'
                    }
                >
                    <span
                        className={
                            !headers
                                ? 'inline-flex size-5 items-center justify-center rounded-full bg-primary text-xs font-medium text-primary-foreground tabular-nums'
                                : 'inline-flex size-5 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground tabular-nums'
                        }
                    >
                        1
                    </span>
                    Upload
                </li>
                <li className="h-px w-8 bg-border" aria-hidden />
                <li
                    className={
                        headers
                            ? 'flex items-center gap-2 font-medium text-foreground'
                            : 'flex items-center gap-2 text-muted-foreground'
                    }
                >
                    <span
                        className={
                            headers
                                ? 'inline-flex size-5 items-center justify-center rounded-full bg-primary text-xs font-medium text-primary-foreground tabular-nums'
                                : 'inline-flex size-5 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground tabular-nums'
                        }
                    >
                        2
                    </span>
                    Map columns
                </li>
            </ol>

            {!headers ? (
                <section className="mt-5 max-w-lg animate-in rounded-lg border border-border bg-card duration-200 ease-[var(--ease-out)] fade-in-0">
                    <header className="flex h-11 items-center px-4">
                        <h3 className="text-sm font-semibold tracking-tight">
                            Upload a CSV
                        </h3>
                    </header>
                    <div className="space-y-4 border-t border-border p-4">
                        <div className="grid gap-2">
                            <Label htmlFor="file">CSV file</Label>
                            <Input
                                id="file"
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(e) =>
                                    upload.setData(
                                        'file',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={upload.errors.file} />
                        </div>
                        <Button
                            onClick={submitUpload}
                            disabled={upload.processing || !upload.data.file}
                        >
                            {upload.processing && <Spinner />}
                            Continue to mapping
                        </Button>
                    </div>
                </section>
            ) : (
                <section className="mt-5 max-w-lg animate-in rounded-lg border border-border bg-card duration-200 ease-[var(--ease-out)] fade-in-0">
                    <header className="flex h-11 items-center px-4">
                        <h3 className="text-sm font-semibold tracking-tight">
                            Map columns
                        </h3>
                    </header>
                    <div className="space-y-4 border-t border-border p-4">
                        <p className="text-sm text-muted-foreground">
                            Match each lead field to a column from your file.
                            Name is required.
                        </p>
                        {fields.map((field) => (
                            <div
                                key={field}
                                className="grid grid-cols-2 items-center gap-4"
                            >
                                <Label className="capitalize">
                                    {field}
                                    {field === 'name' && (
                                        <span className="text-destructive">
                                            {' '}
                                            *
                                        </span>
                                    )}
                                </Label>
                                <Select
                                    value={mapping.data[field]}
                                    onValueChange={(value) =>
                                        mapping.setData(field, value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="—" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>—</SelectItem>
                                        {headers.map((header) => (
                                            <SelectItem
                                                key={header}
                                                value={header}
                                            >
                                                {header}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        ))}
                        <InputError message={mapping.errors.path} />
                        <Button
                            onClick={submitMapping}
                            disabled={
                                mapping.processing || mapping.data.name === NONE
                            }
                        >
                            {mapping.processing && <Spinner />}
                            Import leads
                        </Button>
                    </div>
                </section>
            )}
        </div>
    );
}

LeadImport.layout = () => ({
    breadcrumbs: [
        { title: 'Leads', href: leadsIndex() },
        { title: 'Import', href: leadsIndex() },
    ],
});
