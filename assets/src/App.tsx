import { useEffect, useState, useCallback } from 'react';
import type { Bootstrap, DashboardStats, PaginatedInvoices, SubmittedInvoice } from './types/invoice';
import type { Theme, SectionId, NavItem, FormState } from './types/app';
import { fetchDashboardStats } from './api/dashboard';
import { fetchInvoices } from './api/invoices';
import { S } from './styles';
import { Sidebar } from './components/Sidebar';
import { SendInvoiceModal } from './components/SendInvoiceModal';

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const NAV_ITEMS: NavItem[] = [
  { id: 'start', label: 'Start', description: 'Pulpit i szybki przeglad aktywnosci', tag: 'LIVE' },
  { id: 'invoices', label: 'Faktury', description: 'Lista wysylek, statusy i pliki', tag: 'LIVE' },
  { id: 'contractors', label: 'Kontrahenci', description: 'Baza kontrahentow i dane do faktur', tag: 'WIP' },
  { id: 'reports', label: 'Raporty', description: 'Podsumowania i wykresy finansowe', tag: 'WIP' },
  { id: 'settings', label: 'Ustawienia', description: 'Konfiguracja firmy i KSeF', tag: 'WIP' },
];

const INVOICE_LIMIT = 25;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const getSystemTheme = (): Theme =>
  window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

// ---------------------------------------------------------------------------
// App
// ---------------------------------------------------------------------------

