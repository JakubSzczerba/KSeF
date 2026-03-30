import type { PaginatedInvoices } from '../types/invoice';

export async function fetchInvoices(
  page: number,
  limit: number,
  status?: string
): Promise<PaginatedInvoices> {
  const params = new URLSearchParams({ page: String(page), limit: String(limit) });
  if (status) params.set('status', status);
  const res = await fetch(`/api/invoices?${params.toString()}`);
  return res.json() as Promise<PaginatedInvoices>;
}
