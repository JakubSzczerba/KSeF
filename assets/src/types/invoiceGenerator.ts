export interface InvoiceLineItemForm {
  description: string;
  quantity: string;
  unitPriceNet: string;
  vatRate: number;
}

export interface GenerateInvoiceRequest {
  invoiceNumber: string;
  invoiceDate: string;
  saleDate: string;
  paymentDueDate: string;
  sellerNip: string;
  sellerName: string;
  sellerAddress: string;
  bankAccount: string;
  buyerNip: string;
  buyerName: string;
  buyerAddress: string;
  lines: Array<{
    description: string;
    quantity: number;
    unitPriceNet: number;
    vatRate: number;
  }>;
  notes?: string;
}
