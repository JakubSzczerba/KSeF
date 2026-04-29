import { useEffect, useRef, useState } from 'react';
import { getCompanySettings } from '../api/settings';
import { findContractorByNip } from '../api/contractors';
import { generateInvoiceXml } from '../api/invoiceGenerator';
import type { InvoiceLineItemForm } from '../types/invoiceGenerator';

const today = () => new Date().toISOString().slice(0, 10);
const addDays = (d: string, n: number) => {
  const dt = new Date(d);
  dt.setDate(dt.getDate() + n);
  return dt.toISOString().slice(0, 10);
};
const newLine = (): InvoiceLineItemForm => ({ description: '', quantity: '1', unitPriceNet: '', vatRate: 23 });

const VAT_RATES = [23, 8, 5, 0];

function calcLine(line: InvoiceLineItemForm) {
  const qty = parseFloat(line.quantity) || 0;
  const price = parseFloat(line.unitPriceNet) || 0;
  const net = Math.round(qty * price * 100) / 100;
  const vat = Math.round(net * line.vatRate) / 100;
  return { net, vat, gross: Math.round((net + vat) * 100) / 100 };
}

export default function InvoiceGeneratorSection() {
  const [invoiceNumber, setInvoiceNumber] = useState('');
  const [invoiceDate, setInvoiceDate] = useState(today());
  const [saleDate, setSaleDate] = useState(today());
  const [paymentDueDate, setPaymentDueDate] = useState(addDays(today(), 14));
  const [notes, setNotes] = useState('');

  const [sellerNip, setSellerNip] = useState('');
  const [sellerName, setSellerName] = useState('');
  const [sellerAddress, setSellerAddress] = useState('');
  const [bankAccount, setBankAccount] = useState('');

  const [buyerNip, setBuyerNip] = useState('');
  const [buyerName, setBuyerName] = useState('');
  const [buyerAddress, setBuyerAddress] = useState('');
  const [buyerLookupStatus, setBuyerLookupStatus] = useState<'idle' | 'loading' | 'found' | 'notfound'>('idle');

  const [lines, setLines] = useState<InvoiceLineItemForm[]>([newLine()]);

  const [generatedXml, setGeneratedXml] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [generating, setGenerating] = useState(false);
  const [sendStatus, setSendStatus] = useState<'idle' | 'sending' | 'ok' | 'error'>('idle');
  const [sendMessage, setSendMessage] = useState('');

  const buyerTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    getCompanySettings().then(res => {
      if (res.ok && res.settings) {
        setSellerNip(res.settings.nip);
        setSellerName(res.settings.name);
        setSellerAddress(res.settings.address ?? '');
        setBankAccount(res.settings.bankAccount ?? '');
      }
    });
  }, []);

  const handleBuyerNipChange = (val: string) => {
    setBuyerNip(val);
    if (buyerTimeout.current) clearTimeout(buyerTimeout.current);
    if (val.length === 10) {
      setBuyerLookupStatus('loading');
      buyerTimeout.current = setTimeout(async () => {
        const res = await findContractorByNip(val);
        if (res.ok && res.contractor) {
          setBuyerName(res.contractor.name);
          setBuyerAddress(res.contractor.address ?? '');
          setBuyerLookupStatus('found');
        } else {
          setBuyerLookupStatus('notfound');
        }
      }, 400);
    } else {
      setBuyerLookupStatus('idle');
    }
  };

  const updateLine = (i: number, field: keyof InvoiceLineItemForm, value: string | number) => {
    setLines(prev => prev.map((l, idx) => idx === i ? { ...l, [field]: value } : l));
  };

  const addLine = () => setLines(prev => [...prev, newLine()]);
  const removeLine = (i: number) => setLines(prev => prev.filter((_, idx) => idx !== i));

  const totals = lines.reduce((acc, l) => {
    const c = calcLine(l);
    return { net: acc.net + c.net, vat: acc.vat + c.vat, gross: acc.gross + c.gross };
  }, { net: 0, vat: 0, gross: 0 });

  const handleGenerate = async () => {
    setError(null);
    setGeneratedXml(null);
    setGenerating(true);
    try {
      const res = await generateInvoiceXml({
        invoiceNumber,
        invoiceDate,
        saleDate,
        paymentDueDate,
        sellerNip,
        sellerName,
        sellerAddress,
        bankAccount,
        buyerNip,
        buyerName,
        buyerAddress,
        lines: lines.map(l => ({
          description: l.description,
          quantity: parseFloat(l.quantity) || 1,
          unitPriceNet: parseFloat(l.unitPriceNet) || 0,
          vatRate: l.vatRate,
        })),
        notes: notes || undefined,
      });
      if (!res.ok) { setError(res.message ?? 'Błąd generowania'); return; }
      setGeneratedXml(res.xml ?? null);
      setSendStatus('idle');
    } finally {
      setGenerating(false);
    }
  };

  const handleDownload = () => {
    if (!generatedXml) return;
    const blob = new Blob([generatedXml], { type: 'application/xml' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${invoiceNumber || 'faktura'}.xml`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const handleSendToKsef = async () => {
    if (!generatedXml) return;
    setSendStatus('sending');
    setSendMessage('');
    try {
      const body = new FormData();
      body.append('xml_text', generatedXml);
      const res = await fetch('/send', { method: 'POST', body });
      const json = await res.json();
      if (json.ok) {
        setSendStatus('ok');
        setSendMessage(`Faktura wysłana. Job ID: ${json.jobId}`);
      } else {
        setSendStatus('error');
        setSendMessage(json.message ?? 'Błąd wysyłki');
      }
    } catch {
      setSendStatus('error');
      setSendMessage('Błąd połączenia z serwerem');
    }
  };

  return (
    <div className="generator-section">
      <div className="section-header">
        <h2>Generator faktur FA(3)</h2>
      </div>

      <div className="generator-layout">
        <div className="generator-form">

          {/* Dane faktury */}
          <fieldset>
            <legend>Dane faktury</legend>
            <div className="form-row">
              <label>Numer faktury *<input type="text" value={invoiceNumber} onChange={e => setInvoiceNumber(e.target.value)} placeholder="1/01/2026" /></label>
              <label>Data wystawienia *<input type="date" value={invoiceDate} onChange={e => { setInvoiceDate(e.target.value); setPaymentDueDate(addDays(e.target.value, 14)); }} /></label>
              <label>Data sprzedaży *<input type="date" value={saleDate} onChange={e => setSaleDate(e.target.value)} /></label>
              <label>Termin płatności *<input type="date" value={paymentDueDate} onChange={e => setPaymentDueDate(e.target.value)} /></label>
            </div>
          </fieldset>

          {/* Sprzedawca */}
          <fieldset>
            <legend>Sprzedawca</legend>
            <div className="form-row">
              <label>NIP *<input type="text" value={sellerNip} onChange={e => setSellerNip(e.target.value)} maxLength={10} /></label>
              <label>Nazwa *<input type="text" value={sellerName} onChange={e => setSellerName(e.target.value)} /></label>
            </div>
            <div className="form-row">
              <label className="full-width">Adres *<input type="text" value={sellerAddress} onChange={e => setSellerAddress(e.target.value)} /></label>
              <label className="full-width">Numer rachunku bankowego *<input type="text" value={bankAccount} onChange={e => setBankAccount(e.target.value)} placeholder="PL61109010140000071219812874" /></label>
            </div>
          </fieldset>

          {/* Nabywca */}
          <fieldset>
            <legend>Nabywca</legend>
            <div className="form-row">
              <label>
                NIP * {buyerLookupStatus === 'loading' && <span className="lookup-hint">szukam...</span>}
                     {buyerLookupStatus === 'found' && <span className="lookup-hint ok">znaleziono</span>}
                     {buyerLookupStatus === 'notfound' && <span className="lookup-hint warn">nie ma w bazie</span>}
                <input type="text" value={buyerNip} onChange={e => handleBuyerNipChange(e.target.value)} maxLength={10} placeholder="10 cyfr" />
              </label>
              <label>Nazwa *<input type="text" value={buyerName} onChange={e => setBuyerName(e.target.value)} /></label>
            </div>
            <label className="full-width">Adres<input type="text" value={buyerAddress} onChange={e => setBuyerAddress(e.target.value)} /></label>
          </fieldset>

          {/* Pozycje */}
          <fieldset>
            <legend>Pozycje faktury</legend>
            <table className="lines-table">
              <thead>
                <tr>
                  <th>Opis</th>
                  <th>Ilość</th>
                  <th>Cena netto</th>
                  <th>VAT %</th>
                  <th>Netto</th>
                  <th>VAT</th>
                  <th>Brutto</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {lines.map((line, i) => {
                  const c = calcLine(line);
                  return (
                    <tr key={i}>
                      <td><input type="text" value={line.description} onChange={e => updateLine(i, 'description', e.target.value)} placeholder="Nazwa usługi/towaru" /></td>
                      <td><input type="number" value={line.quantity} onChange={e => updateLine(i, 'quantity', e.target.value)} min="0" step="0.01" style={{ width: '70px' }} /></td>
                      <td><input type="number" value={line.unitPriceNet} onChange={e => updateLine(i, 'unitPriceNet', e.target.value)} min="0" step="0.01" style={{ width: '90px' }} /></td>
                      <td>
                        <select value={line.vatRate} onChange={e => updateLine(i, 'vatRate', parseInt(e.target.value))}>
                          {VAT_RATES.map(r => <option key={r} value={r}>{r}%</option>)}
                        </select>
                      </td>
                      <td className="num-cell">{c.net.toFixed(2)}</td>
                      <td className="num-cell">{c.vat.toFixed(2)}</td>
                      <td className="num-cell">{c.gross.toFixed(2)}</td>
                      <td>{lines.length > 1 && <button className="btn-icon btn-danger" onClick={() => removeLine(i)}>✕</button>}</td>
                    </tr>
                  );
                })}
              </tbody>
              <tfoot>
                <tr>
                  <td colSpan={4}><strong>Razem:</strong></td>
                  <td className="num-cell"><strong>{totals.net.toFixed(2)}</strong></td>
                  <td className="num-cell"><strong>{totals.vat.toFixed(2)}</strong></td>
                  <td className="num-cell"><strong>{totals.gross.toFixed(2)}</strong></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
            <button className="btn-secondary" onClick={addLine}>+ Dodaj pozycję</button>
          </fieldset>

          {/* Uwagi */}
          <fieldset>
            <legend>Uwagi (opcjonalnie)</legend>
            <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={2} placeholder="Dodatkowe informacje na fakturze..." />
          </fieldset>

          {error && <div className="form-error">{error}</div>}

          <div className="form-actions">
            <button className="btn-primary" onClick={handleGenerate} disabled={generating}>
              {generating ? 'Generowanie...' : 'Generuj XML FA(3)'}
            </button>
          </div>
        </div>

        {/* Podgląd XML */}
        {generatedXml && (
          <div className="xml-preview">
            <div className="xml-preview-header">
              <h3>Wygenerowany XML</h3>
              <div className="xml-preview-actions">
                <button className="btn-secondary" onClick={handleDownload}>Pobierz XML</button>
                <button
                  className="btn-primary"
                  onClick={handleSendToKsef}
                  disabled={sendStatus === 'sending'}
                >
                  {sendStatus === 'sending' ? 'Wysyłanie...' : 'Wyślij do KSeF'}
                </button>
              </div>
            </div>
            {sendStatus === 'ok' && <div className="form-success">{sendMessage}</div>}
            {sendStatus === 'error' && <div className="form-error">{sendMessage}</div>}
            <pre className="xml-code">{generatedXml}</pre>
          </div>
        )}
      </div>
    </div>
  );
}
