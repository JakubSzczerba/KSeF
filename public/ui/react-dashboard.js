import React, { useEffect, useState } from "https://esm.sh/react@18.2.0";
import { createRoot } from "https://esm.sh/react-dom@18.2.0/client";
import htm from "https://esm.sh/htm@3.1.1";

const html = htm.bind(React.createElement);
const rootElement = document.getElementById("ksef-react-root");

if (!rootElement) {
    throw new Error("Brak elementu #ksef-react-root");
}

const bootstrap = JSON.parse(rootElement.dataset.bootstrap || "{}");
const initialRows = Array.isArray(bootstrap.rows) ? bootstrap.rows : [];
const sendEndpoint = typeof bootstrap.sendEndpoint === "string" ? bootstrap.sendEndpoint : "/send";
const rowsEndpoint = typeof bootstrap.rowsEndpoint === "string" ? bootstrap.rowsEndpoint : "/invoices/rows";
const downloadInvoiceEndpointTemplate = typeof bootstrap.downloadInvoiceEndpointTemplate === "string"
    ? bootstrap.downloadInvoiceEndpointTemplate
    : "/invoices/download/__KSEF_NUMBER__";
const downloadInvoicePdfEndpointTemplate = typeof bootstrap.downloadInvoicePdfEndpointTemplate === "string"
    ? bootstrap.downloadInvoicePdfEndpointTemplate
    : "/invoices/download/__KSEF_NUMBER__/pdf";

const navigationItems = [
    {
        id: "start",
        label: "Start",
        description: "Pulpit i szybki przeglad aktywnosci",
        tag: "LIVE",
    },
    {
        id: "invoices",
        label: "Faktury",
        description: "Lista wysylek, statusy i pliki",
        tag: "LIVE",
    },
    {
        id: "contractors",
        label: "Kontrahenci",
        description: "Baza kontrahentow i dane do faktur",
        tag: "WIP",
    },
    {
        id: "reports",
        label: "Raporty",
        description: "Podsumowania i wykresy finansowe",
        tag: "WIP",
    },
    {
        id: "settings",
        label: "Ustawienia",
        description: "Konfiguracja firmy i KSeF",
        tag: "WIP",
    },
];

