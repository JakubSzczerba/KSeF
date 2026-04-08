export type PaymentStatus = 'unpaid' | 'paid' | 'overdue';

export interface SubmittedInvoice {
  sessionReferenceNumber: string;
  invoiceReferenceNumber: string;
  submittedAt: string;
  paymentStatus: PaymentStatus;
}

export interface PaginatedInvoices {
  ok: boolean;
  items: SubmittedInvoice[];
  total: number;
  page: number;
  pages: number;
  limit: number;
}

export interface DashboardStats {
  ok: boolean;
  sentThisMonth: number;
  unpaidCount: number;
  overdueCount: number;
}

export interface Bootstrap {
  sendEndpoint?: string;
  invoiceRowsEndpoint?: string;
}
