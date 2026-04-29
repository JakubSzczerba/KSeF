import { useCallback, useEffect, useRef, useState } from 'react';
import { csvDownloadUrl, fetchRevenueByMonth } from '../api/reports';
import type { MonthData } from '../api/reports';

const MONTHS_PL = ['Sty', 'Lut', 'Mar', 'Kwi', 'Maj', 'Cze', 'Lip', 'Sie', 'Wrz', 'Paź', 'Lis', 'Gru'];
const STATUS_OPTIONS = [
  { value: '', label: 'Wszystkie statusy' },
  { value: 'paid', label: 'Opłacone' },
  { value: 'unpaid', label: 'Nieopłacone' },
  { value: 'overdue', label: 'Przeterminowane' },
];

function BarChart({ data, isDark }: { data: MonthData[]; isDark: boolean }) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const W = canvas.width;
    const H = canvas.height;
    const PAD = { top: 20, right: 20, bottom: 40, left: 70 };
    const chartW = W - PAD.left - PAD.right;
    const chartH = H - PAD.top - PAD.bottom;

    ctx.clearRect(0, 0, W, H);

    const bg = isDark ? '#1a1a2e' : '#f8f9fa';
    const text = isDark ? '#cdd6f4' : '#333';
    const grid = isDark ? '#313244' : '#e0e0e0';
    const bar = isDark ? '#89b4fa' : '#4f8ef7';

    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    const maxRev = Math.max(...data.map(d => d.revenue), 1);
    const topVal = Math.ceil(maxRev / 1000) * 1000 || 1000;
    const gridLines = 5;

    ctx.strokeStyle = grid;
    ctx.lineWidth = 1;
    ctx.font = '11px monospace';
    ctx.fillStyle = text;
    ctx.textAlign = 'right';

    for (let i = 0; i <= gridLines; i++) {
      const y = PAD.top + chartH - (i / gridLines) * chartH;
      ctx.beginPath();
      ctx.moveTo(PAD.left, y);
      ctx.lineTo(PAD.left + chartW, y);
      ctx.stroke();
      ctx.fillText((topVal * i / gridLines).toLocaleString('pl-PL'), PAD.left - 6, y + 4);
    }

    const barW = Math.floor(chartW / 12) - 4;

    data.forEach((d, i) => {
      const x = PAD.left + (i / 12) * chartW + (chartW / 12 - barW) / 2;
      const barH = (d.revenue / topVal) * chartH;
      const y = PAD.top + chartH - barH;

      ctx.fillStyle = bar;
      ctx.fillRect(x, y, barW, barH);

      if (d.count > 0) {
        ctx.fillStyle = text;
        ctx.textAlign = 'center';
        ctx.font = '10px monospace';
        ctx.fillText(d.revenue > 0 ? d.revenue.toLocaleString('pl-PL', { maximumFractionDigits: 0 }) : String(d.count), x + barW / 2, y - 4);
      }

      ctx.fillStyle = text;
      ctx.textAlign = 'center';
      ctx.font = '11px monospace';
      ctx.fillText(MONTHS_PL[i], x + barW / 2, H - PAD.bottom + 16);
    });
  }, [data, isDark]);

  return <canvas ref={canvasRef} width={760} height={300} style={{ width: '100%', height: 'auto', display: 'block' }} />;
}

export default function ReportsSection() {
  const currentYear = new Date().getFullYear();
  const [year, setYear] = useState(currentYear);
  const [status, setStatus] = useState('');
  const [data, setData] = useState<MonthData[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const isDark = document.documentElement.classList.contains('dark') ||
    window.matchMedia('(prefers-color-scheme: dark)').matches;

  const load = useCallback(async (y: number, s: string) => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetchRevenueByMonth(y, s || undefined);
      if (!res.ok) throw new Error('Błąd pobierania danych');
      setData(res.data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Błąd');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(year, status); }, [year, status, load]);

  const totalRevenue = data.reduce((s, d) => s + d.revenue, 0);
  const totalCount = data.reduce((s, d) => s + d.count, 0);

  const years = Array.from({ length: 5 }, (_, i) => currentYear - i);

  return (
    <div className="reports-section">
      <div className="section-header">
        <h2>Raporty</h2>
        <a
          href={csvDownloadUrl(status || undefined)}
          className="btn-secondary"
          download
        >
          Pobierz CSV
        </a>
      </div>

      <div className="reports-filters">
        <label>
          Rok:
          <select value={year} onChange={e => setYear(Number(e.target.value))}>
            {years.map(y => <option key={y} value={y}>{y}</option>)}
          </select>
        </label>
        <label>
          Status:
          <select value={status} onChange={e => setStatus(e.target.value)}>
            {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
          </select>
        </label>
      </div>

      <div className="reports-summary">
        <div className="summary-card">
          <span className="summary-label">Łącznie faktur</span>
          <span className="summary-value">{totalCount}</span>
        </div>
        <div className="summary-card">
          <span className="summary-label">Łączny przychód</span>
          <span className="summary-value">{totalRevenue.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} PLN</span>
        </div>
      </div>

      {error && <div className="error-banner">{error}</div>}

      <div className="chart-wrapper" style={{ opacity: loading ? 0.5 : 1 }}>
        <BarChart data={data} isDark={isDark} />
      </div>

      <table className="data-table reports-table">
        <thead>
          <tr>
            <th>Miesiąc</th>
            <th>Liczba faktur</th>
            <th>Przychód (PLN)</th>
          </tr>
        </thead>
        <tbody>
          {data.map(d => (
            <tr key={d.month}>
              <td>{MONTHS_PL[d.month - 1]} {year}</td>
              <td>{d.count}</td>
              <td className="num-cell">{d.revenue.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