export default function App({ bootstrap }: { bootstrap: Bootstrap }) {
  const sendEndpoint = bootstrap.sendEndpoint ?? '/send';

  const [theme, setTheme] = useState<Theme>(
    (localStorage.getItem('ksef-ui-theme') as Theme | null) ?? getSystemTheme()
  );
  const [message, setMessage] = useState('');
  const [messageType, setMessageType] = useState<'ok' | 'error'>('ok');
  const [isNarrow, setIsNarrow] = useState(window.innerWidth < 980);
  const [isBusy, setIsBusy] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [activeSection, setActiveSection] = useState<SectionId>('start');

  // Dashboard stats
  const [stats, setStats] = useState<DashboardStats | null>(null);

  // Invoice list state
  const [invoiceData, setInvoiceData] = useState<PaginatedInvoices | null>(null);
  const [invoicePage, setInvoicePage] = useState(1);

  // Start section activity feed (last 5)
  const [activityRows, setActivityRows] = useState<SubmittedInvoice[]>([]);

  // Send form
  const [form, setForm] = useState<FormState>({
    xmlText: '',
    file: null,
    systemCode: 'FA (3)',
    schemaVersion: '1-0E',
    formValue: 'FA',
    offlineMode: false,
  });

  // Theme effect
  useEffect(() => {
    document.body.setAttribute('data-theme', theme);
    localStorage.setItem('ksef-ui-theme', theme);
  }, [theme]);

  // Resize
  useEffect(() => {
    const onResize = () => {
      const narrow = window.innerWidth < 980;
      setIsNarrow(narrow);
      if (!narrow) setIsDrawerOpen(false);
    };
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  // Escape key
  useEffect(() => {
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') { setIsDrawerOpen(false); setIsModalOpen(false); }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, []);

  // Load stats on mount
  useEffect(() => {
    fetchDashboardStats()
      .then(setStats)
      .catch(() => { /* fail silently */ });
  }, []);

  // Load activity (last 5)
  useEffect(() => {
    fetchInvoices(1, 5)
      .then(r => setActivityRows(r.items))
      .catch(() => { /* fail silently */ });
  }, []);

  // Load invoices when section = invoices or page changes
  const loadInvoices = useCallback((page: number) => {
    setIsBusy(true);
    fetchInvoices(page, INVOICE_LIMIT)
      .then(data => { setInvoiceData(data); setInvoicePage(page); })
      .catch(e => setAlert(e instanceof Error ? e.message : 'Blad pobierania faktur.', 'error'))
      .finally(() => setIsBusy(false));
  }, []);

  useEffect(() => {
    if (activeSection === 'invoices') loadInvoices(invoicePage);
  }, [activeSection]); // eslint-disable-line react-hooks/exhaustive-deps

  const setAlert = (text: string, type: 'ok' | 'error' = 'ok') => {
    setMessage(text);
    setMessageType(type);
    setTimeout(() => setMessage(''), 6000);
  };

  const openSection = (id: SectionId) => { setActiveSection(id); setIsDrawerOpen(false); };

  // Send invoice with async polling
  const submitInvoice = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsBusy(true);
    try {
      const body = new FormData();
      body.set('xml_text', form.xmlText);
      body.set('system_code', form.systemCode);
      body.set('schema_version', form.schemaVersion);
      body.set('form_value', form.formValue);
      if (form.offlineMode) body.set('offline_mode', '1');
      if (form.file instanceof File) body.set('xml_file', form.file);

      const res = await fetch(sendEndpoint, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const payload = await res.json() as { ok: boolean; jobId?: string; message?: string };
      if (!res.ok || !payload.ok) throw new Error(payload.message ?? 'Nie udalo sie wyslac faktury.');

      const jobId = payload.jobId;
      if (!jobId) throw new Error('Brak jobId w odpowiedzi.');

      setAlert('Wyslano do kolejki. Czekam na potwierdzenie...', 'ok');
      setIsModalOpen(false);
      setForm(prev => ({ ...prev, xmlText: '', file: null, offlineMode: false }));

      // Poll job status
      await pollJobStatus(jobId);
    } catch (err) {
      setAlert(err instanceof Error ? err.message : 'Wystapil nieznany blad.', 'error');
    } finally {
      setIsBusy(false);
    }
  };

  const pollJobStatus = async (jobId: string): Promise<void> => {
    const MAX_ATTEMPTS = 90;
    for (let i = 0; i < MAX_ATTEMPTS; i++) {
      await new Promise(r => setTimeout(r, 2000));
      try {
        const res = await fetch(`/send/status/${encodeURIComponent(jobId)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await res.json() as { ok: boolean; status?: string; ksefNumber?: string; error?: string };
        if (!payload.ok) continue;
        if (payload.status === 'done') {
          setAlert(`Faktura wyslana. Ref: ${payload.ksefNumber ?? 'n/d'}`, 'ok');
          // Refresh stats and activity
          fetchDashboardStats().then(setStats).catch(() => null);
          fetchInvoices(1, 5).then(r => setActivityRows(r.items)).catch(() => null);
          if (activeSection === 'invoices') loadInvoices(invoicePage);
          return;
        }
        if (payload.status === 'failed') {
          setAlert(`Blad wysylki: ${payload.error ?? 'Nieznany blad'}`, 'error');
          return;
        }
      } catch (_) { /* ignore polling errors */ }
    }
    setAlert('Timeout oczekiwania na status wysylki.', 'error');
  };

  const updatePaymentStatus = async (invoiceRef: string, status: PaymentStatus) => {
    try {
      const res = await fetch(`/api/invoices/${encodeURIComponent(invoiceRef)}/payment-status`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ status }),
      });
      const payload = await res.json() as { ok: boolean; message?: string };
      if (!payload.ok) throw new Error(payload.message ?? 'Blad aktualizacji statusu.');
      setInvoiceData(prev => prev ? {
        ...prev,
        items: prev.items.map(inv =>
          inv.invoiceReferenceNumber === invoiceRef ? { ...inv, paymentStatus: status } : inv
        ),
      } : prev);
      setActivityRows(prev => prev.map(inv =>
        inv.invoiceReferenceNumber === invoiceRef ? { ...inv, paymentStatus: status } : inv
      ));
    } catch (err) {
      setAlert(err instanceof Error ? err.message : 'Blad aktualizacji statusu.', 'error');
    }
  };

  const resolveDownloadEndpoint = (ksefNumber: string) =>
    `/invoices/download/${encodeURIComponent(ksefNumber)}`;
  const resolvePdfEndpoint = (ksefNumber: string) =>
    `/invoices/download/${encodeURIComponent(ksefNumber)}/pdf`;

  const downloadFile = async (ksefNumber: string, endpoint: string, ext: string) => {
    if (!ksefNumber || ksefNumber === 'n/d') { setAlert('Brak numeru KSeF.', 'error'); return; }
    setIsBusy(true);
    try {
      const res = await fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) {
        const ct = res.headers.get('content-type') ?? '';
        const msg = ct.includes('application/json')
          ? ((await res.json() as { message?: string }).message ?? 'Blad pobierania')
          : await res.text();
        throw new Error(msg);
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = `${ksefNumber}.${ext}`;
      document.body.appendChild(a); a.click(); a.remove();
      URL.revokeObjectURL(url);
      setAlert(`Pobrano ${ksefNumber} (${ext.toUpperCase()}).`, 'ok');
    } catch (err) {
      setAlert(err instanceof Error ? err.message : 'Blad pobierania.', 'error');
    } finally {
      setIsBusy(false);
    }
  };

  const activeNav = NAV_ITEMS.find(n => n.id === activeSection) ?? NAV_ITEMS[0];

  // ---------------------------------------------------------------------------
  // Render helpers
  // ---------------------------------------------------------------------------

  const renderStartSection = () => (
    <section style={S.sectionStack}>
      <div style={S.hero(theme)}>
        <div>
          <div style={S.eyebrow(theme)}>Start</div>
          <h1 style={S.heroTitle}>Pulpit operacyjny KSeF.</h1>
          <p style={S.heroText(theme)}>Wysylaj faktury, monitoruj statusy i przegladaj archiwum wysylek.</p>
        </div>
        <div style={S.heroActions(isNarrow)}>
          <button style={S.btn.secondary(theme)} type="button" onClick={() => setIsModalOpen(true)}>Wyslij fakture</button>
        </div>
      </div>

      <div style={S.metricsGrid}>
        <MetricCard theme={theme} label="Wyslane w tym miesiacu" value={stats?.sentThisMonth ?? '—'} note="Faktury wyslane do KSeF w biezacym miesiacu." accent="blue" />
        <MetricCard theme={theme} label="Niezaplacone" value={stats?.unpaidCount ?? '—'} note="Faktury oczekujace na platnosc." accent="amber" />
        <MetricCard theme={theme} label="Ostatnia aktywnosc" value={activityRows.length > 0 ? activityRows[0].submittedAt.slice(0, 10) : '—'} note="Data ostatniego dokumentu." accent="green" />
        <MetricCard theme={theme} label="Wszyskie rekordy" value={invoiceData?.total ?? stats?.sentThisMonth ?? '—'} note="Laczna liczba zapisanych wysylek." accent="orange" />
      </div>

      <div style={S.contentGrid(isNarrow)}>
        <section style={S.panel(theme)}>
          <div style={S.panelHeader}>
            <div>
              <div style={S.panelTitle}>Ostatnia aktywnosc</div>
              <div style={S.panelMeta(theme)}>Ostatnie 5 wysylek z bazy.</div>
            </div>
            <button style={S.btn.ghost(theme)} type="button" onClick={() => openSection('invoices')}>Przejdz do faktur</button>
          </div>
          <div style={S.activityList}>
            {activityRows.length === 0
              ? <div style={S.emptyState(theme)}>Brak danych do pokazania.</div>
              : activityRows.map(row => (
                <div key={`${row.sessionReferenceNumber}-${row.invoiceReferenceNumber}`} style={S.activityRow(theme)}>
                  <div>
                    <div style={S.activityPrimary}>{row.invoiceReferenceNumber}</div>
                    <div style={S.activitySecondary(theme)}>{row.submittedAt.slice(0, 10)} · {row.paymentStatus}</div>
                  </div>
                  <span style={S.paymentBadge(row.paymentStatus)}>{row.paymentStatus}</span>
                </div>
              ))}
          </div>
        </section>

        <section style={S.panel(theme)}>
          <div style={S.panelHeader}>
            <div>
              <div style={S.panelTitle}>Moduly w nawigacji</div>
              <div style={S.panelMeta(theme)}>Układ jest gotowy na kolejne fazy roadmapy.</div>
            </div>
          </div>
          <div style={S.moduleList}>
            {NAV_ITEMS.map(item => (
              <div key={item.id} style={S.moduleRow(theme)}>
                <div>
                  <div style={S.activityPrimary}>{item.label}</div>
                  <div style={S.activitySecondary(theme)}>{item.description}</div>
                </div>
                <span style={S.navTag(item.tag)}>{item.tag}</span>
              </div>
            ))}
          </div>
        </section>
      </div>
    </section>
  );

  const renderInvoicesSection = () => {
    const items = invoiceData?.items ?? [];
    const total = invoiceData?.total ?? 0;
    const pages = invoiceData?.pages ?? 1;
    const page = invoiceData?.page ?? 1;

    return (
      <section style={S.sectionStack}>
        <div style={S.sectionIntro}>
          <div>
            <div style={S.eyebrow(theme)}>Faktury</div>
            <h1 style={S.sectionTitle}>Lista wysylek z lokalnej bazy.</h1>
            <p style={S.sectionText(theme)}>Faktury zapisane po wysylce do KSeF. Pobierz XML lub PDF dla kazdej pozycji.</p>
          </div>
          <div style={S.sectionActions(isNarrow)}>
            <div style={S.inlineMetric(theme)}><strong>{total}</strong><span>rekordow</span></div>
          </div>
        </div>

        <section style={S.tableCard(theme)}>
          <div style={S.tableHeaderBar(isNarrow)}>
            <div>
              <div style={S.tableTitle}>Lista faktur</div>
              <div style={S.panelMeta(theme)}>Strona {page} z {pages}</div>
            </div>
            <div style={S.tableActions(isNarrow)}>
              <button style={S.btn.secondary(theme)} type="button" onClick={() => loadInvoices(page)} disabled={isBusy}>Odswiez</button>
              <button style={S.btn.primary} type="button" onClick={() => setIsModalOpen(true)}>Nowa wysylka</button>
            </div>
          </div>

          <div style={S.tableWrap}>
            <table style={S.table}>
              <thead>
                <tr>
                  <th style={S.th(theme)}>Data</th>
                  <th style={S.th(theme)}>Invoice Ref</th>
                  <th style={S.th(theme)}>Session Ref</th>
                  <th style={S.th(theme)}>Status platnosci</th>
                  <th style={S.th(theme)}>Akcje</th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0
                  ? <tr><td style={S.td(theme)} colSpan={5}>Brak faktur.</td></tr>
                  : items.map(row => (
                    <tr key={`${row.sessionReferenceNumber}-${row.invoiceReferenceNumber}`}>
                      <td style={S.td(theme)}>{row.submittedAt.slice(0, 10)}</td>
                      <td style={S.td(theme)}><small style={S.small(theme)}>{row.invoiceReferenceNumber}</small></td>
                      <td style={S.td(theme)}><small style={S.small(theme)}>{row.sessionReferenceNumber}</small></td>
                      <td style={S.td(theme)}>
                        <select
                          style={S.paymentSelect(row.paymentStatus)}
                          value={row.paymentStatus}
                          onChange={e => updatePaymentStatus(row.invoiceReferenceNumber, e.target.value as PaymentStatus)}
                        >
                          <option value="unpaid">unpaid</option>
                          <option value="paid">paid</option>
                          <option value="overdue">overdue</option>
                        </select>
                      </td>
                      <td style={S.td(theme)}>
                        <div style={S.actionRow}>
                          <button style={S.btn.xml(theme)} type="button" disabled={isBusy}
                            onClick={() => downloadFile(row.invoiceReferenceNumber, resolveDownloadEndpoint(row.invoiceReferenceNumber), 'xml')}>XML</button>
                          <button style={S.btn.pdf} type="button" disabled={isBusy}
                            onClick={() => downloadFile(row.invoiceReferenceNumber, resolvePdfEndpoint(row.invoiceReferenceNumber), 'pdf')}>PDF</button>
                        </div>
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>

          {pages > 1 && (
            <div style={S.pagination}>
              <button style={S.btn.secondary(theme)} type="button" disabled={page <= 1 || isBusy} onClick={() => loadInvoices(page - 1)}>&#8592; Poprzednia</button>
              <span style={S.pageInfo(theme)}>Strona {page} z {pages}</span>
              <button style={S.btn.secondary(theme)} type="button" disabled={page >= pages || isBusy} onClick={() => loadInvoices(page + 1)}>Nastepna &#8594;</button>
            </div>
          )}
        </section>
      </section>
    );
  };

  const renderPlaceholder = (title: string, text: string) => (
    <section style={S.sectionStack}>
      <div style={S.sectionIntro}>
        <div>
          <div style={S.eyebrow(theme)}>{activeNav.label}</div>
          <h1 style={S.sectionTitle}>{title}</h1>
          <p style={S.sectionText(theme)}>{text}</p>
        </div>
      </div>
      <section style={S.placeholderCard(theme)}>
        <div style={S.placeholderTitle}>Sekcja w przygotowaniu</div>
        <div style={S.placeholderText(theme)}>Layout jest juz gotowy. Kolejna implementacja moze wejsc w ten modul bez przebudowy shellu aplikacji.</div>
        <div style={S.placeholderActions(isNarrow)}>
          <button style={S.btn.secondary(theme)} type="button" onClick={() => openSection('start')}>Wroc do Start</button>
        </div>
      </section>
    </section>
  );

  const renderActiveSection = () => {
    if (activeSection === 'start') return renderStartSection();
    if (activeSection === 'invoices') return renderInvoicesSection();
    if (activeSection === 'contractors') return renderPlaceholder('Modul kontrahentow czeka na warstwe danych.', 'CRUD kontrahentow, autocomplete NIP i historia rozliczen.');
    if (activeSection === 'reports') return renderPlaceholder('Raporty dostaly juz miejsce w glownej nawigacji.', 'Po dodaniu statusow platnosci tu trafi widok finansowy z wykresami.');
    return renderPlaceholder('Ustawienia sa gotowe na osobny modul.', 'Konfiguracja firmy, KSeF i numeracji faktur.');
  };

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div style={S.appFrame(isNarrow)}>
      {!isNarrow && (
        <Sidebar theme={theme} activeSection={activeSection} navItems={NAV_ITEMS} drawerMode={false} onNavigate={openSection} />
      )}

      {isNarrow && isDrawerOpen && (
        <div style={S.drawerOverlay} onClick={() => setIsDrawerOpen(false)}>
          <div style={S.drawerWrap} onClick={e => e.stopPropagation()}>
            <Sidebar theme={theme} activeSection={activeSection} navItems={NAV_ITEMS} drawerMode={true} onNavigate={openSection} />
          </div>
        </div>
      )}

      <main style={S.contentShell}>
        <header style={S.topBar(theme, isNarrow)}>
          <div style={S.topLeft(isNarrow)}>
            {isNarrow && <button style={S.iconButton(theme)} type="button" onClick={() => setIsDrawerOpen(true)}>Menu</button>}
            <div>
              <div style={S.topTitle}>{activeNav.label}</div>
              <div style={S.topSubtitle(theme)}>{activeNav.description}</div>
            </div>
          </div>
          <div style={S.topRight(isNarrow)}>
            <button style={S.btn.secondary(theme)} type="button" onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}>
              {theme === 'light' ? 'Tryb ciemny' : 'Tryb jasny'}
            </button>
            <div style={S.avatar(theme)}>
              <div style={S.avatarBadge}>JS</div>
              <div>
                <div style={S.avatarName}>Jakub Szczerba</div>
                <div style={S.avatarRole(theme)}>Owner</div>
              </div>
            </div>
          </div>
        </header>

        <div style={S.breadcrumbs(theme)}>
          <span style={S.breadcrumbMuted(theme)}>Dashboard</span>
          <span>/</span>
          <strong>{activeNav.label}</strong>
        </div>

        {message && <div style={S.toast(messageType)}>{message}</div>}

        {renderActiveSection()}
      </main>

      {isModalOpen && (
        <SendInvoiceModal
          theme={theme}
          isNarrow={isNarrow}
          isBusy={isBusy}
          form={form}
          onFormChange={patch => setForm(prev => ({ ...prev, ...patch }))}
          onClose={() => setIsModalOpen(false)}
          onSubmit={submitInvoice}
        />
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// MetricCard component
// ---------------------------------------------------------------------------

function MetricCard({ theme, label, value, note, accent }: {
  theme: Theme;
  label: string;
  value: number | string;
  note: string;
  accent: 'blue' | 'green' | 'amber' | 'orange';
}) {
  return (
    <div style={S.metricCard(theme, accent)}>
      <div style={S.metricLabel(theme)}>{label}</div>
      <div style={S.metricValue}>{value}</div>
      <div style={S.metricNote(theme)}>{note}</div>
    </div>
  );
}
