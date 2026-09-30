import { Form, Head } from '@inertiajs/react';
import BusinessController from '@/actions/App/Http/Controllers/Settings/BusinessController';
import Heading from '@/components/heading';
import ImageUploadField from '@/components/settings/image-upload-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type BusinessSettings = {
    business_name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    tax_number: string | null;
    invoice_prefix: string;
    invoice_number_start: number;
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
}: {
    settings: BusinessSettings;
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
