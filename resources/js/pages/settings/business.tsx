import { Form, Head } from '@inertiajs/react';
import BusinessController from '@/actions/App/Http/Controllers/Settings/BusinessController';
import Heading from '@/components/heading';
import ImageUploadField from '@/components/settings/image-upload-field';
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
import { Textarea } from '@/components/ui/textarea';
import type { IndustryOption } from '@/lib/industries';

type BusinessSettings = {
    business_name: string;
    industry: string;
    address: string | null;
    pincode: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    tax_number: string | null;
    invoice_prefix: string;
    invoice_number_start: number;
    quotation_prefix: string;
    challan_prefix: string;
    sms_driver: string;
    sms_country_code: string;
    sms_twilio_from: string | null;
    sms_http_url: string | null;
    sms_http_to_field: string;
    sms_http_message_field: string;
    sms_payment_reminders: boolean;
    email_payment_reminders: boolean;
    sms_birthday_wishes: boolean;
    sms_anniversary_wishes: boolean;
    quotation_customer_decisions: boolean;
    quotation_show_updates: boolean;
    show_all_catalog_fields: boolean;
    receipt_width: string;
    receipt_accent_color: string;
    receipt_show_logo: boolean;
    receipt_show_signature: boolean;
    receipt_show_stamp: boolean;
    receipt_show_gstin: boolean;
    receipt_footer: string | null;
    default_tax_rate: string;
    default_currency: string;
    invoice_terms: string | null;
    footer_text: string | null;
    logo_url: string | null;
    signature_url: string | null;
    stamp_url: string | null;
    bank_name: string | null;
    account_holder_name: string | null;
    account_number: string | null;
    ifsc_code: string | null;
    upi_id: string | null;
};

