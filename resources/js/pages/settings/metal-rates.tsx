import { Form, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import MetalRateController from '@/actions/App/Http/Controllers/Settings/MetalRateController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MetalRate } from '@/types/invoice';

export default function MetalRatesPage({
    latest,
    history,
}: {
    latest: MetalRate[];
    history: { data: MetalRate[] };
}) {
    return (
        <>
            <Head title="Metal rates" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Daily metal rates"
                    description="Today's rate shows automatically on the dashboard and can be pulled into invoice items"
                />

                <Card>
                    <CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-semibold">Add / update today's rate</h3>
                        <Form
                            {...MetalRateController.store.form()}
                            resetOnSuccess={['rate_per_gram']}
                            className="grid gap-3 sm:grid-cols-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="metal_type">Metal</Label>
                                        <Input id="metal_type" name="metal_type" placeholder="Gold" required />
                                        <InputError message={errors.metal_type} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="purity">Purity</Label>
                                        <Input id="purity" name="purity" placeholder="22K" required />
                                        <InputError message={errors.purity} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="rate_date">Date</Label>
                                        <Input
                                            id="rate_date"
                                            name="rate_date"
                                            type="date"
                                            defaultValue={new Date().toISOString().slice(0, 10)}
                                            required
                                        />
                                        <InputError message={errors.rate_date} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="rate_per_gram">Rate per gram (₹)</Label>
                                        <Input id="rate_per_gram" name="rate_per_gram" type="number" step="0.01" min={0.01} required />
                                        <InputError message={errors.rate_per_gram} />
                                    </div>
                                    <div className="sm:col-span-4">
                                        <Button disabled={processing}>
                                            <Plus className="size-4" />
                                            {processing ? 'Saving…' : 'Save rate'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-semibold">Current rates</h3>
                        {latest.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No rates recorded yet.</p>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {latest.map((r) => (
                                    <div key={r.id} className="flex items-center justify-between rounded-md border p-3 text-sm">
                                        <span>{r.metal_type} {r.purity}</span>
                                        <div className="flex items-center gap-3">
                                            <span className="font-medium">₹{r.rate_per_gram}/g</span>
                                            <span className="text-xs text-muted-foreground">{r.rate_date}</span>
                                            <Form {...MetalRateController.destroy.form(r.id)}>
                                                {({ processing }) => (
                                                    <Button size="sm" variant="ghost" disabled={processing}>
                                                        Remove
                                                    </Button>
                                                )}
                                            </Form>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-semibold">History</h3>
                        <div className="space-y-1">
                            {history.data.map((r) => (
                                <div key={r.id} className="flex justify-between text-sm text-muted-foreground">
                                    <span>{r.rate_date} — {r.metal_type} {r.purity}</span>
                                    <span>₹{r.rate_per_gram}/g</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
