import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { AttributesEditor } from '@/components/attributes-editor';
import type { Customer, Staff } from '@/types/customer';

type Props = {
    customer?: Partial<Customer>;
    staff: Staff[];
    errors: Record<string, string>;
};

/**
 * Renders as uncontrolled inputs (defaultValue + name) so it drops straight
 * into Inertia's <Form> component — no local state, no onChange wiring.
 */
export default function CustomerFormFields({ customer, staff, errors }: Props) {
    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="full_name">Full name</Label>
                    <Input
                        id="full_name"
                        name="full_name"
                        defaultValue={customer?.full_name}
                        placeholder="e.g. Priya Sharma"
                        autoComplete="name"
                        required
                    />
                    <InputError message={errors.full_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="mobile_number">Mobile number</Label>
                    <Input
                        id="mobile_number"
                        name="mobile_number"
                        type="tel"
                        inputMode="tel"
                        defaultValue={customer?.mobile_number}
                        placeholder="e.g. 98765 43210"
                        autoComplete="tel"
                        required
                    />
                    <InputError message={errors.mobile_number} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email (optional)</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        defaultValue={customer?.email ?? ''}
                        placeholder="name@example.com"
                        autoComplete="email"
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="customer_type">Customer type</Label>
                    <Select
                        name="customer_type"
                        defaultValue={customer?.customer_type ?? 'individual'}
                    >
                        <SelectTrigger id="customer_type" className="w-full">
                            <SelectValue placeholder="Select a type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="individual">Individual</SelectItem>
                            <SelectItem value="business">Business</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.customer_type} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="address">Address (optional)</Label>
                <Textarea
                    id="address"
                    name="address"
                    defaultValue={customer?.address ?? ''}
                    placeholder="Street, city, state, PIN code"
                    rows={2}
                    autoComplete="street-address"
                />
                <InputError message={errors.address} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="tax_number">Tax / GST number (optional)</Label>
                    <Input
                        id="tax_number"
                        name="tax_number"
                        defaultValue={customer?.tax_number ?? ''}
                        placeholder="e.g. 27AAAPZ1234C1ZV"
                    />
                    <InputError message={errors.tax_number} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="assigned_staff_id">Assigned staff (optional)</Label>
                    <Select
                        name="assigned_staff_id"
                        defaultValue={
                            customer?.assigned_staff_id
                                ? String(customer.assigned_staff_id)
                                : undefined
                        }
                    >
                        <SelectTrigger id="assigned_staff_id" className="w-full">
                            <SelectValue placeholder="Unassigned" />
                        </SelectTrigger>
                        <SelectContent>
                            {staff.map((member) => (
                                <SelectItem key={member.id} value={String(member.id)}>
                                    {member.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.assigned_staff_id} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="state_code">GST state code (optional)</Label>
                    <Input
                        id="state_code"
                        name="state_code"
                        maxLength={2}
                        defaultValue={customer?.state_code ?? ''}
                        placeholder="e.g. 27"
                    />
                    <InputError message={errors.state_code} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="birthday">Birthday (optional)</Label>
                    <Input id="birthday" name="birthday" type="date" defaultValue={customer?.birthday ?? ''} />
                    <InputError message={errors.birthday} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="anniversary">Anniversary (optional)</Label>
                    <Input id="anniversary" name="anniversary" type="date" defaultValue={customer?.anniversary ?? ''} />
                    <InputError message={errors.anniversary} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="notes">Notes (optional)</Label>
                <Textarea
                    id="notes"
                    name="notes"
                    defaultValue={customer?.notes ?? ''}
                    placeholder="Anything worth remembering about this customer"
                    rows={3}
                />
                <InputError message={errors.notes} />
            </div>

            <AttributesEditor
                initial={customer?.attributes ?? {}}
                label="Extra attributes"
                hint="Referral source, GSTIN type, segment or anything else this business tracks."
            />

            <InputError message={errors.attributes} />
        </div>
    );
}
