import { Form, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import QuotationTemplateController from '@/actions/App/Http/Controllers/Settings/QuotationTemplateController';

type QuotationTemplate = {
    id: number;
    name: string;
    description: string | null;
    items: Array<Record<string, unknown>> | null;
    charges: Array<Record<string, unknown>> | null;
    is_active: boolean;
    created_at: string;
};

export default function QuotationTemplatesPage({ templates }: { templates: QuotationTemplate[] }) {
    return (
        <>
            <Head title="Quotation templates" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Quotation templates"
                    description="Save reusable product sets and charges to build quotations faster."
                />

                <div className="flex justify-end">
                    <AddTemplateDialog />
                </div>

                {templates.length === 0 ? (
                    <Card>
                        <CardContent className="py-8 text-center text-sm text-muted-foreground">
                            No templates yet. Create one to speed up quotation building.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {templates.map((template) => (
                            <Card key={template.id} className="flex flex-col">
                                <CardContent className="flex flex-1 flex-col gap-2 p-4">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-semibold text-sm">{template.name}</h3>
                                        {!template.is_active && (
                                            <Badge variant="secondary" className="text-xs">inactive</Badge>
                                        )}
                                    </div>
                                    {template.description && (
                                        <p className="text-xs text-muted-foreground line-clamp-2">{template.description}</p>
                                    )}
                                    <div className="mt-auto flex items-center justify-between pt-3">
                                        <span className="text-xs text-muted-foreground">
                                            {template.items?.length ?? 0} items
                                        </span>
                                        <EditTemplateDialog template={template} />
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function emptyTemplate() {
    return {
        name: '',
        description: '',
        items: [],
        charges: [],
        is_active: true,
    };
}

function AddTemplateDialog() {
    const [open, setOpen] = useState(false);
    const [data, setData] = useState(emptyTemplate);

    function reset() {
        setData(emptyTemplate());
        setOpen(false);
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4 mr-1.5" />
                    New template
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>New quotation template</DialogTitle>
                </DialogHeader>
                <Form
                    {...QuotationTemplateController.store.form()}
                    onSuccess={reset}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    value={data.name}
                                    onChange={(e) => setData((current) => ({ ...current, name: e.target.value }))}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows={2}
                                    value={data.description}
                                    onChange={(e) => setData((current) => ({ ...current, description: e.target.value }))}
                                />
                                <InputError message={errors.description} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>Create template</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditTemplateDialog({ template }: { template: QuotationTemplate }) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState(template.name);
    const [description, setDescription] = useState(template.description ?? '');
    const [isActive, setIsActive] = useState(template.is_active);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">Edit</Button>
            </DialogTrigger>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit template</DialogTitle>
                </DialogHeader>
                <Form
                    {...QuotationTemplateController.update.form(template.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows={2}
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                />
                                <InputError message={errors.description} />
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    id="is_active"
                                    name="is_active"
                                    type="checkbox"
                                    checked={isActive}
                                    onChange={(e) => setIsActive(e.target.checked)}
                                />
                                <Label htmlFor="is_active" className="text-sm font-medium">Active</Label>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>Save changes</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