export default function BusinessSettingsPage({
    settings,
    industries,
}: {
    settings: BusinessSettings;
    industries: IndustryOption[];
}) {
    return (
        <>
            <Head title="Business settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Business"
                    description="Shown on every invoice and its public share page"
                />

                <Form
                    {...BusinessController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-8"
                >
                    {({ processing, errors }) => (
                        <>
                            <section className="space-y-4">
                                <h3 className="text-sm font-semibold">
                                    Business details
                                </h3>

                                <div className="grid gap-2">
                                    <Label htmlFor="business_name">Business name</Label>
                                    <Input
                                        id="business_name"
                                        name="business_name"
                                        defaultValue={settings.business_name}
                                        required
                                    />
                                    <InputError message={errors.business_name} />
                                </div>

                                <ImageUploadField
                                    id="logo"
                                    name="logo"
                                    label="Logo"
                                    currentUrl={settings.logo_url}
                                    error={errors.logo}
                                    hint="PNG or JPG, up to 2MB"
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="industry">
                                        Industry
                                    </Label>
                                    <Select name="industry" defaultValue={settings.industry}>
                                        <SelectTrigger id="industry" className="w-full">
                                            <SelectValue placeholder="Select your trade" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {industries.map((option) => (
                                                <SelectItem
                                                    key={option.key}
                                                    value={option.key}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Controls which modules and item fields
                                        appear — metal rates and hallmarking for
                                        jewelry, per sq ft pricing for tiles,
                                        and so on.
                                    </p>
                                    <InputError message={errors.industry} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address">Address</Label>
                                    <Textarea
                                        id="address"
                                        name="address"
                                        defaultValue={settings.address ?? ''}
                                        rows={2}
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">Phone</Label>
                                        <Input
                                            id="phone"
                                            name="phone"
                                            type="tel"
                                            defaultValue={settings.phone ?? ''}
                                        />
                                        <InputError message={errors.phone} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="business_email">Email</Label>
                                        <Input
                                            id="business_email"
                                            name="email"
                                            type="email"
                                            defaultValue={settings.email ?? ''}
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="website">Website</Label>
                                        <Input
                                            id="website"
                                            name="website"
                                            type="url"
                                            placeholder="https://"
                                            defaultValue={settings.website ?? ''}
                                        />
                                        <InputError message={errors.website} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax_number">
                                            Tax / GST number
                                        </Label>
                                        <Input
                                            id="tax_number"
                                            name="tax_number"
                                            defaultValue={settings.tax_number ?? ''}
                                        />
                                        <InputError message={errors.tax_number} />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="pincode">Pincode</Label>
                                        <Input
                                            id="pincode"
                                            name="pincode"
                                            inputMode="numeric"
                                            maxLength={6}
                                            placeholder="380001"
                                            defaultValue={settings.pincode ?? ''}
                                        />
                                        <InputError message={errors.pincode} />
                                        <p className="text-xs text-muted-foreground">
                                            Required for e-invoice (IRN) generation.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <section className="space-y-4 border-t pt-6">
                                <h3 className="text-sm font-semibold">
                                    Receipts
                                </h3>
                                <p className="-mt-2 text-xs text-muted-foreground">
                                    Printed on the 58 mm and 80 mm thermal slips
                                    your customers keep.
                                </p>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="receipt_width">
                                            Paper width
                                        </Label>
                                        <Select
                                            name="receipt_width"
                                            defaultValue={
                                                settings.receipt_width ?? '80'
                                            }
                                        >
                                            <SelectTrigger id="receipt_width">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="58">
                                                    58 mm
                                                </SelectItem>
                                                <SelectItem value="80">
                                                    80 mm
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.receipt_width}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="receipt_accent_color">
                                            Accent colour
                                        </Label>
                                        <Input
                                            id="receipt_accent_color"
                                            name="receipt_accent_color"
                                            type="color"
                                            className="h-10 p-1"
                                            defaultValue={
                                                settings.receipt_accent_color ??
                                                '#0F172A'
                                            }
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Used for the header rule and totals.
                                        </p>
                                        <InputError
                                            message={
                                                errors.receipt_accent_color
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="receipt_footer">
                                        Receipt footer
                                    </Label>
                                    <Input
                                        id="receipt_footer"
                                        name="receipt_footer"
                                        placeholder="Leave blank to use your invoice footer"
                                        defaultValue={
                                            settings.receipt_footer ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.receipt_footer}
                                    />
                                </div>

                                <div className="space-y-2 rounded-md border p-4">
                                    {(
                                        [
                                            {
                                                name: 'receipt_show_logo',
                                                label: 'Print your logo',
                                                hint: 'Uses the logo from the Business details section above.',
                                            },
                                            {
                                                name: 'receipt_show_gstin',
                                                label: 'Print GSTIN',
                                                hint: 'Include your tax number in the receipt header.',
                                            },
                                            {
                                                name: 'receipt_show_stamp',
                                                label: 'Print stamp',
                                                hint: 'Uses the stamp uploaded above.',
                                            },
                                            {
                                                name: 'receipt_show_signature',
                                                label: 'Print signature',
                                                hint: 'Uses the signature uploaded above.',
                                            },
                                        ] as const
                                    ).map((option) => (
                                        <label
                                            key={option.name}
                                            className="flex cursor-pointer items-start gap-3"
                                        >
                                            <input
                                                type="checkbox"
                                                name={option.name}
                                                defaultChecked={
                                                    settings[
                                                        option.name
                                                    ] ?? false
                                                }
                                                className="mt-1 size-4"
                                            />
                                            <span>
                                                <span className="text-sm">
                                                    {option.label}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {option.hint}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </section>

                            <section className="space-y-4 border-t pt-6">
                                <h3 className="text-sm font-semibold">
                                    Bank details
                                </h3>
                                <p className="-mt-2 text-xs text-muted-foreground">
                                    Printed on invoices so customers know where
                                    to pay.
                                </p>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="bank_name">Bank name</Label>
                                        <Input
                                            id="bank_name"
                                            name="bank_name"
                                            defaultValue={settings.bank_name ?? ''}
                                        />
                                        <InputError message={errors.bank_name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="account_holder_name">
                                            Account holder
                                        </Label>
                                        <Input
                                            id="account_holder_name"
                                            name="account_holder_name"
                                            defaultValue={
                                                settings.account_holder_name ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.account_holder_name}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="account_number">
                                            Account number
                                        </Label>
                                        <Input
                                            id="account_number"
                                            name="account_number"
                                            defaultValue={
                                                settings.account_number ?? ''
                                            }
                                        />
                                        <InputError message={errors.account_number} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="ifsc_code">IFSC code</Label>
                                        <Input
                                            id="ifsc_code"
                                            name="ifsc_code"
                                            defaultValue={settings.ifsc_code ?? ''}
                                        />
                                        <InputError message={errors.ifsc_code} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="upi_id">UPI ID</Label>
                                        <Input
                                            id="upi_id"
                                            name="upi_id"
                                            defaultValue={settings.upi_id ?? ''}
                                        />
                                        <InputError message={errors.upi_id} />
                                    </div>
                                </div>
                            </section>

                            <section className="space-y-4 border-t pt-6">
                                <h3 className="text-sm font-semibold">
                                    Invoicing
                                </h3>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="invoice_prefix">
                                            Invoice prefix
                                        </Label>
                                        <Input
                                            id="invoice_prefix"
                                            name="invoice_prefix"
                                            defaultValue={settings.invoice_prefix}
                                            required
                                        />
                                        <InputError message={errors.invoice_prefix} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invoice_number_start">
                                            Starts at
                                        </Label>
                                        <Input
                                            id="invoice_number_start"
                                            name="invoice_number_start"
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            defaultValue={
                                                settings.invoice_number_start
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.invoice_number_start}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="quotation_prefix">
                                            Quotation prefix
                                        </Label>
                                        <Input
                                            id="quotation_prefix"
                                            name="quotation_prefix"
                                            defaultValue={settings.quotation_prefix}
                                            required
                                        />
                                        <InputError message={errors.quotation_prefix} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="challan_prefix">
                                            Challan prefix
                                        </Label>
                                        <Input
                                            id="challan_prefix"
                                            name="challan_prefix"
                                            defaultValue={settings.challan_prefix}
                                            required
                                        />
                                        <InputError message={errors.challan_prefix} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="default_tax_rate">
                                            Default tax rate (%)
                                        </Label>
                                        <Input
                                            id="default_tax_rate"
                                            name="default_tax_rate"
                                            type="number"
                                            inputMode="decimal"
                                            step="0.01"
                                            min={0}
                                            max={100}
                                            defaultValue={settings.default_tax_rate}
                                            required
                                        />
                                        <InputError
                                            message={errors.default_tax_rate}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2 sm:w-40">
                                    <Label htmlFor="default_currency">
                                        Currency code
                                    </Label>
                                    <Input
                                        id="default_currency"
                                        name="default_currency"
                                        maxLength={3}
                                        className="uppercase"
                                        defaultValue={settings.default_currency}
                                        required
                                    />
                                    <InputError message={errors.default_currency} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="invoice_terms">
                                        Invoice terms (optional)
                                    </Label>
                                    <Textarea
                                        id="invoice_terms"
                                        name="invoice_terms"
                                        rows={3}
                                        defaultValue={settings.invoice_terms ?? ''}
                                    />
                                    <InputError message={errors.invoice_terms} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="footer_text">
                                        Footer text (optional)
                                    </Label>
                                    <Textarea
                                        id="footer_text"
                                        name="footer_text"
                                        rows={2}
                                        defaultValue={settings.footer_text ?? ''}
                                    />
                                    <InputError message={errors.footer_text} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <ImageUploadField
                                        id="signature"
                                        name="signature"
                                        label="Signature (optional)"
                                        currentUrl={settings.signature_url}
                                        error={errors.signature}
                                    />
                                    <ImageUploadField
                                        id="stamp"
                                        name="stamp"
                                        label="Stamp / seal (optional)"
                                        currentUrl={settings.stamp_url}
                                        error={errors.stamp}
                                    />
                                </div>
                            </section>

                            <section className="space-y-4 border-t pt-6">
                                <h3 className="text-sm font-semibold">
                                    SMS gateway
                                </h3>
                                <p className="-mt-2 text-xs text-muted-foreground">
                                    Used for payment reminders, share-link SMS and occasion wishes.
                                    Secrets are write-only — leave blank to keep the stored value.
                                </p>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="sms_driver">Driver</Label>
                                        <Select name="sms_driver" defaultValue={settings.sms_driver || 'log'}>
                                            <SelectTrigger id="sms_driver" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="log">Log only</SelectItem>
                                                <SelectItem value="twilio">Twilio</SelectItem>
                                                <SelectItem value="http">Custom HTTP</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.sms_driver} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="sms_country_code">Country code</Label>
                                        <Input
                                            id="sms_country_code"
                                            name="sms_country_code"
                                            defaultValue={settings.sms_country_code || '91'}
                                            required
                                        />
                                        <InputError message={errors.sms_country_code} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="sms_twilio_from">Twilio from number</Label>
                                        <Input
                                            id="sms_twilio_from"
                                            name="sms_twilio_from"
                                            defaultValue={settings.sms_twilio_from ?? ''}
                                            placeholder="+15551234567"
                                        />
                                        <InputError message={errors.sms_twilio_from} />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="sms_twilio_sid">Twilio SID</Label>
                                        <Input
                                            id="sms_twilio_sid"
                                            name="sms_twilio_sid"
                                            type="password"
                                            autoComplete="off"
                                            placeholder="Unchanged"
                                        />
                                        <InputError message={errors.sms_twilio_sid} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="sms_twilio_token">Twilio auth token</Label>
                                        <Input
                                            id="sms_twilio_token"
                                            name="sms_twilio_token"
                                            type="password"
                                            autoComplete="off"
                                            placeholder="Unchanged"
                                        />
                                        <InputError message={errors.sms_twilio_token} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="sms_http_url">Custom gateway URL (POST JSON)</Label>
                                    <Input
                                        id="sms_http_url"
                                        name="sms_http_url"
                                        defaultValue={settings.sms_http_url ?? ''}
                                        placeholder="https://gateway.example.com/send"
                                    />
                                    <InputError message={errors.sms_http_url} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="sms_http_token">Gateway token</Label>
                                        <Input
                                            id="sms_http_token"
                                            name="sms_http_token"
                                            type="password"
                                            autoComplete="off"
                                            placeholder="Unchanged"
                                        />
                                        <InputError message={errors.sms_http_token} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="sms_http_to_field">To field</Label>
                                        <Input
                                            id="sms_http_to_field"
                                            name="sms_http_to_field"
                                            defaultValue={settings.sms_http_to_field}
                                        />
                                        <InputError message={errors.sms_http_to_field} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="sms_http_message_field">Message field</Label>
                                        <Input
                                            id="sms_http_message_field"
                                            name="sms_http_message_field"
                                            defaultValue={settings.sms_http_message_field}
                                        />
                                        <InputError message={errors.sms_http_message_field} />
                                    </div>
                                </div>

                                <div className="space-y-3 rounded-md border p-4">
                                    <p className="text-sm font-medium">
                                        Automatic messages
                                    </p>
                                    <p className="-mt-1 text-xs text-muted-foreground">
                                        Run daily by the <code>reminders:send</code>{' '}
                                        scheduler. Messages are always recorded in
                                        message history.
                                    </p>

                                    {(
                                        [
                                            {
                                                name: 'sms_payment_reminders',
                                                label: 'Payment due reminders (SMS)',
                                                hint: 'Text customers whose invoices are past due.',
                                            },
                                            {
                                                name: 'email_payment_reminders',
                                                label: 'Payment due reminders (email)',
                                                hint: 'Email the same nudge to customers who have an address on file.',
                                            },
                                            {
                                                name: 'sms_birthday_wishes',
                                                label: 'Birthday wishes',
                                                hint: 'Sent on the day of their birthday.',
                                            },
                                            {
                                                name: 'sms_anniversary_wishes',
                                                label: 'Anniversary wishes',
                                                hint: 'Sent on the customer’s anniversary.',
                                            },
                                        ] as const
                                    ).map((option) => (
                                        <label
                                            key={option.name}
                                            className="flex cursor-pointer items-start gap-3"
                                        >
                                            <input
                                                type="checkbox"
                                                name={option.name}
                                                value="1"
                                                defaultChecked={
                                                    settings[option.name]
                                                }
                                                className="mt-1 size-4"
                                            />
                                            <span>
                                                <span className="block text-sm">
                                                    {option.label}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {option.hint}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            <div className="space-y-3 rounded-md border p-4">
                                    <p className="text-sm font-medium">
                                        Quotations
                                    </p>
                                    <p className="-mt-1 text-xs text-muted-foreground">
                                        What customers can do on a shared
                                        quotation link.
                                    </p>

                                    {(
                                        [
                                            {
                                                name: 'quotation_customer_decisions',
                                                label: 'Let customers accept or decline',
                                                hint: 'Shows Accept / Decline buttons on the shared quotation.',
                                            },
                                            {
                                                name: 'quotation_show_updates',
                                                label: 'Show an updates feed',
                                                hint: 'Customers see every change made to a shared quotation.',
                                            },
                                            {
                                                name: 'show_all_catalog_fields',
                                                label: 'Show every catalog field',
                                                hint: 'Reveal all product fields regardless of your industry.',
                                            },
                                        ] as const
                                    ).map((option) => (
                                        <label
                                            key={option.name}
                                            className="flex cursor-pointer items-start gap-3"
                                        >
                                            <input
                                                type="checkbox"
                                                name={option.name}
                                                value="1"
                                                defaultChecked={
                                                    settings[option.name]
                                                }
                                                className="mt-1 size-4"
                                            />
                                            <span>
                                                <span className="block text-sm">
                                                    {option.label}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {option.hint}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </section>

                            <div className="flex justify-end border-t pt-6">
                                <Button disabled={processing}>
                                    {processing ? 'Saving…' : 'Save settings'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
