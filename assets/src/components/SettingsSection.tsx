import React, { useEffect, useState } from 'react';
import { getCompanySettings, updateCompanySettings } from '../api/settings';
import type { CompanySettings } from '../types/settings';

interface FormState {
  name: string;
  nip: string;
  address: string;
  bankAccount: string;
  vatNumber: string;
}

const toForm = (s: CompanySettings | null): FormState => ({
  name: s?.name ?? '',
  nip: s?.nip ?? '',
  address: s?.address ?? '',
  bankAccount: s?.bankAccount ?? '',
  vatNumber: s?.vatNumber ?? '',
});

export default function SettingsSection() {
  const [form, setForm] = useState<FormState>(toForm(null));
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    getCompanySettings().then(res => {
      if (res.ok && res.settings) setForm(toForm(res.settings));
    }).finally(() => setLoading(false));
  }, []);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(false);
    try {
      const res = await updateCompanySettings({
        name: form.name,
        nip: form.nip,
        address: form.address || undefined,
        bankAccount: form.bankAccount || undefined,
        vatNumber: form.vatNumber || undefined,
      });
      if (!res.ok) {
        setError(res.message ?? 'Błąd zapisu');
        return;
      }
      setSuccess(true);
      if (res.settings) setForm(toForm(res.settings));
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div style={{ padding: '2rem' }}>Ładowanie...</div>;

  return (
    <div className="settings-section">
      <div className="section-header">
        <h2>Ustawienia firmy</h2>
      </div>

      <form className="settings-form" onSubmit={handleSave}>
        {error && <div className="form-error">{error}</div>}
        {success && <div className="form-success">Ustawienia zapisane.</div>}

        <div className="form-group">
          <label>
            Nazwa firmy *
            <input
              type="text"
              value={form.name}
              onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
              placeholder="Moja Firma Sp. z o.o."
              required
            />
          </label>
        </div>

        <div className="form-group">
          <label>
            NIP * (10 cyfr)
            <input
              type="text"
              value={form.nip}
              onChange={e => setForm(f => ({ ...f, nip: e.target.value }))}
              placeholder="1234567890"
              maxLength={10}
              required
            />
          </label>
        </div>

        <div className="form-group">
          <label>
            Adres
            <input
              type="text"
              value={form.address}
              onChange={e => setForm(f => ({ ...f, address: e.target.value }))}
              placeholder="ul. Przykładowa 1, 00-001 Warszawa"
            />
          </label>
        </div>

        <div className="form-group">
          <label>
            Numer rachunku bankowego (IBAN)
            <input
              type="text"
              value={form.bankAccount}
              onChange={e => setForm(f => ({ ...f, bankAccount: e.target.value }))}
              placeholder="PL61109010140000071219812874"
              maxLength={34}
            />
          </label>
        </div>

        <div className="form-group">
          <label>
            Numer VAT UE
            <input
              type="text"
              value={form.vatNumber}
              onChange={e => setForm(f => ({ ...f, vatNumber: e.target.value }))}
              placeholder="PL1234567890"
              maxLength={50}
            />
          </label>
        </div>

        <div className="form-actions">
          <button type="submit" className="btn-primary" disabled={saving}>
            {saving ? 'Zapisywanie...' : 'Zapisz ustawienia'}
          </button>
        </div>
      </form>
    </div>
  );
}
