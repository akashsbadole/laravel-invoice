import { Form, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import InvoiceTemplateController from '@/actions/App/Http/Controllers/Settings/InvoiceTemplateController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { InvoiceTemplate } from '@/types/invoice';

const allToggles: { name: keyof InvoiceTemplate['layout_config']; label: string }[] = [
    { name: 'show_huid', label: 'Show HUID (hallmark ID)' },
    { name: 'show_hsn', label: 'Show HSN code' },
    { name: 'show_stone_details', label: 'Show stone / certificate details' },
    { name: 'show_bank_details', label: 'Show bank details' },
    { name: 'show_signature', label: 'Show signature' },
    { name: 'show_stamp', label: 'Show stamp / seal' },
    { name: 'show_qr_code', label: 'Show QR code (needs simple-qrcode package)' },
];

export default function InvoiceTemplatesPage({
    templates,
    industryTemplateFlags,
}: {
    templates: InvoiceTemplate[];
    industryTemplateFlags: string[];
}) {
    // Jewelry-only toggles are meaningless for a tiles or hardware template.
    const toggles = allToggles.filter(
        (toggle) =>
            industryTemplateFlags.length === 0 ||
            industryTemplateFlags.includes(toggle.name),
    );
    return (
        <>
            <Head title="Invoice templates" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Invoice templates"
                    description="Control what appears on the printed invoice, the PDF, and the customer's share page"
                />

                <div className="flex justify-end">
                    <TemplateDialog mode="create" toggles={toggles} />
                </div>

                <div className="space-y-2">
                    {templates.map((template) => (
                        <Card key={template.id}>
                            <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                                <div className="flex items-center gap-3">
                                    <span
                                        className="size-6 rounded-full border"
                                        style={{ backgroundColor: template.layout_config.accent_color }}
                                    />
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{template.name}</span>
                                            {template.is_default && <Badge>default</Badge>}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {template.layout_config.header_alignment} header ·{' '}
                                            {toggles.filter((t) => template.layout_config[t.name]).length} of{' '}
                                            {toggles.length} sections shown
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <TemplateDialog mode="edit" template={template} toggles={toggles} />
                                    {!template.is_default && (
                                        <>
                                            <Form {...InvoiceTemplateController.setDefault.form(template.id)}>
                                                {({ processing }) => (
                                                    <Button size="sm" variant="outline" disabled={processing}>
                                                        Make default
                                                    </Button>
                                                )}
                                            </Form>
                                            <Form {...InvoiceTemplateController.destroy.form(template.id)}>
                                                {({ processing }) => (
                                                    <Button size="sm" variant="ghost" disabled={processing}>
                                                        Delete
                                                    </Button>
                                                )}
                                            </Form>
                                        </>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

function TemplateDialog({
    mode,
    template,
    toggles,
}: {
    mode: 'create' | 'edit';
    template?: InvoiceTemplate;
    toggles: typeof allToggles;
}) {
    const [open, setOpen] = useState(false);
    const config = template?.layout_config;
    const formProps =
        mode === 'create'
            ? InvoiceTemplateController.store.form()
            : InvoiceTemplateController.update.form(template!.id);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {mode === 'create' ? (
                    <Button size="sm">
                        <Plus className="size-4" />
                        New template
                    </Button>
                ) : (
                    <Button size="sm" variant="outline">
                        Customise
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{mode === 'create' ? 'New template' : `Customise ${template?.name}`}</DialogTitle>
                </DialogHeader>
                <Form
                    {...formProps}
                    resetOnSuccess={mode === 'create'}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`tpl-name-${template?.id ?? 'new'}`}>Name</Label>
                                <Input
                                    id={`tpl-name-${template?.id ?? 'new'}`}
                                    name="name"
                                    defaultValue={template?.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            {mode === 'create' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="tpl-slug">Slug</Label>
                                    <Input id="tpl-slug" name="slug" placeholder="standard" required />
                                    <InputError message={errors.slug} />
                                </div>
                            )}

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor={`tpl-color-${template?.id ?? 'new'}`}>Accent colour</Label>
                                    <input
                                        id={`tpl-color-${template?.id ?? 'new'}`}
                                        name="accent_color"
                                        type="color"
                                        defaultValue={config?.accent_color ?? '#0f172a'}
                                        className="h-9 w-full cursor-pointer rounded-md border bg-transparent p-1"
                                    />
                                    <InputError message={errors.accent_color} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`tpl-align-${template?.id ?? 'new'}`}>Header alignment</Label>
                                    <Select name="header_alignment" defaultValue={config?.header_alignment ?? 'left'}>
                                        <SelectTrigger id={`tpl-align-${template?.id ?? 'new'}`} className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="left">Left</SelectItem>
                                            <SelectItem value="center">Centre</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Sections to show</Label>
                                {toggles.map((toggle) => (
                                    <div key={toggle.name} className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id={`${toggle.name}-${template?.id ?? 'new'}`}
                                            name={toggle.name}
                                            defaultChecked={config ? Boolean(config[toggle.name]) : true}
                                            className="size-4"
                                        />
                                        <Label htmlFor={`${toggle.name}-${template?.id ?? 'new'}`} className="font-normal">
                                            {toggle.label}
                                        </Label>
                                    </div>
                                ))}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`tpl-footer-${template?.id ?? 'new'}`}>Footer note (optional)</Label>
                                <Input
                                    id={`tpl-footer-${template?.id ?? 'new'}`}
                                    name="footer_note"
                                    defaultValue={config?.footer_note ?? ''}
                                    placeholder="e.g. Exchange within 7 days with this bill"
                                />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>Save</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
