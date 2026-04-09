export interface Contractor {
  id: number;
  name: string;
  nip: string;
  address: string | null;
  email: string | null;
  createdAt: string;
}

export interface ContractorsListResponse {
  ok: boolean;
  items: Contractor[];
  total: number;
  page: number;
  pages: number;
  limit: number;
}
