import type { CompanySettings } from '../types/settings';

export async function getCompanySettings(): Promise<{ ok: boolean; settings: CompanySettings | null }> {
  const res = await fetch('/api/settings/company');
  return res.json();
}

export async function updateCompanySettings(data: {
  name: string;
  nip: string;
  address?: string;
  bankAccount?: string;
  vatNumber?: string;
}): Promise<{ ok: boolean; settings?: CompanySettings; message?: string }> {
  const res = await fetch('/api/settings/company', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}
