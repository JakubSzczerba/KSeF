import { useEffect, useState, useCallback } from 'react';
import type { Bootstrap, DashboardStats, PaginatedInvoices, SubmittedInvoice } from './types/invoice';
import { fetchDashboardStats } from './api/dashboard';
import { fetchInvoices } from './api/invoices';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface FormState {
  xmlText: string;
  file: File | null;
  systemCode: string;
  schemaVersion: string;
  formValue: string;
  offlineMode: boolean;
}

type Theme = 'light' | 'dark';
type SectionId = 'start' | 'invoices' | 'contractors' | 'reports' | 'settings';

interface NavItem {
  id: SectionId;
  label: string;
  description: string;
  tag: 'LIVE' | 'WIP';
}

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

  const renderSidebar = (drawerMode: boolean) => (
    <aside style={S.sidebar(theme, drawerMode)}>
      <div style={S.sidebarTop}>
        <div style={S.brandMark}>KS</div>
        <div>
          <div style={S.brandTitle}>KSeF Workspace</div>
          <div style={S.brandSubtitle(theme)}>Panel operacyjny dla wysylek i obiegu faktur.</div>
        </div>
      </div>

      <nav style={S.navList}>
        {NAV_ITEMS.map(item => (
          <button key={item.id} type="button" style={S.navButton(theme, activeSection === item.id)} onClick={() => openSection(item.id)}>
            <div>
              <div style={S.navLabel}>{item.label}</div>
              <div style={S.navDescription(theme)}>{item.description}</div>
            </div>
            <span style={S.navTag(item.tag)}>{item.tag}</span>
          </button>
        ))}
      </nav>

      <div style={S.sidebarFooter(theme)}>
        <div style={S.sidebarFooterTitle}>KSeF Dashboard</div>
        <div style={S.sidebarFooterText(theme)}>Wysylka faktur, monitoring statusow i archiwum.</div>
      </div>
    </aside>
  );

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
                      <td style={S.td(theme)}><span style={S.paymentBadge(row.paymentStatus)}>{row.paymentStatus}</span></td>
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
      {!isNarrow && renderSidebar(false)}

      {isNarrow && isDrawerOpen && (
        <div style={S.drawerOverlay} onClick={() => setIsDrawerOpen(false)}>
          <div style={S.drawerWrap} onClick={e => e.stopPropagation()}>
            {renderSidebar(true)}
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
        <div style={S.overlay} onClick={() => !isBusy && setIsModalOpen(false)}>
          <div style={S.modal(theme, isNarrow)} onClick={e => e.stopPropagation()}>
            <div style={S.modalHeader}>
              <div>
                <div style={S.eyebrow(theme)}>Nowa wysylka</div>
                <h2 style={S.modalTitle}>Wysylka faktury do KSeF</h2>
              </div>
              <button style={S.iconButton(theme)} type="button" onClick={() => setIsModalOpen(false)} disabled={isBusy}>Zamknij</button>
            </div>
            <form style={S.form} onSubmit={submitInvoice}>
              <label style={S.label}>Plik XML</label>
              <input style={S.input(theme)} type="file" accept=".xml,text/xml,application/xml"
                onChange={e => setForm(p => ({ ...p, file: e.target.files?.[0] ?? null }))} />

              <label style={S.label}>Tresc XML</label>
              <textarea style={S.textarea(theme)} value={form.xmlText}
                onChange={e => setForm(p => ({ ...p, xmlText: e.target.value }))}
                placeholder="Wklej XML faktury FA(3)" />

              <div style={S.row(isNarrow)}>
                {(['systemCode', 'schemaVersion', 'formValue'] as const).map(field => (
                  <div key={field}>
                    <label style={S.label}>{field}</label>
                    <input style={S.input(theme)} value={form[field]}
                      onChange={e => setForm(p => ({ ...p, [field]: e.target.value }))} />
                  </div>
                ))}
              </div>

              <label style={S.check}>
                <input type="checkbox" checked={form.offlineMode}
                  onChange={e => setForm(p => ({ ...p, offlineMode: e.target.checked }))} />
                {' '}Uzyj offlineMode
              </label>

              <div style={S.modalActions}>
                <button style={S.btn.ghost(theme)} type="button" onClick={() => setIsModalOpen(false)} disabled={isBusy}>Anuluj</button>
                <button style={S.btn.primary} type="submit" disabled={isBusy}>{isBusy ? 'Wysylanie...' : 'Wyslij'}</button>
              </div>
            </form>
          </div>
        </div>
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

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

type CSSProperties = React.CSSProperties;

const S = {
  appFrame: (narrow: boolean): CSSProperties => ({
    minHeight: '100vh',
    width: 'min(1500px, calc(100% - 1.4rem))',
    margin: '0 auto',
    padding: narrow ? '0.7rem 0 1rem' : '1rem 0 1.2rem',
    display: 'grid',
    gridTemplateColumns: narrow ? '1fr' : '280px minmax(0, 1fr)',
    gap: '0.9rem',
  }),
  sidebar: (theme: Theme, drawer: boolean): CSSProperties => ({
    borderRadius: '24px',
    padding: '1rem',
    display: 'grid',
    gap: '1rem',
    alignContent: 'start',
    minHeight: drawer ? '100%' : 'calc(100vh - 2rem)',
    background: theme === 'light'
      ? 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(241,247,255,0.94))'
      : 'linear-gradient(180deg, rgba(12,19,37,0.98), rgba(16,27,49,0.95))',
    border: theme === 'light' ? '1px solid #dce7f3' : '1px solid #304566',
    boxShadow: '0 22px 44px rgba(6, 12, 29, 0.22)',
  }),
  sidebarTop: { display: 'grid', gridTemplateColumns: '56px 1fr', gap: '0.75rem', alignItems: 'center' } as CSSProperties,
  brandMark: { width: '56px', height: '56px', borderRadius: '18px', display: 'grid', placeItems: 'center', fontFamily: 'Sora, sans-serif', fontWeight: 800, color: '#fff', background: 'linear-gradient(135deg, #ff7a2c, #ffc04c)' } as CSSProperties,
  brandTitle: { fontFamily: 'Sora, sans-serif', fontWeight: 700, fontSize: '1rem' } as CSSProperties,
  brandSubtitle: (theme: Theme): CSSProperties => ({ marginTop: '0.2rem', color: theme === 'light' ? '#4a6484' : '#95adcf', fontSize: '0.82rem', lineHeight: 1.5 }),
  navList: { display: 'grid', gap: '0.5rem' } as CSSProperties,
  navButton: (theme: Theme, active: boolean): CSSProperties => ({
    width: '100%', border: active ? '1px solid rgba(255,135,42,0.55)' : theme === 'light' ? '1px solid #dde8f3' : '1px solid #2f466a',
    borderRadius: '18px', padding: '0.8rem 0.85rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '0.8rem', textAlign: 'left', cursor: 'pointer', color: theme === 'light' ? '#11243a' : '#dbe7f9',
    background: active ? theme === 'light' ? 'linear-gradient(135deg, rgba(255,233,210,0.95), rgba(255,255,255,0.96))' : 'linear-gradient(135deg, rgba(72,47,27,0.85), rgba(30,45,72,0.96))' : theme === 'light' ? 'rgba(255,255,255,0.72)' : 'rgba(15,27,49,0.7)',
  }),
  navLabel: { fontWeight: 800, fontSize: '0.95rem' } as CSSProperties,
  navDescription: (theme: Theme): CSSProperties => ({ marginTop: '0.2rem', color: theme === 'light' ? '#5b7492' : '#93aace', fontSize: '0.77rem', lineHeight: 1.45 }),
  navTag: (tag: string): CSSProperties => ({ borderRadius: '999px', padding: '0.22rem 0.48rem', fontSize: '0.68rem', fontWeight: 800, color: tag === 'LIVE' ? '#14532d' : '#92400e', background: tag === 'LIVE' ? '#dcfce7' : '#fff1d6' }),
  sidebarFooter: (theme: Theme): CSSProperties => ({ marginTop: 'auto', borderRadius: '18px', padding: '0.9rem', background: theme === 'light' ? '#eff5fc' : '#162640', border: theme === 'light' ? '1px solid #d9e5f1' : '1px solid #304566' }),
  sidebarFooterTitle: { fontFamily: 'Sora, sans-serif', fontWeight: 700, fontSize: '0.82rem' } as CSSProperties,
  sidebarFooterText: (theme: Theme): CSSProperties => ({ marginTop: '0.3rem', color: theme === 'light' ? '#4c6787' : '#93aace', fontSize: '0.76rem', lineHeight: 1.5 }),
  drawerOverlay: { position: 'fixed', inset: 0, zIndex: 900, background: 'rgba(4,9,22,0.58)', padding: '0.7rem', backdropFilter: 'blur(3px)' } as CSSProperties,
  drawerWrap: { width: 'min(340px, 92vw)', height: '100%' } as CSSProperties,
  contentShell: { display: 'grid', gap: '0.8rem', alignContent: 'start' } as CSSProperties,
  topBar: (theme: Theme, narrow: boolean): CSSProperties => ({
    display: 'flex', flexDirection: narrow ? 'column' : 'row', alignItems: narrow ? 'stretch' : 'center', justifyContent: 'space-between', gap: '0.8rem', borderRadius: '24px', padding: '1rem 1.05rem',
    background: theme === 'light' ? 'linear-gradient(118deg, rgba(255,153,71,0.16), rgba(255,255,255,0.88) 46%, rgba(122,204,255,0.84))' : 'linear-gradient(118deg, rgba(255,121,49,0.26), rgba(20,32,58,0.92) 45%, rgba(59,112,186,0.84))',
    boxShadow: '0 18px 36px rgba(5,11,30,0.22)', border: theme === 'light' ? '1px solid #e7eef8' : '1px solid #2e4062',
  }),
  topLeft: (narrow: boolean): CSSProperties => ({ display: 'flex', alignItems: 'center', gap: '0.8rem', flexWrap: narrow ? 'wrap' : 'nowrap' }),
  topTitle: { fontFamily: 'Sora, sans-serif', fontWeight: 700, fontSize: '1.05rem' } as CSSProperties,
  topSubtitle: (theme: Theme): CSSProperties => ({ color: theme === 'light' ? '#4d6680' : '#90aacb', fontSize: '0.8rem', marginTop: '0.15rem' }),
  topRight: (narrow: boolean): CSSProperties => ({ display: 'flex', alignItems: 'center', gap: '0.6rem', flexWrap: narrow ? 'wrap' : 'nowrap' }),
  avatar: (theme: Theme): CSSProperties => ({ display: 'flex', alignItems: 'center', gap: '0.6rem', borderRadius: '14px', padding: '0.45rem 0.75rem', background: theme === 'light' ? 'rgba(255,255,255,0.7)' : 'rgba(18,30,55,0.8)', border: theme === 'light' ? '1px solid #dce8f4' : '1px solid #2c4265' }),
  avatarBadge: { width: '34px', height: '34px', borderRadius: '10px', display: 'grid', placeItems: 'center', fontWeight: 800, fontSize: '0.75rem', color: '#fff', background: 'linear-gradient(135deg, #ff7a2c, #ff9f50)' } as CSSProperties,
  avatarName: { fontWeight: 700, fontSize: '0.82rem' } as CSSProperties,
  avatarRole: (theme: Theme): CSSProperties => ({ color: theme === 'light' ? '#5a7492' : '#93aace', fontSize: '0.72rem' }),
  breadcrumbs: (theme: Theme): CSSProperties => ({ display: 'flex', gap: '0.5rem', alignItems: 'center', fontSize: '0.82rem', padding: '0.1rem 0.2rem', color: theme === 'light' ? '#5b7492' : '#93aace' }),
  breadcrumbMuted: (theme: Theme): CSSProperties => ({ color: theme === 'light' ? '#9ab0c4' : '#5a7898' }),
  toast: (type: string): CSSProperties => ({ padding: '0.65rem 0.95rem', borderRadius: '14px', fontSize: '0.87rem', fontWeight: 500, background: type === 'ok' ? '#dcfce7' : '#fee2e2', color: type === 'ok' ? '#14532d' : '#7f1d1d', border: type === 'ok' ? '1px solid #bbf7d0' : '1px solid #fecaca' }),
  iconButton: (theme: Theme): CSSProperties => ({ border: theme === 'light' ? '1px solid #d5e4f1' : '1px solid #2d4568', borderRadius: '12px', padding: '0.4rem 0.7rem', cursor: 'pointer', background: theme === 'light' ? 'rgba(255,255,255,0.8)' : 'rgba(14,24,46,0.8)', color: theme === 'light' ? '#11243a' : '#dbe7f9', fontSize: '0.8rem', fontWeight: 600 }),
  sectionStack: { display: 'grid', gap: '0.9rem' } as CSSProperties,
  hero: (theme: Theme): CSSProperties => ({ borderRadius: '24px', padding: '1.4rem 1.5rem', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem', flexWrap: 'wrap', background: theme === 'light' ? 'rgba(255,255,255,0.78)' : 'rgba(10,18,38,0.8)', border: theme === 'light' ? '1px solid #dce7f3' : '1px solid #304566' }),
  eyebrow: (theme: Theme): CSSProperties => ({ fontSize: '0.75rem', fontWeight: 800, letterSpacing: '0.08em', textTransform: 'uppercase', color: theme === 'light' ? '#c85c0a' : '#f59d48', marginBottom: '0.4rem' }),
  heroTitle: { fontFamily: 'Sora, sans-serif', fontWeight: 700, fontSize: '1.5rem', margin: '0 0 0.5rem' } as CSSProperties,
  heroText: (theme: Theme): CSSProperties => ({ color: theme === 'light' ? '#4c6680' : '#90aacb', fontSize: '0.9rem', margin: 0, lineHeight: 1.6 }),
  heroActions: (narrow: boolean): CSSProperties => ({ display: 'flex', gap: '0.6rem', flexWrap: 'wrap', alignItems: 'center', flexShrink: 0, marginTop: narrow ? '0.5rem' : 0 }),
  metricsGrid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.8rem' } as CSSProperties,
  metricCard: (theme: Theme, accent: string): CSSProperties => {
    const colors: Record<string, [string, string, string, string]> = {
      blue: ['#dbeafe', '#1e40af', '#eff6ff', '#1e3a8a'],
      green: ['#dcfce7', '#166534', '#f0fdf4', '#14532d'],
      amber: ['#fef9c3', '#92400e', '#fffbeb', '#78350f'],
      orange: ['#ffedd5', '#9a3412', '#fff7ed', '#7c2d12'],
    };
    const [lightBg, lightColor, darkBg, darkColor] = colors[accent] ?? colors['blue'];
    return { borderRadius: '20px', padding: '1rem 1.1rem', background: theme === 'light' ? lightBg : darkBg, border: `1px solid ${theme === 'light' ? lightBg : darkBg}`, color: theme === 'light' ? lightColor : darkColor };
  },
  metricLabel: (theme: Theme): CSSProperties => ({ fontSize: '0.77rem', fontWeight: 600, opacity: 0.75, marginBottom: '0.4rem', color: theme === 'light' ? 'inherit' : 'inherit' }),
  metricValue: { fontFamily: 'Sora, sans-serif', fontWeight: 800, fontSize: '2rem', lineHeight: 1 } as CSSProperties,
  metricNote: (_theme: Theme): CSSProperties => ({ fontSize: '0.73rem', marginTop: '0.35rem', opacity: 0.7, lineHeight: 1.4 }),
  contentGrid: (narrow: boolean): CSSProperties => ({ display: 'grid', gridTemplateColumns: narrow ? '1fr' : '1fr 1fr', gap: '0.8rem' }),
  panel: (theme: Theme): CSSProperties => ({ borderRadius: '20px', padding: '1rem', background: theme === 'light' ? 'rgba(255,255,255,0.82)' : 'rgba(10,19,38,0.82)', border: theme === 'light' ? '1px solid #dce7f3' : '1px solid #2d4268' }),
  panelHeader: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.8rem' } as CSSProperties,
  panelTitle: { fontFamily: 'Sora, sans-serif', fontWeight: 700, fontSize: '0.95rem' } as CSSProperties,
  panelMeta: (theme: Theme): CSSProperties => ({ color: theme === 'light' ? '#5b7492' : '#93aace', fontSize: '0.76rem', marginTop: '0.2rem' }),
  activityList: { display: 'grid', gap: '0.5rem' } as CSSProperties,
  activityRow: (theme: Theme): CSSProperties => ({ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.55rem 0.7rem', borderRadius: '12px', background: theme === 'light' ? '#f4f9ff' : '#0e1a35', border: theme === 'light' ? '1px solid #e0ecf8' : '1px solid #1e3057' }),
  activityPrimary: { fontWeight: 700, fontSize: '0.84rem', wordBreak: 'break-all' } as CSSProperties,
  activitySecondary: (theme: Theme): CSSProperties => ({ color: theme === 'light' ? '#5b7492' : '#93aace', fontSize: '0.74rem', marginTop: '0.15rem' }),
  emptyState: (theme: Theme): CSSProperties => ({ color: theme === 'light' ? '#9ab0c4' : '#5a7898', fontSize: '0.82rem', padding: '1rem 0', textAlign: 'center' }),
  moduleList: { display: 'grid', gap: '0.5rem' } as CSSProperties,
  moduleRow: (theme: Theme): CSSProperties => ({ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.55rem 0.7rem', borderRadius: '12px', background: theme === 'light' ? '#f4f9ff' : '#0e1a35', border: theme === 'light' ? '1px solid #e0ecf8' : '1px solid #1e3057' }),
  paymentBadge: (status: string): CSSProperties => ({
    borderRadius: '999px', padding: '0.2rem 0.45rem', fontSize: '0.68rem', fontWeight: 700,
    color: status === 'paid' ? '#14532d' : status === 'overdue' ? '#7f1d1d' : '#92400e',
    background: status === 'paid' ? '#dcfce7' : status === 'overdue' ? '#fee2e2' : '#fff1d6',
  }),
  sectionIntro: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem', flexWrap: 'wrap' } as CSSProperties,
  sectionTitle: { fontFamily: 'Sora, sans-serif', fontWeight: 700, fontSize: '1.4rem', margin: '0 0 0.4rem' } as CSSProperties,
  sectionText: (theme: Theme): CSSProperties => ({ color: theme === 'light' ? '#4c6680' : '#90aacb', fontSize: '0.88rem', margin: 0, lineHeight: 1.6 }),
  sectionActions: (narrow: boolean): CSSProperties => ({ display: 'flex', gap: '0.6rem', flexWrap: 'wrap', alignItems: 'center', flexShrink: 0, marginTop: narrow ? '0.5rem' : 0 }),
  inlineMetric: (theme: Theme): CSSProperties => ({ display: 'flex', flexDirection: 'column', alignItems: 'center', borderRadius: '12px', padding: '0.4rem 0.8rem', background: theme === 'light' ? 'rgba(255,255,255,0.7)' : 'rgba(12,22,44,0.7)', border: theme === 'light' ? '1px solid #dce7f3' : '1px solid #2d4268', fontSize: '0.75rem', gap: '0.1rem' }),
  tableCard: (theme: Theme): CSSProperties => ({ borderRadius: '20px', background: theme === 'light' ? 'rgba(255,255,255,0.85)' : 'rgba(10,18,38,0.85)', border: theme === 'light' ? '1px solid #dce7f3' : '1px solid #2d4268', overflow: 'hidden' }),
  tableHeaderBar: (narrow: boolean): CSSProperties => ({ display: 'flex', justifyContent: 'space-between', alignItems: narrow ? 'stretch' : 'center', flexDirection: narrow ? 'column' : 'row', gap: '0.6rem', padding: '0.9rem 1rem 0.7rem' }),
  tableTitle: { fontFamily: 'Sora, sans-serif', fontWeight: 700, fontSize: '0.95rem' } as CSSProperties,
  tableActions: (narrow: boolean): CSSProperties => ({ display: 'flex', gap: '0.5rem', flexWrap: narrow ? 'wrap' : 'nowrap' }),
  tableWrap: { overflowX: 'auto' } as CSSProperties,
  table: { width: '100%', borderCollapse: 'collapse', fontSize: '0.84rem' } as CSSProperties,
  th: (theme: Theme): CSSProperties => ({ padding: '0.55rem 0.8rem', textAlign: 'left', fontWeight: 700, fontSize: '0.75rem', color: theme === 'light' ? '#5b7492' : '#93aace', borderBottom: theme === 'light' ? '1px solid #dce7f3' : '1px solid #1e3057', whiteSpace: 'nowrap' }),
  td: (theme: Theme): CSSProperties => ({ padding: '0.55rem 0.8rem', borderBottom: theme === 'light' ? '1px solid #edf3fa' : '1px solid #182b4a', verticalAlign: 'middle' }),
  small: (theme: Theme): CSSProperties => ({ fontSize: '0.74rem', color: theme === 'light' ? '#6b8aaa' : '#7090b8', wordBreak: 'break-all' }),
  actionRow: { display: 'flex', gap: '0.35rem' } as CSSProperties,
  pagination: { display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '0.8rem', padding: '0.8rem 1rem' } as CSSProperties,
  pageInfo: (theme: Theme): CSSProperties => ({ fontSize: '0.82rem', color: theme === 'light' ? '#5b7492' : '#93aace' }),
  placeholderCard: (theme: Theme): CSSProperties => ({ borderRadius: '20px', padding: '2rem', textAlign: 'center', background: theme === 'light' ? 'rgba(255,255,255,0.82)' : 'rgba(10,18,38,0.82)', border: theme === 'light' ? '1px solid #dce7f3' : '1px solid #2d4268' }),
  placeholderTitle: { fontFamily: 'Sora, sans-serif', fontWeight: 700, fontSize: '1.1rem', marginBottom: '0.6rem' } as CSSProperties,
  placeholderText: (theme: Theme): CSSProperties => ({ color: theme === 'light' ? '#5b7492' : '#93aace', fontSize: '0.88rem', lineHeight: 1.6, marginBottom: '1.2rem' }),
  placeholderActions: (narrow: boolean): CSSProperties => ({ display: 'flex', gap: '0.6rem', justifyContent: 'center', flexWrap: narrow ? 'wrap' : 'nowrap' }),
  overlay: { position: 'fixed', inset: 0, zIndex: 1000, display: 'grid', placeItems: 'center', background: 'rgba(4,9,22,0.6)', backdropFilter: 'blur(4px)' } as CSSProperties,
  modal: (theme: Theme, narrow: boolean): CSSProperties => ({ width: 'min(600px, calc(100vw - 2rem))', maxHeight: 'calc(100vh - 2rem)', overflowY: 'auto', borderRadius: '24px', padding: narrow ? '1.2rem' : '1.6rem', background: theme === 'light' ? '#fff' : '#0d1c38', border: theme === 'light' ? '1px solid #dce7f3' : '1px solid #304566', boxShadow: '0 24px 60px rgba(4,9,22,0.5)' }),
  modalHeader: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1.2rem' } as CSSProperties,
  modalTitle: { fontFamily: 'Sora, sans-serif', fontWeight: 700, fontSize: '1.25rem', margin: '0.2rem 0 0' } as CSSProperties,
  form: { display: 'grid', gap: '0.8rem' } as CSSProperties,
  label: { fontWeight: 600, fontSize: '0.83rem' } as CSSProperties,
  input: (theme: Theme): CSSProperties => ({ width: '100%', padding: '0.5rem 0.7rem', borderRadius: '12px', border: theme === 'light' ? '1px solid #d0e0f0' : '1px solid #2d4268', background: theme === 'light' ? '#f6faff' : '#0c1a34', color: theme === 'light' ? '#11243a' : '#dbe7f9', fontSize: '0.87rem', fontFamily: 'inherit' }),
  textarea: (theme: Theme): CSSProperties => ({ width: '100%', minHeight: '120px', padding: '0.5rem 0.7rem', borderRadius: '12px', border: theme === 'light' ? '1px solid #d0e0f0' : '1px solid #2d4268', background: theme === 'light' ? '#f6faff' : '#0c1a34', color: theme === 'light' ? '#11243a' : '#dbe7f9', fontSize: '0.84rem', fontFamily: 'monospace', resize: 'vertical' }),
  row: (narrow: boolean): CSSProperties => ({ display: 'grid', gridTemplateColumns: narrow ? '1fr' : '1fr 1fr 1fr', gap: '0.6rem' }),
  check: { display: 'flex', alignItems: 'center', gap: '0.4rem', fontSize: '0.85rem', fontWeight: 500, cursor: 'pointer' } as CSSProperties,
  modalActions: { display: 'flex', justifyContent: 'flex-end', gap: '0.6rem', marginTop: '0.4rem' } as CSSProperties,
  btn: {
    primary: { borderRadius: '12px', padding: '0.5rem 1rem', border: 'none', background: 'linear-gradient(135deg, #ff7a2c, #ff9f50)', color: '#fff', fontWeight: 700, fontSize: '0.85rem', cursor: 'pointer' } as CSSProperties,
    secondary: (theme: Theme): CSSProperties => ({ borderRadius: '12px', padding: '0.5rem 0.9rem', border: theme === 'light' ? '1px solid #d5e4f1' : '1px solid #2d4568', background: theme === 'light' ? 'rgba(255,255,255,0.8)' : 'rgba(14,24,46,0.8)', color: theme === 'light' ? '#11243a' : '#dbe7f9', fontWeight: 600, fontSize: '0.83rem', cursor: 'pointer' }),
    ghost: (theme: Theme): CSSProperties => ({ borderRadius: '12px', padding: '0.5rem 0.9rem', border: 'none', background: 'transparent', color: theme === 'light' ? '#5b7492' : '#93aace', fontWeight: 600, fontSize: '0.83rem', cursor: 'pointer' }),
    xml: (theme: Theme): CSSProperties => ({ borderRadius: '10px', padding: '0.3rem 0.6rem', border: theme === 'light' ? '1px solid #dce7f3' : '1px solid #2d4268', background: 'transparent', color: theme === 'light' ? '#2563eb' : '#60a5fa', fontWeight: 700, fontSize: '0.74rem', cursor: 'pointer' }),
    pdf: { borderRadius: '10px', padding: '0.3rem 0.6rem', border: 'none', background: 'linear-gradient(135deg, #ff7a2c, #ff9f50)', color: '#fff', fontWeight: 700, fontSize: '0.74rem', cursor: 'pointer' } as CSSProperties,
  },
};
