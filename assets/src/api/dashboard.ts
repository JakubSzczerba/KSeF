import type { DashboardStats } from '../types/invoice';

export async function fetchDashboardStats(): Promise<DashboardStats> {
  const res = await fetch('/api/dashboard/stats');
  return res.json() as Promise<DashboardStats>;
}