const getSystemTheme = () => (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");

const formatMonthToken = (value) => {
    if (typeof value !== "string" || value.length < 7) {
        return "";
    }

    return value.slice(0, 7);
};

function App() {
    const [rows, setRows] = useState(initialRows);
    const [theme, setTheme] = useState(localStorage.getItem("ksef-ui-theme") || getSystemTheme());
    const [message, setMessage] = useState("");
    const [messageType, setMessageType] = useState("ok");
    const [isNarrow, setIsNarrow] = useState(window.innerWidth < 980);
    const [isBusy, setIsBusy] = useState(false);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [activeSection, setActiveSection] = useState("start");
    const [searchText, setSearchText] = useState("");
    const [form, setForm] = useState({
        xmlText: "",
        file: null,
        systemCode: "FA (3)",
        schemaVersion: "1-0E",
        formValue: "FA",
        offlineMode: false
    });

    useEffect(() => {
        document.body.setAttribute("data-theme", theme);
        localStorage.setItem("ksef-ui-theme", theme);
    }, [theme]);

    useEffect(() => {
        const onResize = () => {
            const narrow = window.innerWidth < 980;
            setIsNarrow(narrow);
            if (!narrow) {
                setIsDrawerOpen(false);
            }
        };

        window.addEventListener("resize", onResize);

        return () => window.removeEventListener("resize", onResize);
    }, []);

    useEffect(() => {
        const onKeyDown = (event) => {
            if (event.key === "Escape") {
                setIsDrawerOpen(false);
                setIsModalOpen(false);
            }
        };

        window.addEventListener("keydown", onKeyDown);

        return () => window.removeEventListener("keydown", onKeyDown);
    }, []);

    const normalizedSearch = searchText.trim().toLowerCase();
    const filteredRows = rows.filter((row) => {
        if (normalizedSearch === "") {
            return true;
        }

        const searchable = [
            row.invoiceNumber,
            row.ksefNumber,
            row.sessionReferenceNumber,
            row.invoiceReferenceNumber,
            row.invoiceStatusDescription,
            row.submittedAt,
        ]
            .filter((value) => typeof value === "string")
            .join(" ")
            .toLowerCase();

        return searchable.includes(normalizedSearch);
    });

    const currentMonthToken = new Date().toISOString().slice(0, 7);
    const processedRows = rows.filter((row) => row.invoiceStatusCode === 200).length;
    const pendingRows = rows.filter((row) => row.invoiceStatusCode !== null && row.invoiceStatusCode !== 200).length;
    const bufferedRows = rows.filter((row) => !row.ksefNumber || row.ksefNumber === "n/d").length;
    const currentMonthRows = rows.filter((row) => formatMonthToken(row.submittedAt) === currentMonthToken).length;
    const latestRows = filteredRows.slice(0, 5);
    const activeNavigationItem = navigationItems.find((item) => item.id === activeSection) || navigationItems[0];

    const setAlert = (text, type = "ok") => {
        setMessage(text);
        setMessageType(type);
    };

    const openSection = (sectionId) => {
        setActiveSection(sectionId);
        setIsDrawerOpen(false);
    };

    const resolveRowKey = (row) => `${row.sessionReferenceNumber}-${row.invoiceReferenceNumber}`;

    const loadRows = async () => {
        const response = await fetch(rowsEndpoint, {
            method: "GET",
            headers: { "X-Requested-With": "XMLHttpRequest" }
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || "Nie udało sie pobrac listy faktur.");
        }

        const nextRows = Array.isArray(payload.rows) ? payload.rows : [];
        setRows(nextRows);

        return nextRows;
    };

    const refreshRows = async () => {
        setIsBusy(true);
        try {
            await loadRows();
            setAlert("Lista faktur zostala odswiezona.", "ok");
        } catch (error) {
            setAlert(error instanceof Error ? error.message : "Wystapil nieznany blad.", "error");
        } finally {
            setIsBusy(false);
        }
    };

    const submitInvoice = async (event) => {
        event.preventDefault();
        setIsBusy(true);

        try {
            const body = new FormData();
            body.set("xml_text", form.xmlText);
            body.set("system_code", form.systemCode);
            body.set("schema_version", form.schemaVersion);
            body.set("form_value", form.formValue);
            if (form.offlineMode) {
                body.set("offline_mode", "1");
            }
            if (form.file instanceof File) {
                body.set("xml_file", form.file);
            }

            const response = await fetch(sendEndpoint, {
                method: "POST",
                body,
                headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || "Nie udalo sie wyslac faktury.");
            }

            if (Array.isArray(payload.rows)) {
                setRows(payload.rows);
            } else {
                await loadRows();
            }
            setAlert(payload.message || "Wyslano fakture.", "ok");
            setIsModalOpen(false);
            setForm((prev) => ({ ...prev, xmlText: "", file: null, offlineMode: false }));
        } catch (error) {
            setAlert(error instanceof Error ? error.message : "Wystapil nieznany blad.", "error");
        } finally {
            setIsBusy(false);
        }
    };

    const resolveDownloadEndpoint = (ksefNumber) => downloadInvoiceEndpointTemplate.replace("__KSEF_NUMBER__", encodeURIComponent(ksefNumber));
    const resolvePdfDownloadEndpoint = (ksefNumber) => downloadInvoicePdfEndpointTemplate.replace("__KSEF_NUMBER__", encodeURIComponent(ksefNumber));

    const downloadInvoice = async (ksefNumber, endpointResolver, extension) => {
        if (!ksefNumber || ksefNumber === "n/d") {
            setAlert("Brak numeru KSeF dla tej faktury.", "error");
            return;
        }

        setIsBusy(true);
        try {
            const response = await fetch(endpointResolver(ksefNumber), {
                method: "GET",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            });

            if (!response.ok) {
                const contentType = response.headers.get("content-type") || "";
                if (contentType.includes("application/json")) {
                    const payload = await response.json();
                    throw new Error(payload.message || "Nie udalo sie pobrac faktury.");
                }

                throw new Error(await response.text() || "Nie udalo sie pobrac faktury.");
            }

            const blob = await response.blob();
            const blobUrl = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = blobUrl;
            link.download = `${ksefNumber}.${extension}`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(blobUrl);

            setAlert(`Pobrano fakture ${ksefNumber} (${extension.toUpperCase()}).`, "ok");
        } catch (error) {
            setAlert(error instanceof Error ? error.message : "Wystapil nieznany blad.", "error");
        } finally {
            setIsBusy(false);
        }
    };

    const renderSidebar = (drawerMode) => html`
        <aside style=${styles.sidebar(theme, drawerMode)}>
            <div style=${styles.sidebarTop}>
                <div style=${styles.brandMark}>KS</div>
                <div>
                    <div style=${styles.brandTitle}>KSeF Workspace</div>
                    <div style=${styles.brandSubtitle(theme)}>Panel operacyjny dla wysylek i obiegu faktur.</div>
                </div>
            </div>

            <nav style=${styles.navList}>
                ${navigationItems.map((item) => html`
                    <button
                        key=${item.id}
                        type="button"
                        style=${styles.navButton(theme, activeSection === item.id)}
                        onClick=${() => openSection(item.id)}
                    >
                        <div>
                            <div style=${styles.navLabel}>${item.label}</div>
                            <div style=${styles.navDescription(theme)}>${item.description}</div>
                        </div>
                        <span style=${styles.navTag(item.tag)}>${item.tag}</span>
                    </button>
                `)}
            </nav>

            <div style=${styles.sidebarFooter(theme)}>
                <div style=${styles.sidebarFooterTitle}>Stan layoutu</div>
                <div style=${styles.sidebarFooterText(theme)}>
                    Domkniety shell aplikacji: sidebar, topbar, breadcrumb i mobilny drawer.
                </div>
            </div>
        </aside>
    `;

    const renderOverviewCard = (label, value, note, accent) => html`
        <div key=${label} style=${styles.metricCard(theme, accent)}>
            <div style=${styles.metricLabel(theme)}>${label}</div>
            <div style=${styles.metricValue}>${value}</div>
            <div style=${styles.metricNote(theme)}>${note}</div>
        </div>
    `;

    const renderStartSection = () => html`
        <section style=${styles.sectionStack}>
            <div style=${styles.hero(theme)}>
                <div>
                    <div style=${styles.eyebrow(theme)}>Start</div>
                    <h1 style=${styles.heroTitle}>Operacyjny pulpit KSeF z gotowa nawigacja aplikacji.</h1>
                    <p style=${styles.heroText(theme)}>
                        Widok startowy spina wysylke, monitoring statusow i przejscia do kolejnych modulow:
                        kontrahentow, raportow oraz ustawien.
                    </p>
                </div>
                <div style=${styles.heroActions(isNarrow)}>
                    <button style=${styles.button.secondary(theme)} type="button" onClick=${refreshRows} disabled=${isBusy}>Odswiez dane</button>
                    <button style=${styles.button.primary} type="button" onClick=${() => setIsModalOpen(true)}>Wyslij fakture</button>
                </div>
            </div>

            <div style=${styles.metricsGrid}>
                ${renderOverviewCard("Wszystkie rekordy", rows.length, "Laczna liczba pozycji dostepnych w dashboardzie.", "blue")}
                ${renderOverviewCard("Przetworzone", processedRows, "Faktury z terminalnym kodem 200.", "green")}
                ${renderOverviewCard("W buforze", bufferedRows, "Pozycje bez numeru KSeF lub jeszcze niezsynchronizowane.", "amber")}
                ${renderOverviewCard("Biezacy miesiac", currentMonthRows, "Wysylki zarejestrowane w aktualnym miesiacu.", "orange")}
            </div>

            <div style=${styles.contentGrid(isNarrow)}>
                <section style=${styles.panel(theme)}>
                    <div style=${styles.panelHeader}>
                        <div>
                            <div style=${styles.panelTitle}>Ostatnia aktywnosc</div>
                            <div style=${styles.panelMeta(theme)}>Ostatnie 5 pozycji po uwzglednieniu filtra z topbara.</div>
                        </div>
                        <button style=${styles.button.ghost(theme)} type="button" onClick=${() => openSection("invoices")}>Przejdz do faktur</button>
                    </div>

                    <div style=${styles.activityList}>
                        ${latestRows.length === 0
                            ? html`<div style=${styles.emptyState(theme)}>Brak pozycji do pokazania w widoku startowym.</div>`
                            : latestRows.map((row) => html`
                                <div key=${resolveRowKey(row)} style=${styles.activityRow(theme)}>
                                    <div>
                                        <div style=${styles.activityPrimary}>${row.invoiceNumber || row.ksefNumber || "Bez numeru"}</div>
                                        <div style=${styles.activitySecondary(theme)}>
                                            ${row.submittedAt || "n/d"} · ${row.invoiceStatusDescription || "Brak opisu"}
                                        </div>
                                    </div>
                                    <span style=${styles.statusBadge(row.invoiceStatusCode)}>${row.invoiceStatusCode ?? "n/d"}</span>
                                </div>
                            `)}
                    </div>
                </section>

                <section style=${styles.panel(theme)}>
                    <div style=${styles.panelHeader}>
                        <div>
                            <div style=${styles.panelTitle}>Moduly w nawigacji</div>
                            <div style=${styles.panelMeta(theme)}>Układ jest gotowy na kolejne fazy roadmapy.</div>
                        </div>
                    </div>

                    <div style=${styles.moduleList}>
                        ${navigationItems.map((item) => html`
                            <div key=${item.id} style=${styles.moduleRow(theme)}>
                                <div>
                                    <div style=${styles.activityPrimary}>${item.label}</div>
                                    <div style=${styles.activitySecondary(theme)}>${item.description}</div>
                                </div>
                                <span style=${styles.navTag(item.tag)}>${item.tag}</span>
                            </div>
                        `)}
                    </div>
                </section>
            </div>
        </section>
    `;

    const renderInvoicesSection = () => html`
        <section style=${styles.sectionStack}>
            <div style=${styles.sectionIntro}>
                <div>
                    <div style=${styles.eyebrow(theme)}>Faktury</div>
                    <h1 style=${styles.sectionTitle}>Lista wysylek osadzona w docelowym layoucie dashboardu.</h1>
                    <p style=${styles.sectionText(theme)}>
                        Tabela zachowuje obecne akcje, a wyszukiwarka z topbara filtruje rekordy po numerach,
                        referencjach i opisie statusu.
                    </p>
                </div>
                <div style=${styles.sectionActions(isNarrow)}>
                    <div style=${styles.inlineMetric(theme)}>
                        <strong>${filteredRows.length}</strong>
                        <span>rekordow po filtrze</span>
                    </div>
                    <div style=${styles.inlineMetric(theme)}>
                        <strong>${pendingRows}</strong>
                        <span>pozycji z innym kodem niz 200</span>
                    </div>
                </div>
            </div>

            <section style=${styles.tableCard(theme)}>
                <div style=${styles.tableHeaderBar(isNarrow)}>
                    <div>
                        <div style=${styles.tableTitle}>Lista faktur z KSeF</div>
                        <div style=${styles.panelMeta(theme)}>
                            Aktywny filtr: ${normalizedSearch === "" ? "brak" : normalizedSearch}
                        </div>
                    </div>
                    <div style=${styles.tableActions(isNarrow)}>
                        <button style=${styles.button.secondary(theme)} type="button" onClick=${refreshRows} disabled=${isBusy}>Odswiez</button>
                        <button style=${styles.button.primary} type="button" onClick=${() => setIsModalOpen(true)}>Nowa wysylka</button>
                    </div>
                </div>
                <div style=${styles.tableWrap}>
                    <table style=${styles.table}>
                        <thead>
                            <tr>
                                <th style=${styles.th(theme)}>Data / Numer</th>
                                <th style=${styles.th(theme)}>KSeF Number</th>
                                <th style=${styles.th(theme)}>Session Reference</th>
                                <th style=${styles.th(theme)}>Invoice Reference</th>
                                <th style=${styles.th(theme)}>Opis</th>
                                <th style=${styles.th(theme)}>Status Faktury</th>
                                <th style=${styles.th(theme)}>Status Sesji</th>
                                <th style=${styles.th(theme)}>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${filteredRows.length === 0
                                ? html`<tr><td style=${styles.td(theme)} colSpan="8">Brak faktur dla aktualnego filtra.</td></tr>`
                                : filteredRows.map((row) => html`
                                    <tr key=${resolveRowKey(row)}>
                                        <td style=${styles.td(theme)}>
                                            <strong>${row.submittedAt || "n/d"}</strong>
                                            <br />
                                            <small style=${styles.small(theme)}>${row.invoiceNumber || "n/d"}</small>
                                        </td>
                                        <td style=${styles.td(theme)}>${row.ksefNumber || "n/d"}</td>
                                        <td style=${styles.td(theme)}>${row.sessionReferenceNumber || "n/d"}</td>
                                        <td style=${styles.td(theme)}>${row.invoiceReferenceNumber || "n/d"}</td>
                                        <td style=${styles.td(theme)}>${row.invoiceStatusDescription || "n/d"}</td>
                                        <td style=${styles.td(theme)}><span style=${styles.statusBadge(row.invoiceStatusCode)}>${row.invoiceStatusCode ?? "n/d"}</span></td>
                                        <td style=${styles.td(theme)}>${row.sessionStatusCode ?? "n/d"}</td>
                                        <td style=${styles.td(theme)}>
                                            <div style=${styles.actionRow}>
                                                <button
                                                    style=${styles.button.xml(theme)}
                                                    type="button"
                                                    disabled=${isBusy || !row.ksefNumber || row.ksefNumber === "n/d"}
                                                    onClick=${() => downloadInvoice(row.ksefNumber, resolveDownloadEndpoint, "xml")}
                                                >
                                                    XML
                                                </button>
                                                <button
                                                    style=${styles.button.pdf}
                                                    type="button"
                                                    disabled=${isBusy || !row.ksefNumber || row.ksefNumber === "n/d"}
                                                    onClick=${() => downloadInvoice(row.ksefNumber, resolvePdfDownloadEndpoint, "pdf")}
                                                >
                                                    PDF
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                `)}
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    `;

    const renderPlaceholderSection = (title, text) => html`
        <section style=${styles.sectionStack}>
            <div style=${styles.sectionIntro}>
                <div>
                    <div style=${styles.eyebrow(theme)}>${activeNavigationItem.label}</div>
                    <h1 style=${styles.sectionTitle}>${title}</h1>
                    <p style=${styles.sectionText(theme)}>${text}</p>
                </div>
            </div>

            <section style=${styles.placeholderCard(theme)}>
                <div style=${styles.placeholderTitle}>Sekcja w przygotowaniu</div>
                <div style=${styles.placeholderText(theme)}>
                    Layout jest juz gotowy. Kolejna implementacja moze wejsc w ten modul bez przebudowy shellu
                    aplikacji ani nawigacji mobilnej.
                </div>
                <div style=${styles.placeholderActions(isNarrow)}>
                    <button style=${styles.button.secondary(theme)} type="button" onClick=${() => openSection("start")}>Wroc do Start</button>
                    <button style=${styles.button.ghost(theme)} type="button" onClick=${() => openSection("invoices")}>Przejdz do faktur</button>
                </div>
            </section>
        </section>
    `;

    const renderActiveSection = () => {
        if (activeSection === "start") {
            return renderStartSection();
        }

        if (activeSection === "invoices") {
            return renderInvoicesSection();
        }

        if (activeSection === "contractors") {
            return renderPlaceholderSection(
                "Modul kontrahentow czeka na warstwe danych.",
                "To naturalne miejsce na CRUD kontrahentow, autocomplete po NIP i historie rozliczen z roadmapy."
            );
        }

        if (activeSection === "reports") {
            return renderPlaceholderSection(
                "Raporty dostaly juz miejsce w glownej nawigacji.",
                "Po dodaniu statusow platnosci i danych z bazy tu trafi widok finansowy z wykresami i eksportem."
            );
        }

        return renderPlaceholderSection(
            "Ustawienia sa gotowe na osobny modul konfiguracyjny.",
            "Widok bedzie mogl przechowywac dane firmy, konfiguracje KSeF i ustawienia numeracji bez zmian w layoucie."
        );
    };

    return html`
        <div style=${styles.appFrame(isNarrow)}>
            ${!isNarrow ? renderSidebar(false) : null}

            ${isNarrow && isDrawerOpen ? html`
                <div style=${styles.drawerOverlay} onClick=${() => setIsDrawerOpen(false)}>
                    <div style=${styles.drawerWrap} onClick=${(event) => event.stopPropagation()}>
                        ${renderSidebar(true)}
                    </div>
                </div>
            ` : null}

            <main style=${styles.contentShell}>
                <header style=${styles.topBar(theme, isNarrow)}>
                    <div style=${styles.topLeft(isNarrow)}>
                        ${isNarrow ? html`
                            <button style=${styles.iconButton(theme)} type="button" onClick=${() => setIsDrawerOpen(true)}>Menu</button>
                        ` : null}
                        <div>
                            <div style=${styles.topTitle}>${activeNavigationItem.label}</div>
                            <div style=${styles.topSubtitle(theme)}>${activeNavigationItem.description}</div>
                        </div>
                    </div>

                    <div style=${styles.topRight(isNarrow)}>
                        <label style=${styles.searchField(theme, isNarrow)}>
                            <span style=${styles.searchLabel(theme)}>Szukaj</span>
                            <input
                                style=${styles.searchInput}
                                value=${searchText}
                                onChange=${(event) => setSearchText(event.target.value)}
                                placeholder="Numer faktury, KSeF lub referencja"
                            />
                        </label>
                        <button style=${styles.button.secondary(theme)} type="button" onClick=${() => setTheme(theme === "light" ? "dark" : "light")}>
                            ${theme === "light" ? "Tryb ciemny" : "Tryb jasny"}
                        </button>
                        <div style=${styles.avatar(theme)}>
                            <div style=${styles.avatarBadge}>JS</div>
                            <div>
                                <div style=${styles.avatarName}>Jakub Szczerba</div>
                                <div style=${styles.avatarRole(theme)}>Owner</div>
                            </div>
                        </div>
                    </div>
                </header>

                <div style=${styles.breadcrumbs(theme)}>
                    <span style=${styles.breadcrumbMuted(theme)}>Dashboard</span>
                    <span>/</span>
                    <strong>${activeNavigationItem.label}</strong>
                </div>

                ${message ? html`<div style=${styles.toast(messageType)}>${message}</div>` : null}

                ${renderActiveSection()}
            </main>

            ${isModalOpen ? html`
                <div style=${styles.overlay} onClick=${() => !isBusy && setIsModalOpen(false)}>
                    <div style=${styles.modal(theme, isNarrow)} onClick=${(event) => event.stopPropagation()}>
                        <div style=${styles.modalHeader}>
                            <div>
                                <div style=${styles.eyebrow(theme)}>Nowa wysylka</div>
                                <h2 style=${styles.modalTitle}>Wysylka faktury do KSeF</h2>
                            </div>
                            <button style=${styles.iconButton(theme)} type="button" onClick=${() => setIsModalOpen(false)} disabled=${isBusy}>Zamknij</button>
                        </div>
                        <form style=${styles.form} onSubmit=${submitInvoice}>
                            <label style=${styles.label}>Plik XML</label>
                            <input
                                style=${styles.input(theme)}
                                type="file"
                                accept=".xml,text/xml,application/xml"
                                onChange=${(event) => setForm((prev) => ({ ...prev, file: event.target.files?.[0] ?? null }))}
                            />

                            <label style=${styles.label}>Tresc XML</label>
                            <textarea
                                style=${styles.textarea(theme)}
                                value=${form.xmlText}
                                onChange=${(event) => setForm((prev) => ({ ...prev, xmlText: event.target.value }))}
                                placeholder="Wklej XML faktury FA(3)"
                            />

                            <div style=${styles.row(isNarrow)}>
                                <div>
                                    <label style=${styles.label}>FormCode.systemCode</label>
                                    <input style=${styles.input(theme)} value=${form.systemCode} onChange=${(event) => setForm((prev) => ({ ...prev, systemCode: event.target.value }))} />
                                </div>
                                <div>
                                    <label style=${styles.label}>FormCode.schemaVersion</label>
                                    <input style=${styles.input(theme)} value=${form.schemaVersion} onChange=${(event) => setForm((prev) => ({ ...prev, schemaVersion: event.target.value }))} />
                                </div>
                                <div>
                                    <label style=${styles.label}>FormCode.value</label>
                                    <input style=${styles.input(theme)} value=${form.formValue} onChange=${(event) => setForm((prev) => ({ ...prev, formValue: event.target.value }))} />
                                </div>
                            </div>

                            <label style=${styles.check}>
                                <input type="checkbox" checked=${form.offlineMode} onChange=${(event) => setForm((prev) => ({ ...prev, offlineMode: event.target.checked }))} />
                                Uzyj offlineMode
                            </label>

                            <div style=${styles.modalActions}>
                                <button style=${styles.button.ghost(theme)} type="button" onClick=${() => setIsModalOpen(false)} disabled=${isBusy}>Anuluj</button>
                                <button style=${styles.button.primary} type="submit" disabled=${isBusy}>${isBusy ? "Wysylanie..." : "Wyslij"}</button>
                            </div>
                        </form>
                    </div>
                </div>
            ` : null}
        </div>
    `;
}

const styles = {
    appFrame: (isNarrow) => ({
        minHeight: "100vh",
        width: "min(1500px, calc(100% - 1.4rem))",
        margin: "0 auto",
        padding: isNarrow ? "0.7rem 0 1rem" : "1rem 0 1.2rem",
        display: "grid",
        gridTemplateColumns: isNarrow ? "1fr" : "280px minmax(0, 1fr)",
        gap: "0.9rem"
    }),
    sidebar: (theme, drawerMode) => ({
        borderRadius: "24px",
        padding: "1rem",
        display: "grid",
        gap: "1rem",
        alignContent: "start",
        minHeight: drawerMode ? "100%" : "calc(100vh - 2rem)",
        background: theme === "light"
            ? "linear-gradient(180deg, rgba(255,255,255,0.95), rgba(241,247,255,0.94))"
            : "linear-gradient(180deg, rgba(12,19,37,0.98), rgba(16,27,49,0.95))",
        border: theme === "light" ? "1px solid #dce7f3" : "1px solid #304566",
        boxShadow: "0 22px 44px rgba(6, 12, 29, 0.22)"
    }),
    sidebarTop: {
        display: "grid",
        gridTemplateColumns: "56px 1fr",
        gap: "0.75rem",
        alignItems: "center"
    },
    brandMark: {
        width: "56px",
        height: "56px",
        borderRadius: "18px",
        display: "grid",
        placeItems: "center",
        fontFamily: "Sora, sans-serif",
        fontWeight: 800,
        color: "#fff",
        background: "linear-gradient(135deg, #ff7a2c, #ffc04c)"
    },
    brandTitle: {
        fontFamily: "Sora, sans-serif",
        fontWeight: 700,
        fontSize: "1rem"
    },
    brandSubtitle: (theme) => ({
        marginTop: "0.2rem",
        color: theme === "light" ? "#4a6484" : "#95adcf",
        fontSize: "0.82rem",
        lineHeight: 1.5
    }),
    navList: {
        display: "grid",
        gap: "0.5rem"
    },
    navButton: (theme, isActive) => ({
        width: "100%",
        border: isActive ? "1px solid rgba(255, 135, 42, 0.55)" : theme === "light" ? "1px solid #dde8f3" : "1px solid #2f466a",
        borderRadius: "18px",
        padding: "0.8rem 0.85rem",
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: "0.8rem",
        textAlign: "left",
        cursor: "pointer",
        color: theme === "light" ? "#11243a" : "#dbe7f9",
        background: isActive
            ? theme === "light"
                ? "linear-gradient(135deg, rgba(255, 233, 210, 0.95), rgba(255,255,255,0.96))"
                : "linear-gradient(135deg, rgba(72, 47, 27, 0.85), rgba(30,45,72,0.96))"
            : theme === "light"
                ? "rgba(255,255,255,0.72)"
                : "rgba(15, 27, 49, 0.7)"
    }),
    navLabel: {
        fontWeight: 800,
        fontSize: "0.95rem"
    },
    navDescription: (theme) => ({
        marginTop: "0.2rem",
        color: theme === "light" ? "#5b7492" : "#93aace",
        fontSize: "0.77rem",
        lineHeight: 1.45
    }),
    navTag: (tag) => ({
        borderRadius: "999px",
        padding: "0.22rem 0.48rem",
        fontSize: "0.68rem",
        fontWeight: 800,
        color: tag === "LIVE" ? "#14532d" : "#92400e",
        background: tag === "LIVE" ? "#dcfce7" : "#fff1d6"
    }),
    sidebarFooter: (theme) => ({
        marginTop: "auto",
        borderRadius: "18px",
        padding: "0.9rem",
        background: theme === "light" ? "#eff5fc" : "#162640",
        border: theme === "light" ? "1px solid #d9e5f1" : "1px solid #304566"
    }),
    sidebarFooterTitle: {
        fontFamily: "Sora, sans-serif",
        fontWeight: 700,
        fontSize: "0.82rem"
    },
    sidebarFooterText: (theme) => ({
        marginTop: "0.3rem",
        color: theme === "light" ? "#4c6787" : "#93aace",
        fontSize: "0.76rem",
        lineHeight: 1.5
    }),
    drawerOverlay: {
        position: "fixed",
        inset: 0,
        zIndex: 900,
        background: "rgba(4, 9, 22, 0.58)",
        padding: "0.7rem",
        backdropFilter: "blur(3px)"
    },
    drawerWrap: {
        width: "min(340px, 92vw)",
        height: "100%"
    },
    contentShell: {
        display: "grid",
        gap: "0.8rem",
        alignContent: "start"
    },
    topBar: (theme, isNarrow) => ({
        display: "flex",
        flexDirection: isNarrow ? "column" : "row",
        alignItems: isNarrow ? "stretch" : "center",
        justifyContent: "space-between",
        gap: "0.8rem",
        borderRadius: "24px",
        padding: "1rem 1.05rem",
        background: theme === "light"
            ? "linear-gradient(118deg, rgba(255, 153, 71, 0.16), rgba(255,255,255,0.88) 46%, rgba(122, 204, 255, 0.84))"
            : "linear-gradient(118deg, rgba(255, 121, 49, 0.26), rgba(20, 32, 58, 0.92) 45%, rgba(59, 112, 186, 0.84))",
        boxShadow: "0 18px 36px rgba(5, 11, 30, 0.22)",
        border: theme === "light" ? "1px solid #e7eef8" : "1px solid #2e4062"
    }),
    topLeft: (isNarrow) => ({
        display: "flex",
        alignItems: isNarrow ? "flex-start" : "center",
        gap: "0.75rem"
    }),
    topTitle: {
        margin: 0,
        fontFamily: "Sora, sans-serif",
        fontSize: "clamp(1.1rem, 2.2vw, 1.45rem)",
        fontWeight: 700
    },
    topSubtitle: (theme) => ({
        marginTop: "0.18rem",
        color: theme === "light" ? "#385372" : "#b5cae8",
        fontSize: "0.84rem",
        fontWeight: 700
    }),
    topRight: (isNarrow) => ({
        display: "flex",
        flexDirection: isNarrow ? "column" : "row",
        alignItems: isNarrow ? "stretch" : "center",
        gap: "0.55rem"
    }),
    searchField: (theme, isNarrow) => ({
        minWidth: isNarrow ? "100%" : "320px",
        borderRadius: "16px",
        padding: "0.45rem 0.7rem",
        display: "grid",
        gap: "0.12rem",
        background: theme === "light" ? "rgba(255,255,255,0.72)" : "rgba(11, 21, 40, 0.68)",
        border: theme === "light" ? "1px solid rgba(206, 221, 238, 0.95)" : "1px solid rgba(61, 86, 122, 0.92)"
    }),
    searchLabel: (theme) => ({
        fontSize: "0.68rem",
        fontWeight: 800,
        color: theme === "light" ? "#58708f" : "#a4badb",
        textTransform: "uppercase",
        letterSpacing: "0.08em"
    }),
    searchInput: {
        border: 0,
        outline: "none",
        background: "transparent",
        color: "inherit",
        font: "inherit",
        padding: 0
    },
    avatar: (theme) => ({
        borderRadius: "16px",
        padding: "0.42rem 0.55rem",
        display: "flex",
        gap: "0.55rem",
        alignItems: "center",
        background: theme === "light" ? "rgba(255,255,255,0.7)" : "rgba(11, 21, 40, 0.64)",
        border: theme === "light" ? "1px solid rgba(206, 221, 238, 0.95)" : "1px solid rgba(61, 86, 122, 0.92)"
    }),
    avatarBadge: {
        width: "36px",
        height: "36px",
        borderRadius: "12px",
        display: "grid",
        placeItems: "center",
        fontFamily: "Sora, sans-serif",
        fontWeight: 800,
        color: "#fff",
        background: "linear-gradient(135deg, #ff7a2c, #ffbd44)"
    },
    avatarName: {
        fontWeight: 800,
        fontSize: "0.85rem"
    },
    avatarRole: (theme) => ({
        color: theme === "light" ? "#5a7392" : "#9cb3d5",
        fontSize: "0.73rem"
    }),
    breadcrumbs: (theme) => ({
        display: "flex",
        alignItems: "center",
        gap: "0.42rem",
        padding: "0 0.2rem",
        color: theme === "light" ? "#506a89" : "#a9beda",
        fontSize: "0.8rem",
        fontWeight: 700
    }),
    breadcrumbMuted: (theme) => ({
        color: theme === "light" ? "#7188a3" : "#89a0c1"
    }),
    sectionStack: {
        display: "grid",
        gap: "0.8rem"
    },
    hero: (theme) => ({
        borderRadius: "24px",
        padding: "1.15rem",
        display: "grid",
        gap: "0.9rem",
        background: theme === "light"
            ? "linear-gradient(145deg, rgba(255,255,255,0.92), rgba(239,247,255,0.96))"
            : "linear-gradient(145deg, rgba(14,22,41,0.96), rgba(17,28,52,0.96))",
        border: theme === "light" ? "1px solid #dfe9f4" : "1px solid #2c4164",
        boxShadow: "0 18px 40px rgba(4, 10, 28, 0.18)"
    }),
    eyebrow: (theme) => ({
        display: "inline-block",
        marginBottom: "0.45rem",
        borderRadius: "999px",
        padding: "0.22rem 0.5rem",
        fontSize: "0.72rem",
        fontWeight: 800,
        letterSpacing: "0.08em",
        textTransform: "uppercase",
        color: theme === "light" ? "#8a3a0a" : "#ffd9c4",
        background: theme === "light" ? "#fff0e7" : "rgba(255, 139, 72, 0.22)"
    }),
    heroTitle: {
        margin: 0,
        fontFamily: "Sora, sans-serif",
        fontSize: "clamp(1.35rem, 2.8vw, 2.1rem)",
        lineHeight: 1.15
    },
    heroText: (theme) => ({
        margin: "0.55rem 0 0",
        maxWidth: "760px",
        color: theme === "light" ? "#4f6887" : "#9eb6d7",
        fontSize: "0.94rem",
        lineHeight: 1.65
    }),
    heroActions: (isNarrow) => ({
        display: "flex",
        flexDirection: isNarrow ? "column" : "row",
        gap: "0.55rem"
    }),
    metricsGrid: {
        display: "grid",
        gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))",
        gap: "0.8rem"
    },
    metricCard: (theme, accent) => ({
        borderRadius: "20px",
        padding: "1rem",
        background: theme === "light" ? "#f8fbff" : "#111d34",
        border: `1px solid ${accent === "green" ? "#a7d8b7" : accent === "amber" ? "#f0cf99" : accent === "orange" ? "#f2b489" : theme === "light" ? "#dce7f3" : "#304566"}`,
        boxShadow: "0 12px 28px rgba(4, 10, 28, 0.16)"
    }),
    metricLabel: (theme) => ({
        color: theme === "light" ? "#5d7491" : "#93aace",
        fontSize: "0.78rem",
        fontWeight: 700
    }),
    metricValue: {
        marginTop: "0.42rem",
        fontFamily: "Sora, sans-serif",
        fontSize: "1.85rem",
        fontWeight: 700
    },
    metricNote: (theme) => ({
        marginTop: "0.32rem",
        color: theme === "light" ? "#556e8b" : "#91a8ca",
        fontSize: "0.78rem",
        lineHeight: 1.5
    }),
    contentGrid: (isNarrow) => ({
        display: "grid",
        gridTemplateColumns: isNarrow ? "1fr" : "minmax(0, 1.15fr) minmax(280px, 0.85fr)",
        gap: "0.8rem"
    }),
    panel: (theme) => ({
        borderRadius: "22px",
        padding: "1rem",
        background: theme === "light" ? "#f8fbff" : "#101b31",
        border: theme === "light" ? "1px solid #dfe9f4" : "1px solid #2c4164",
        boxShadow: "0 16px 34px rgba(4, 10, 28, 0.18)"
    }),
    panelHeader: {
        display: "flex",
        justifyContent: "space-between",
        alignItems: "flex-start",
        gap: "0.8rem",
        marginBottom: "0.8rem"
    },
    panelTitle: {
        fontFamily: "Sora, sans-serif",
        fontWeight: 700,
        fontSize: "0.95rem"
    },
    panelMeta: (theme) => ({
        marginTop: "0.22rem",
        color: theme === "light" ? "#5d7491" : "#93aace",
        fontSize: "0.78rem"
    }),
    activityList: {
        display: "grid",
        gap: "0.55rem"
    },
    activityRow: (theme) => ({
        borderRadius: "16px",
        padding: "0.8rem",
        display: "flex",
        justifyContent: "space-between",
        alignItems: "center",
        gap: "0.8rem",
        background: theme === "light" ? "#ffffff" : "#13223d",
        border: theme === "light" ? "1px solid #e4edf7" : "1px solid #314a71"
    }),
    activityPrimary: {
        fontWeight: 800,
        fontSize: "0.9rem"
    },
    activitySecondary: (theme) => ({
        marginTop: "0.18rem",
        color: theme === "light" ? "#607894" : "#95adcf",
        fontSize: "0.76rem"
    }),
    moduleList: {
        display: "grid",
        gap: "0.55rem"
    },
    moduleRow: (theme) => ({
        borderRadius: "16px",
        padding: "0.8rem",
        display: "flex",
        justifyContent: "space-between",
        gap: "0.8rem",
        alignItems: "center",
        background: theme === "light" ? "#ffffff" : "#13223d",
        border: theme === "light" ? "1px solid #e4edf7" : "1px solid #314a71"
    }),
    emptyState: (theme) => ({
        borderRadius: "16px",
        padding: "1rem",
        textAlign: "center",
        color: theme === "light" ? "#5f7894" : "#94acce",
        background: theme === "light" ? "#ffffff" : "#13223d",
        border: theme === "light" ? "1px dashed #d7e3f1" : "1px dashed #38537c"
    }),
    sectionIntro: {
        display: "flex",
        justifyContent: "space-between",
        alignItems: "flex-start",
        gap: "0.8rem",
        flexWrap: "wrap"
    },
    sectionTitle: {
        margin: 0,
        fontFamily: "Sora, sans-serif",
        fontWeight: 700,
        fontSize: "clamp(1.2rem, 2vw, 1.55rem)"
    },
    sectionText: (theme) => ({
        margin: "0.42rem 0 0",
        maxWidth: "720px",
        color: theme === "light" ? "#516987" : "#9cb4d5",
        fontSize: "0.9rem",
        lineHeight: 1.6
    }),
    sectionActions: (isNarrow) => ({
        display: "flex",
        flexDirection: isNarrow ? "column" : "row",
        gap: "0.5rem",
        alignItems: isNarrow ? "stretch" : "center"
    }),
    inlineMetric: (theme) => ({
        minWidth: "170px",
        borderRadius: "16px",
        padding: "0.75rem 0.85rem",
        display: "grid",
        gap: "0.14rem",
        background: theme === "light" ? "#f8fbff" : "#101b31",
        border: theme === "light" ? "1px solid #dfe9f4" : "1px solid #2c4164"
    }),
    tableCard: (theme) => ({
        borderRadius: "22px",
        overflow: "hidden",
        background: theme === "light" ? "#f8fbff" : "#0f1a2f",
        border: theme === "light" ? "1px solid #dfe9f4" : "1px solid #293d5f",
        boxShadow: "0 14px 34px rgba(4, 10, 28, 0.24)"
    }),
    tableHeaderBar: (isNarrow) => ({
        padding: "0.95rem 1rem",
        display: "flex",
        flexDirection: isNarrow ? "column" : "row",
        alignItems: isNarrow ? "stretch" : "center",
        justifyContent: "space-between",
        gap: "0.75rem"
    }),
    tableTitle: {
        fontFamily: "Sora, sans-serif",
        fontSize: "0.98rem",
        fontWeight: 700
    },
    tableActions: (isNarrow) => ({
        display: "flex",
        flexDirection: isNarrow ? "column" : "row",
        gap: "0.5rem"
    }),
    tableWrap: {
        overflowX: "auto"
    },
    table: {
        width: "100%",
        minWidth: "1050px",
        borderCollapse: "collapse"
    },
    th: (theme) => ({
        textAlign: "left",
        padding: "0.58rem 0.65rem",
        fontFamily: "Sora, sans-serif",
        fontSize: "0.78rem",
        letterSpacing: "0.01em",
        background: theme === "light" ? "#edf5ff" : "#1a2a47",
        borderBottom: theme === "light" ? "1px solid #d7e3f1" : "1px solid #2f446a"
    }),
    td: (theme) => ({
        textAlign: "left",
        verticalAlign: "top",
        padding: "0.56rem 0.65rem",
        fontSize: "0.84rem",
        borderBottom: theme === "light" ? "1px solid #dbe6f2" : "1px solid #263a59"
    }),
    small: (theme) => ({
        color: theme === "light" ? "#496788" : "#93acd4",
        fontSize: "0.72rem"
    }),
    actionRow: {
        display: "flex",
        flexWrap: "nowrap",
        gap: "0.45rem",
        alignItems: "center"
    },
    statusBadge: (code) => ({
        display: "inline-flex",
        borderRadius: "999px",
        padding: "0.11rem 0.42rem",
        fontWeight: 800,
        fontSize: "0.72rem",
        background: code === 200 ? "#dcfce7" : code == null ? "#e2e8f0" : "#fff4cc",
        color: code === 200 ? "#166534" : code == null ? "#334155" : "#92400e"
    }),
    placeholderCard: (theme) => ({
        borderRadius: "22px",
        padding: "1.1rem",
        display: "grid",
        gap: "0.8rem",
        background: theme === "light" ? "#f8fbff" : "#101b31",
        border: theme === "light" ? "1px solid #dfe9f4" : "1px solid #2c4164",
        boxShadow: "0 16px 34px rgba(4, 10, 28, 0.18)"
    }),
    placeholderTitle: {
        fontFamily: "Sora, sans-serif",
        fontWeight: 700,
        fontSize: "1.02rem"
    },
    placeholderText: (theme) => ({
        color: theme === "light" ? "#516987" : "#9cb4d5",
        lineHeight: 1.65,
        fontSize: "0.9rem"
    }),
    placeholderActions: (isNarrow) => ({
        display: "flex",
        flexDirection: isNarrow ? "column" : "row",
        gap: "0.5rem"
    }),
    toast: (type) => ({
        borderRadius: "14px",
        padding: "0.75rem 0.85rem",
        fontWeight: 700,
        fontSize: "0.88rem",
        background: type === "error" ? "#ffe5e5" : "#dcfce7",
        color: type === "error" ? "#7f1d1d" : "#14532d"
    }),
    overlay: {
        position: "fixed",
        inset: 0,
        background: "rgba(7, 12, 28, 0.6)",
        display: "grid",
        placeItems: "center",
        padding: "1rem",
        zIndex: 1000,
        backdropFilter: "blur(3px)"
    },
    modal: (theme, isNarrow) => ({
        width: isNarrow ? "100%" : "min(920px, 100%)",
        borderRadius: "20px",
        padding: "1rem",
        background: theme === "light" ? "#f9fcff" : "#111d34",
        border: theme === "light" ? "1px solid #dbe8f5" : "1px solid #334c74",
        boxShadow: "0 28px 56px rgba(2, 8, 22, 0.45)"
    }),
    modalHeader: {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: "0.8rem",
        marginBottom: "0.7rem"
    },
    modalTitle: {
        margin: "0.1rem 0 0",
        fontFamily: "Sora, sans-serif",
        fontSize: "1rem"
    },
    iconButton: (theme) => ({
        border: 0,
        borderRadius: "12px",
        padding: "0.5rem 0.72rem",
        fontWeight: 700,
        cursor: "pointer",
        color: theme === "light" ? "#132235" : "#dbe7f9",
        background: theme === "light" ? "#dceaf9" : "#24395a"
    }),
    form: {
        display: "grid",
        gap: "0.42rem"
    },
    label: {
        fontSize: "0.79rem",
        fontWeight: 700,
        opacity: 0.9
    },
    input: (theme) => ({
        borderRadius: "12px",
        border: theme === "light" ? "1px solid #cddced" : "1px solid #3c557d",
        background: theme === "light" ? "#fff" : "#0d172b",
        color: theme === "light" ? "#132235" : "#dbe7f9",
        padding: "0.56rem 0.65rem",
        font: "inherit"
    }),
    textarea: (theme) => ({
        borderRadius: "12px",
        border: theme === "light" ? "1px solid #cddced" : "1px solid #3c557d",
        background: theme === "light" ? "#fff" : "#0d172b",
        color: theme === "light" ? "#132235" : "#dbe7f9",
        padding: "0.56rem 0.65rem",
        font: "inherit",
        minHeight: "150px",
        resize: "vertical"
    }),
    row: (isNarrow) => ({
        display: "grid",
        gridTemplateColumns: isNarrow ? "1fr" : "1fr 1fr 1fr",
        gap: "0.5rem"
    }),
    check: {
        display: "flex",
        gap: "0.45rem",
        alignItems: "center",
        marginTop: "0.2rem"
    },
    modalActions: {
        display: "flex",
        justifyContent: "flex-end",
        gap: "0.5rem",
        marginTop: "0.5rem"
    },
    button: {
        primary: {
            border: 0,
            borderRadius: "12px",
            padding: "0.58rem 0.9rem",
            fontWeight: 800,
            cursor: "pointer",
            color: "#fff",
            background: "linear-gradient(95deg, #ff7b2f, #ffbf34)"
        },
        secondary: (theme) => ({
            border: 0,
            borderRadius: "12px",
            padding: "0.58rem 0.9rem",
            fontWeight: 700,
            cursor: "pointer",
            color: theme === "light" ? "#14253b" : "#dbe7f9",
            background: theme === "light" ? "#ddebf9" : "#223655"
        }),
        xml: (theme) => ({
            border: theme === "light" ? "1px solid #b6cbe2" : "1px solid #3d567a",
            borderRadius: "10px",
            padding: "0.5rem 0.8rem",
            fontWeight: 700,
            cursor: "pointer",
            color: theme === "light" ? "#193553" : "#d7e7ff",
            background: theme === "light" ? "#eef5fc" : "#1a2a45"
        }),
        pdf: {
            border: 0,
            borderRadius: "10px",
            padding: "0.5rem 0.8rem",
            fontWeight: 800,
            cursor: "pointer",
            color: "#fff",
            background: "linear-gradient(95deg, #ff7b2f, #ffbf34)"
        },
        ghost: (theme) => ({
            border: 0,
            borderRadius: "12px",
            padding: "0.58rem 0.9rem",
            fontWeight: 700,
            cursor: "pointer",
            color: theme === "light" ? "#14253b" : "#dbe7f9",
            background: theme === "light" ? "#e9f1fa" : "#243955"
        })
    }
};

createRoot(rootElement).render(html`<${App} />`);
