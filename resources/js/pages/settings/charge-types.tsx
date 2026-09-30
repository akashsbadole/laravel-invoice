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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ChargeTypeController from '@/actions/App/Http/Controllers/Settings/ChargeTypeController';
import type { ChargeType } from '@/types/invoice';

const calcLabels: Record<string, string> = {
    fixed: 'Fixed amount',
    percentage: '% of item value',
    per_gram: 'Per gram',
    per_carat: 'Per carat',
};

export default function ChargeTypesPage({ chargeTypes }: { chargeTypes: ChargeType[] }) {
    const itemTypes = chargeTypes.filter((ct) => ct.applies_to === 'item');
    const invoiceTypes = chargeTypes.filter((ct) => ct.applies_to === 'invoice');

    return (
        <>
            <Head title="Charge types" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Charge types"
                    description="These drive what appears on every invoice item and how it's calculated"
                />

                <div className="flex justify-end">
                    <AddChargeTypeDialog />
                </div>

                <ChargeTypeTable title="Per-item charges" items={itemTypes} />
                <ChargeTypeTable title="Whole-invoice charges" items={invoiceTypes} />
            </div>
        </>
    );
}

function ChargeTypeTable({ title, items }: { title: string; items: ChargeType[] }) {
    return (
        <Card>
            <CardContent className="space-y-1 p-4">
                <h3 className="mb-2 text-sm font-semibold">{title}</h3>
                {items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">None yet.</p>
                ) : (
                    items.map((ct) => (
                        <div
                            key={ct.id}
                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="font-medium">{ct.name}</span>
                                    {ct.is_system && (
                                        <Badge variant="outline" className="text-xs">
                                            built-in
                                        </Badge>
                                    )}
                                    {!ct.is_active && (
                                        <Badge variant="secondary" className="text-xs">
                                            inactive
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {calcLabels[ct.calculation_type]}
                                    {ct.default_rate !== null && ` · default ${ct.default_rate}`}
                                    {!ct.is_taxable && ' · not taxed'}
                                </p>
                            </div>
                            <EditChargeTypeDialog chargeType={ct} />
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function AddChargeTypeDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Add charge type
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New charge type</DialogTitle>
                </DialogHeader>
                <Form
                    {...ChargeTypeController.store.form()}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" name="name" placeholder="e.g. Rhodium Plating" required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="code">Code</Label>
                                <Input id="code" name="code" placeholder="rhodium_plating" required />
                                <InputError message={errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="applies_to">Applies to</Label>
                                <Select name="applies_to" defaultValue="item">
                                    <SelectTrigger id="applies_to" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="item">Per invoice item</SelectItem>
                                        <SelectItem value="invoice">Whole invoice</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="calculation_type">Calculation</Label>
                                <Select name="calculation_type" defaultValue="fixed">
                                    <SelectTrigger id="calculation_type" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="fixed">Fixed amount</SelectItem>
                                        <SelectItem value="percentage">% of item value</SelectItem>
                                        <SelectItem value="per_gram">Per gram</SelectItem>
                                        <SelectItem value="per_carat">Per carat</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.calculation_type} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="default_rate">Default rate (optional)</Label>
                                <Input id="default_rate" name="default_rate" type="number" step="0.01" min={0} />
                            </div>
                            <div className="flex items-center gap-2">
                                <input type="checkbox" id="is_taxable" name="is_taxable" defaultChecked className="size-4" />
                                <Label htmlFor="is_taxable">Include in taxable value</Label>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>Add</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditChargeTypeDialog({ chargeType }: { chargeType: ChargeType }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">Edit</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {chargeType.name}</DialogTitle>
                </DialogHeader>
                <Form
                    {...ChargeTypeController.update.form(chargeType.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`name-${chargeType.id}`}>Name</Label>
                                <Input
                                    id={`name-${chargeType.id}`}
                                    name="name"
                                    defaultValue={chargeType.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`calc-${chargeType.id}`}>Calculation</Label>
                                <Select
                                    name="calculation_type"
                                    defaultValue={chargeType.calculation_type}
                                >
                                    <SelectTrigger id={`calc-${chargeType.id}`} className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="fixed">Fixed amount</SelectItem>
                                        <SelectItem value="percentage">% of item value</SelectItem>
                                        <SelectItem value="per_gram">Per gram</SelectItem>
                                        <SelectItem value="per_carat">Per carat</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`rate-${chargeType.id}`}>Default rate</Label>
                                <Input
                                    id={`rate-${chargeType.id}`}
                                    name="default_rate"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    defaultValue={chargeType.default_rate ?? ''}
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id={`taxable-${chargeType.id}`}
                                    name="is_taxable"
                                    defaultChecked={chargeType.is_taxable}
                                    className="size-4"
                                />
                                <Label htmlFor={`taxable-${chargeType.id}`}>Include in taxable value</Label>
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id={`active-${chargeType.id}`}
                                    name="is_active"
                                    defaultChecked={chargeType.is_active}
                                    className="size-4"
                                />
                                <Label htmlFor={`active-${chargeType.id}`}>Active</Label>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Close</Button>
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
