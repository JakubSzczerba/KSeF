import type { Contractor, ContractorsListResponse } from '../types/contractor';

export async function listContractors(page: number, limit: number, search?: string): Promise<ContractorsListResponse> {
  const params = new URLSearchParams({ page: String(page), limit: String(limit) });
  if (search) params.set('search', search);
  const res = await fetch(`/api/contractors?${params}`);
  return res.json();
}

export async function createContractor(data: { name: string; nip: string; address?: string; email?: string }): Promise<{ ok: boolean; contractor?: Contractor; message?: string }> {
  const res = await fetch('/api/contractors', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

export async function updateContractor(id: number, data: { name: string; address?: string; email?: string }): Promise<{ ok: boolean; message?: string }> {
  const res = await fetch(`/api/contractors/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

export async function deleteContractor(id: number): Promise<{ ok: boolean; message?: string }> {
  const res = await fetch(`/api/contractors/${id}`, { method: 'DELETE' });
  return res.json();
}

export async function findContractorByNip(nip: string): Promise<{ ok: boolean; contractor?: Contractor }> {
  const res = await fetch(`/api/contractors/search?nip=${encodeURIComponent(nip)}`);
  return res.json();
}
