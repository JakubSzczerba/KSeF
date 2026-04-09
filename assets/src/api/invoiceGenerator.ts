import type { GenerateInvoiceRequest } from '../types/invoiceGenerator';

export async function generateInvoiceXml(data: GenerateInvoiceRequest): Promise<{ ok: boolean; xml?: string; message?: string }> {
  const res = await fetch('/api/invoices/generate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}
