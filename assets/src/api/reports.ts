export interface MonthData {
  month: number;
  count: number;
  revenue: number;
}

export interface RevenueByMonthResponse {
  ok: boolean;
  year: number;
  data: MonthData[];
}

export async function fetchRevenueByMonth(year: number, status?: string): Promise<RevenueByMonthResponse> {
  const params = new URLSearchParams({ year: String(year) });
  if (status) params.set('status', status);
  const res = await fetch(`/api/reports/revenue-by-month?${params}`);
  return res.json();
}

export function csvDownloadUrl(status?: string): string {
  const params = new URLSearchParams();
  if (status) params.set('status', status);
  const qs = params.toString();
  return `/api/reports/invoices.csv${qs ? '?' + qs : ''}`;
}
