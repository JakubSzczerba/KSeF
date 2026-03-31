import { S } from '../styles';
import type { Theme, FormState } from '../types/app';

interface SendInvoiceModalProps {
  theme: Theme;
  isNarrow: boolean;
  isBusy: boolean;
  form: FormState;
  onFormChange: (patch: Partial<FormState>) => void;
  onClose: () => void;
  onSubmit: (e: React.FormEvent) => void;
}

export function SendInvoiceModal({ theme, isNarrow, isBusy, form, onFormChange, onClose, onSubmit }: SendInvoiceModalProps) {
  return (
    <div style={S.overlay} onClick={() => !isBusy && onClose()}>
      <div style={S.modal(theme, isNarrow)} onClick={e => e.stopPropagation()}>
        <div style={S.modalHeader}>
          <div>
            <div style={S.eyebrow(theme)}>Nowa wysylka</div>
            <h2 style={S.modalTitle}>Wysylka faktury do KSeF</h2>
          </div>
          <button style={S.iconButton(theme)} type="button" onClick={onClose} disabled={isBusy}>Zamknij</button>
        </div>
        <form style={S.form} onSubmit={onSubmit}>
          <label style={S.label}>Plik XML</label>
          <input style={S.input(theme)} type="file" accept=".xml,text/xml,application/xml"
            onChange={e => onFormChange({ file: e.target.files?.[0] ?? null })} />

          <label style={S.label}>Tresc XML</label>
          <textarea style={S.textarea(theme)} value={form.xmlText}
            onChange={e => onFormChange({ xmlText: e.target.value })}
            placeholder="Wklej XML faktury FA(3)" />

          <div style={S.row(isNarrow)}>
            {(['systemCode', 'schemaVersion', 'formValue'] as const).map(field => (
              <div key={field}>
                <label style={S.label}>{field}</label>
                <input style={S.input(theme)} value={form[field]}
                  onChange={e => onFormChange({ [field]: e.target.value })} />
              </div>
            ))}
          </div>

          <label style={S.check}>
            <input type="checkbox" checked={form.offlineMode}
              onChange={e => onFormChange({ offlineMode: e.target.checked })} />
            {' '}Uzyj offlineMode
          </label>

          <div style={S.modalActions}>
            <button style={S.btn.ghost(theme)} type="button" onClick={onClose} disabled={isBusy}>Anuluj</button>
            <button style={S.btn.primary} type="submit" disabled={isBusy}>{isBusy ? 'Wysylanie...' : 'Wyslij'}</button>
          </div>
        </form>
      </div>
    </div>
  );
}
