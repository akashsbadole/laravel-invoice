import { useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export default function ImageUploadField({
    id,
    name,
    label,
    currentUrl,
    error,
    hint,
}: {
    id: string;
    name: string;
    label: string;
    currentUrl?: string | null;
    error?: string;
    hint?: string;
}) {
    const [preview, setPreview] = useState<string | null>(currentUrl ?? null);

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex items-center gap-4">
                <div
                    className={cn(
                        'flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted text-xs text-muted-foreground',
                    )}
                >
                    {preview ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                            src={preview}
                            alt=""
                            className="size-full object-contain"
                        />
                    ) : (
                        'No file'
                    )}
                </div>
                <div className="flex-1">
                    <input
                        id={id}
                        name={name}
                        type="file"
                        accept="image/*"
                        onChange={(event) => {
                            const file = event.target.files?.[0];
                            if (file) {
                                setPreview(URL.createObjectURL(file));
                            }
                        }}
                        className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-secondary-foreground"
                    />
                    {hint && (
                        <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
                    )}
                </div>
            </div>
            <InputError message={error} />
        </div>
    );
}
