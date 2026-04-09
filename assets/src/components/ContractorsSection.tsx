import React, { useCallback, useEffect, useRef, useState } from 'react';
import type { Contractor } from '../types/contractor';
import { createContractor, deleteContractor, listContractors, updateContractor } from '../api/contractors';

const LIMIT = 25;

interface FormState {
  name: string;
  nip: string;
  address: string;
  email: string;
}

const emptyForm = (): FormState => ({ name: '', nip: '', address: '', email: '' });

export default function ContractorsSection() {
  const [items, setItems] = useState<Contractor[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Contractor | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm());
  const [formError, setFormError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const searchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

  const load = useCallback(async (p: number, s: string) => {
    setLoading(true);
    setError(null);
    try {
      const data = await listContractors(p, LIMIT, s || undefined);
      if (!data.ok) throw new Error('Błąd pobierania kontrahentów');
      setItems(data.items);
      setTotal(data.total);
      setPage(data.page);
      setPages(data.pages);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Nieznany błąd');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(1, search); }, [search, load]);

  const handleSearchChange = (val: string) => {
    setSearchInput(val);
    if (searchTimeout.current) clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => setSearch(val), 400);
  };

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm());
    setFormError(null);
    setModalOpen(true);
  };

  const openEdit = (c: Contractor) => {
    setEditing(c);
    setForm({ name: c.name, nip: c.nip, address: c.address ?? '', email: c.email ?? '' });
    setFormError(null);
    setModalOpen(true);
  };

  const closeModal = () => { setModalOpen(false); setEditing(null); };

  const handleSave = async () => {
    setSaving(true);
    setFormError(null);
    try {
      if (editing) {
        const res = await updateContractor(editing.id, {
          name: form.name,
          address: form.address || undefined,
          email: form.email || undefined,
        });
        if (!res.ok) { setFormError(res.message ?? 'Błąd zapisu'); return; }
      } else {
        const res = await createContractor({
          name: form.name,
          nip: form.nip,
          address: form.address || undefined,
          email: form.email || undefined,
        });
        if (!res.ok) { setFormError(res.message ?? 'Błąd zapisu'); return; }
      }
      closeModal();
      load(page, search);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (c: Contractor) => {
    if (!confirm(`Usunąć kontrahenta "${c.name}" (NIP: ${c.nip})?`)) return;
    const res = await deleteContractor(c.id);
    if (!res.ok) { alert(res.message ?? 'Błąd usuwania'); return; }
    load(page, search);
  };

  return (
    <div className="contractors-section">
      <div className="section-header">
        <h2>Kontrahenci</h2>
        <button className="btn-primary" onClick={openCreate}>+ Dodaj kontrahenta</button>
      </div>

      <div className="search-bar">
        <input
          type="text"
          placeholder="Szukaj po nazwie lub NIP..."
          value={searchInput}
          onChange={e => handleSearchChange(e.target.value)}
        />
      </div>

      {error && <div className="error-banner">{error}</div>}

      <div className="table-wrapper">
        <table className="data-table">
          <thead>
            <tr>
              <th>Nazwa</th>
              <th>NIP</th>
              <th>Adres</th>
              <th>Email</th>
              <th>Dodano</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr><td colSpan={6} className="table-empty">Ładowanie...</td></tr>
            )}
            {!loading && items.length === 0 && (
              <tr><td colSpan={6} className="table-empty">Brak kontrahentów</td></tr>
            )}
            {!loading && items.map(c => (
              <tr key={c.id}>
                <td>{c.name}</td>
                <td className="nip-cell">{c.nip}</td>
                <td>{c.address ?? '—'}</td>
                <td>{c.email ?? '—'}</td>
                <td>{c.createdAt ? new Date(c.createdAt).toLocaleDateString('pl-PL') : '—'}</td>
                <td className="actions-cell">
                  <button className="btn-icon" onClick={() => openEdit(c)} title="Edytuj">✏️</button>
                  <button className="btn-icon btn-danger" onClick={() => handleDelete(c)} title="Usuń">🗑️</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {pages > 1 && (
        <div className="pagination">
          <button disabled={page <= 1} onClick={() => load(page - 1, search)}>‹ Poprzednia</button>
          <span>{page} / {pages} (łącznie: {total})</span>
          <button disabled={page >= pages} onClick={() => load(page + 1, search)}>Następna ›</button>
        </div>
      )}

      {modalOpen && (
        <div className="modal-overlay" onClick={closeModal}>
          <div className="modal" onClick={e => e.stopPropagation()}>
            <h3>{editing ? 'Edytuj kontrahenta' : 'Nowy kontrahent'}</h3>

            {formError && <div className="form-error">{formError}</div>}

            <label>
              Nazwa *
              <input
                type="text"
                value={form.name}
                onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                placeholder="Nazwa firmy"
              />
            </label>

            {!editing && (
              <label>
                NIP * (10 cyfr)
                <input
                  type="text"
                  value={form.nip}
                  onChange={e => setForm(f => ({ ...f, nip: e.target.value }))}
                  placeholder="1234567890"
                  maxLength={10}
                />
              </label>
            )}

            <label>
              Adres
              <input
                type="text"
                value={form.address}
                onChange={e => setForm(f => ({ ...f, address: e.target.value }))}
                placeholder="ul. Przykładowa 1, 00-001 Warszawa"
              />
            </label>

            <label>
              Email
              <input
                type="email"
                value={form.email}
                onChange={e => setForm(f => ({ ...f, email: e.target.value }))}
                placeholder="kontakt@firma.pl"
              />
            </label>

            <div className="modal-actions">
              <button onClick={closeModal} disabled={saving}>Anuluj</button>
              <button className="btn-primary" onClick={handleSave} disabled={saving}>
                {saving ? 'Zapisywanie...' : 'Zapisz'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
