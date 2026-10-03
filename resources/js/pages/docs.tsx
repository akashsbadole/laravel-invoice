import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    CheckCircle2,
    Clock,
    FileText,
    HelpCircle,
    Layers,
    MessageSquare,
    Package,
    Receipt,
    Share2,
    Shield,
    Sparkles,
    UserCheck,
    Video,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function DocsPage() {
    return (
        <>
            <Head title="User Guide & Documentation — Invoice CRM" />

            <div className="min-h-screen bg-slate-50/80 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
                {/* Header */}
                <header className="border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 sticky top-0 z-10 shadow-sm">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <Link href="/" className="flex items-center gap-2.5">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-indigo-600 text-white font-bold">
                                <BookOpen className="size-5" />
                            </span>
                            <span className="font-bold text-lg tracking-tight">
                                Invoice CRM Docs
                            </span>
                        </Link>

                        <div className="flex items-center gap-3">
                            <Button asChild variant="outline" size="sm">
                                <Link href="/features">All Features</Link>
                            </Button>
                            <Button asChild variant="outline" size="sm">
                                <Link href="/contact">Developer Consultation</Link>
                            </Button>
                            <Button asChild size="sm" className="bg-indigo-600 hover:bg-indigo-700 text-white">
                                <Link href="/dashboard">Go to Dashboard</Link>
                            </Button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6 py-10 space-y-12">
                    {/* Hero Intro */}
                    <section className="text-center max-w-3xl mx-auto space-y-4">
                        <Badge className="bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 border-indigo-200 text-xs px-3 py-1 font-semibold">
                            Step-by-Step User Guide
                        </Badge>
                        <h1 className="text-3xl sm:text-5xl font-extrabold tracking-tight">
                            Master Quotations, Invoicing &amp; CRM in Minutes
                        </h1>
                        <p className="text-slate-600 dark:text-slate-400 text-base">
                            Learn how to build quotations, share trackable links via WhatsApp, convert accepted quotes into sales invoices with 1 click, and configure GST tax rules for your trade.
                        </p>
                    </section>

                    {/* Workflow Diagram */}
                    <Card className="border-indigo-200 dark:border-indigo-900 bg-gradient-to-r from-indigo-50/50 via-white to-purple-50/50 dark:from-indigo-950/20 dark:to-purple-950/20 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-lg font-bold flex items-center gap-2">
                                <Layers className="size-5 text-indigo-600" />
                                The Complete Deal Conversion Cycle
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 sm:grid-cols-4 text-center">
                                <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                                    <span className="size-8 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-bold flex items-center justify-center mx-auto mb-2 text-sm">1</span>
                                    <h4 className="font-bold text-sm">Build Quotation</h4>
                                    <p className="text-xs text-slate-500 mt-1">Pick products from catalog with weight, purity, or area pricing.</p>
                                </div>

                                <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                                    <span className="size-8 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-bold flex items-center justify-center mx-auto mb-2 text-sm">2</span>
                                    <h4 className="font-bold text-sm">Share Secure Link</h4>
                                    <p className="text-xs text-slate-500 mt-1">Send via WhatsApp/SMS. Track views, PDF downloads, &amp; decisions.</p>
                                </div>

                                <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 shadow-xs">
                                    <span className="size-8 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center mx-auto mb-2 text-sm">3</span>
                                    <h4 className="font-bold text-emerald-900 dark:text-emerald-300 text-sm">Customer Accepts</h4>
                                    <p className="text-xs text-emerald-700 dark:text-emerald-400 mt-1">Customer confirms or pays UPI deposit on public link.</p>
                                </div>

                                <div className="p-4 rounded-xl bg-indigo-600 text-white shadow-xs">
                                    <span className="size-8 rounded-full bg-white/20 text-white font-bold flex items-center justify-center mx-auto mb-2 text-sm">4</span>
                                    <h4 className="font-bold text-sm">1-Click Conversion</h4>
                                    <p className="text-xs text-indigo-100 mt-1">Convert to Sales Invoice with auto-applied deposit &amp; due date.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Step-by-Step Sections */}
                    <div className="grid gap-8 md:grid-cols-2">
                        {/* Guide 1: Quotations */}
                        <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base font-bold">
                                    <FileText className="size-5 text-indigo-600" />
                                    1. Creating &amp; Sharing Quotations
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                                <p>
                                    To create a quotation, navigate to <strong>Quotations → New Quotation</strong> or select products directly in the <strong>Product Catalog</strong>.
                                </p>
                                <ul className="space-y-2 text-xs border-l-2 border-indigo-500 pl-3">
                                    <li><strong>WhatsApp Share:</strong> Click <em>Share on WhatsApp</em> to send a secure link directly to your customer's mobile number.</li>
                                    <li><strong>Real-time Tracking:</strong> Your dashboard logs when the client <em>views</em> or <em>downloads the PDF</em>.</li>
                                    <li><strong>Customer Decision:</strong> Clients can click <strong>Accept Quotation</strong> or leave revision notes right on their phone screen.</li>
                                </ul>
                            </CardContent>
                        </Card>

                        {/* Guide 2: Conversion */}
                        <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base font-bold">
                                    <UserCheck className="size-5 text-emerald-600" />
                                    2. 1-Click Quotation to Invoice Conversion
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                                <p>
                                    When a customer accepts a quotation, an alert appears on your <strong>Dashboard</strong> and <strong>Quotations Pipeline</strong> board.
                                </p>
                                <ul className="space-y-2 text-xs border-l-2 border-emerald-500 pl-3">
                                    <li><strong>Pipeline Board:</strong> Click the green <em>Convert to Invoice</em> button on any accepted quote card.</li>
                                    <li><strong>Target Document Type:</strong> Select Sales Invoice, Jewelry Invoice, or Delivery Challan.</li>
                                    <li><strong>Auto-Apply Advances:</strong> Check <em>Auto-apply advances</em> to automatically settle booking deposits.</li>
                                </ul>
                            </CardContent>
                        </Card>

                        {/* Guide 3: GST & Taxes */}
                        <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base font-bold">
                                    <Receipt className="size-5 text-purple-600" />
                                    3. GST &amp; Tax Calculation Rules
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                                <p>
                                    Taxes are calculated per item and grouped into slabs automatically according to Indian GST standards.
                                </p>
                                <ul className="space-y-2 text-xs border-l-2 border-purple-500 pl-3">
                                    <li><strong>Intrastate (Same State):</strong> Splits slab tax equally into CGST + SGST rows.</li>
                                    <li><strong>Interstate (Other State):</strong> Charges full slab tax under IGST.</li>
                                    <li><strong>GSTR Export:</strong> Export official GSTR-1 and GSTR-3B JSON files under <em>Reports</em>.</li>
                                </ul>
                            </CardContent>
                        </Card>

                        {/* Guide 4: Developer Consultation */}
                        <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base font-bold">
                                    <Video className="size-5 text-amber-600" />
                                    4. Paid Developer Customization &amp; Video Support
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                                <p>
                                    All core software features are <strong>100% Free Forever</strong>. For specialized needs, connect with our developer team:
                                </p>
                                <ul className="space-y-2 text-xs border-l-2 border-amber-500 pl-3">
                                    <li><strong>Custom Templates:</strong> Custom branded PDF layouts &amp; 58/80mm thermal receipt designs.</li>
                                    <li><strong>1-on-1 Onboarding:</strong> Dedicated video call setup and staff training session.</li>
                                    <li><strong>Custom Integrations:</strong> API connectors for custom ERPs and SMS/WhatsApp gateways.</li>
                                </ul>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Developer Support CTA */}
                    <section className="rounded-2xl bg-indigo-900 text-white p-8 text-center space-y-4">
                        <Badge className="bg-indigo-700 text-indigo-100 border-indigo-600">
                            Developer Support &amp; Onboarding
                        </Badge>
                        <h2 className="text-2xl font-bold">Need Custom Design or Dedicated Setup Help?</h2>
                        <p className="text-indigo-200 text-sm max-w-lg mx-auto">
                            Book a 1-on-1 video call or send us your print design specifications. We are here to customize your workflow.
                        </p>
                        <Button asChild size="lg" className="bg-white text-indigo-900 hover:bg-slate-100 font-bold">
                            <Link href="/contact">Book Developer Call / Contact Support</Link>
                        </Button>
                    </section>
                </main>

                <footer className="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-6 text-center text-xs text-slate-500">
                    © {new Date().getFullYear()} Invoice CRM. Multi-Tenancy Invoicing &amp; Quotations Platform.
                </footer>
            </div>
        </>
    );
}
