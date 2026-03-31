export type Theme = 'light' | 'dark';
export type SectionId = 'start' | 'invoices' | 'contractors' | 'reports' | 'settings';

export interface NavItem {
  id: SectionId;
  label: string;
  description: string;
  tag: 'LIVE' | 'WIP';
}

export interface FormState {
  xmlText: string;
  file: File | null;
  systemCode: string;
  schemaVersion: string;
  formValue: string;
  offlineMode: boolean;
}
