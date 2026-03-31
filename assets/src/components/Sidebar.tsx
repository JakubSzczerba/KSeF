import { S } from '../styles';
import type { Theme, SectionId, NavItem } from '../types/app';

interface SidebarProps {
  theme: Theme;
  activeSection: SectionId;
  navItems: NavItem[];
  drawerMode: boolean;
  onNavigate: (id: SectionId) => void;
}

export function Sidebar({ theme, activeSection, navItems, drawerMode, onNavigate }: SidebarProps) {
  return (
    <aside style={S.sidebar(theme, drawerMode)}>
      <div style={S.sidebarTop}>
        <div style={S.brandMark}>KS</div>
        <div>
          <div style={S.brandTitle}>KSeF Workspace</div>
          <div style={S.brandSubtitle(theme)}>Panel operacyjny dla wysylek i obiegu faktur.</div>
        </div>
      </div>

      <nav style={S.navList}>
        {navItems.map(item => (
          <button key={item.id} type="button" style={S.navButton(theme, activeSection === item.id)} onClick={() => onNavigate(item.id)}>
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
}
