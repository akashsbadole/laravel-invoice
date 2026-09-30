export type ReportColumn = { key: string; label: string; type: 'text' | 'number' | 'money' | 'date' };

export type ReportData = {
    type: string;
    title: string;
    columns: ReportColumn[];
    rows: Record<string, string | number | null>[];
    totals: Record<string, string | number | null> | null;
};

export type ReportTypeOption = { value: string; label: string };

export type ReportFilters = {
    type: string;
    from: string;
    to: string;
    customer_id: string;
    staff_id: string;
    status: string;
};
