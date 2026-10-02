import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export default function AdjustmentNoteDialog({
    invoiceId,
    type,
    balance,
}: {
    invoiceId: number;
    type: 'credit_note' | 'debit_note';
    balance: string;
}) {
    const [open, setOpen] = useState(false);
    const isCredit = type === 'credit_note';
    const label = isCredit ? 'Credit note' : 'Debit note';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">{label}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Issue {isCredit ? 'credit' : 'debit'} note</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    {isCredit
                        ? `Reduces what ${label === 'Credit note' ? 'the customer owes' : 'is owed'} (current balance: ${balance}).`
                        : 'Records an extra charge against this invoice.'}
                </p>
                <Form
                    {...InvoiceController.storeNote.form(invoiceId)}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="type" value={type} />
                            <div className="grid gap-2">
                                <Label htmlFor="note-amount">Amount</Label>
                                <Input
                                    id="note-amount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    defaultValue={isCredit ? balance : ''}
                                    placeholder="0.00"
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="note-tax-rate">GST rate applied to this note (%)</Label>
                                <Input
                                    id="note-tax-rate"
                                    name="tax_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    defaultValue="0"
                                />
                                <InputError message={errors.tax_rate} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="note-reason">Reason (optional)</Label>
                                <Textarea id="note-reason" name="reason" rows={3} maxLength={500} />
                                <InputError message={errors.reason} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing ? 'Issuing…' : `Issue ${label.toLowerCase()}`}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
