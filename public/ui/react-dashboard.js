import React, { useEffect, useMemo, useState } from "https://esm.sh/react@18.2.0";
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

const getSystemTheme = () => (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");

function App() {
    const [rows, setRows] = useState(initialRows);
    const [theme, setTheme] = useState(localStorage.getItem("ksef-ui-theme") || getSystemTheme());
    const [message, setMessage] = useState("");
    const [messageType, setMessageType] = useState("ok");
    const [isNarrow, setIsNarrow] = useState(window.innerWidth < 980);
    const [isBusy, setIsBusy] = useState(false);
    const [isModalOpen, setIsModalOpen] = useState(false);
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
        const onResize = () => setIsNarrow(window.innerWidth < 980);
        window.addEventListener("resize", onResize);
        return () => window.removeEventListener("resize", onResize);
    }, []);

    const sortedRows = useMemo(() => [...rows], [rows]);

    const setAlert = (text, type = "ok") => {
        setMessage(text);
        setMessageType(type);
    };

    const refreshRows = async () => {
        setIsBusy(true);
        try {
            const response = await fetch(rowsEndpoint, {
                method: "GET",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || "Nie udało się pobrać listy faktur.");
            }
            setRows(Array.isArray(payload.rows) ? payload.rows : []);
            setAlert("Lista faktur została odświeżona.", "ok");
        } catch (error) {
            setAlert(error instanceof Error ? error.message : "Wystąpił nieznany błąd.", "error");
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
                throw new Error(payload.message || "Nie udało się wysłać faktury.");
            }

            setRows(Array.isArray(payload.rows) ? payload.rows : []);
            setAlert(payload.message || "Wysłano fakturę.", "ok");
            setIsModalOpen(false);
            setForm((prev) => ({ ...prev, xmlText: "", file: null, offlineMode: false }));
        } catch (error) {
            setAlert(error instanceof Error ? error.message : "Wystąpił nieznany błąd.", "error");
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
                    throw new Error(payload.message || "Nie udało się pobrać faktury.");
                }

                throw new Error(await response.text() || "Nie udało się pobrać faktury.");
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

            setAlert(`Pobrano fakturę ${ksefNumber} (${extension.toUpperCase()}).`, "ok");
        } catch (error) {
            setAlert(error instanceof Error ? error.message : "Wystąpił nieznany błąd.", "error");
        } finally {
            setIsBusy(false);
        }
    };

    return html`
        <main style=${styles.shell(isNarrow)}>
            <section style=${styles.topBar(theme, isNarrow)}>
                <div>
                    <h1 style=${styles.title}>KSeF StructuredInvoice</h1>
                    <p style=${styles.subtitle}>Kompaktowy widok listy faktur z opcją szybkiej wysyłki.</p>
                </div>
                <div style=${styles.topActions(isNarrow)}>
                    <button style=${styles.button.secondary(theme)} type="button" onClick=${() => setTheme(theme === "light" ? "dark" : "light")}>${theme === "light" ? "Tryb ciemny" : "Tryb jasny"}</button>
                    <button style=${styles.button.secondary(theme)} type="button" onClick=${refreshRows} disabled=${isBusy}>Odśwież</button>
                    <button style=${styles.button.primary} type="button" onClick=${() => setIsModalOpen(true)}>Wyślij fakturę</button>
                </div>
            </section>

            ${message ? html`<div style=${styles.toast(messageType)}>${message}</div>` : null}

            <section style=${styles.tableCard(theme)}>
                <div style=${styles.tableHeader}>Lista faktur z KSeF</div>
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
                            ${sortedRows.length === 0
                                ? html`<tr><td style=${styles.td(theme)} colSpan="8">Brak wysłanych faktur.</td></tr>`
                                : sortedRows.map((row, index) => html`
                                    <tr key=${index}>
                                        <td style=${styles.td(theme)}><strong>${row.submittedAt || "n/d"}</strong><br /><small style=${styles.small(theme)}>${row.invoiceNumber || "n/d"}</small></td>
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

            ${isModalOpen ? html`
                <div style=${styles.overlay} onClick=${() => !isBusy && setIsModalOpen(false)}>
                    <div style=${styles.modal(theme, isNarrow)} onClick=${(event) => event.stopPropagation()}>
                        <div style=${styles.modalHeader}>
                            <h2 style=${styles.modalTitle}>Wysyłka faktury</h2>
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

                            <label style=${styles.label}>Treść XML</label>
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
                                Użyj offlineMode
                            </label>

                            <div style=${styles.modalActions}>
                                <button style=${styles.button.ghost(theme)} type="button" onClick=${() => setIsModalOpen(false)} disabled=${isBusy}>Anuluj</button>
                                <button style=${styles.button.primary} type="submit" disabled=${isBusy}>${isBusy ? "Wysyłanie..." : "Wyślij"}</button>
                            </div>
                        </form>
                    </div>
                </div>
            ` : null}
        </main>
    `;
}

const styles = {
    shell: (isNarrow) => ({
        width: isNarrow ? "calc(100% - 1rem)" : "min(1400px, calc(100% - 2rem))",
        margin: "0.8rem auto",
        display: "grid",
        gap: "0.8rem"
    }),
    topBar: (theme, isNarrow) => ({
        display: "flex",
        flexDirection: isNarrow ? "column" : "row",
        alignItems: isNarrow ? "stretch" : "center",
        justifyContent: "space-between",
        gap: "0.8rem",
        borderRadius: "16px",
        padding: "0.9rem 1rem",
        background: theme === "light"
            ? "linear-gradient(118deg, rgba(255, 153, 71, 0.18), rgba(255,255,255,0.88) 45%, rgba(122, 204, 255, 0.84))"
            : "linear-gradient(118deg, rgba(255, 121, 49, 0.28), rgba(20, 32, 58, 0.9) 45%, rgba(59, 112, 186, 0.82))",
        boxShadow: "0 10px 28px rgba(5, 11, 30, 0.22)",
        border: theme === "light" ? "1px solid #e7eef8" : "1px solid #2e4062"
    }),
    title: {
        margin: 0,
        fontFamily: "Sora, sans-serif",
        fontSize: "clamp(1.1rem, 2.2vw, 1.5rem)"
    },
    subtitle: {
        margin: "0.22rem 0 0",
        opacity: 0.85,
        fontWeight: 600,
        fontSize: "0.9rem"
    },
    topActions: (isNarrow) => ({
        display: "flex",
        gap: "0.45rem",
        flexWrap: "wrap",
        width: isNarrow ? "100%" : "auto"
    }),
    toast: (type) => ({
        borderRadius: "12px",
        padding: "0.6rem 0.75rem",
        fontWeight: 700,
        fontSize: "0.88rem",
        background: type === "error" ? "#ffe5e5" : "#dcfce7",
        color: type === "error" ? "#7f1d1d" : "#14532d"
    }),
    tableCard: (theme) => ({
        borderRadius: "16px",
        overflow: "hidden",
        background: theme === "light" ? "#f8fbff" : "#0f1a2f",
        border: theme === "light" ? "1px solid #dfe9f4" : "1px solid #293d5f",
        boxShadow: "0 14px 34px rgba(4, 10, 28, 0.24)"
    }),
    tableHeader: {
        padding: "0.8rem 1rem",
        fontFamily: "Sora, sans-serif",
        fontSize: "0.95rem"
    },
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
        borderRadius: "16px",
        padding: "0.9rem",
        background: theme === "light" ? "#f9fcff" : "#111d34",
        border: theme === "light" ? "1px solid #dbe8f5" : "1px solid #334c74",
        boxShadow: "0 28px 56px rgba(2, 8, 22, 0.45)"
    }),
    modalHeader: {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: "0.8rem",
        marginBottom: "0.5rem"
    },
    modalTitle: {
        margin: 0,
        fontFamily: "Sora, sans-serif",
        fontSize: "1rem"
    },
    iconButton: (theme) => ({
        border: 0,
        borderRadius: "10px",
        padding: "0.48rem 0.68rem",
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
        borderRadius: "10px",
        border: theme === "light" ? "1px solid #cddced" : "1px solid #3c557d",
        background: theme === "light" ? "#fff" : "#0d172b",
        color: theme === "light" ? "#132235" : "#dbe7f9",
        padding: "0.52rem 0.6rem",
        font: "inherit"
    }),
    textarea: (theme) => ({
        borderRadius: "10px",
        border: theme === "light" ? "1px solid #cddced" : "1px solid #3c557d",
        background: theme === "light" ? "#fff" : "#0d172b",
        color: theme === "light" ? "#132235" : "#dbe7f9",
        padding: "0.52rem 0.6rem",
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
            borderRadius: "10px",
            padding: "0.5rem 0.8rem",
            fontWeight: 800,
            cursor: "pointer",
            color: "#fff",
            background: "linear-gradient(95deg, #ff7b2f, #ffbf34)"
        },
        secondary: (theme) => ({
            border: 0,
            borderRadius: "10px",
            padding: "0.5rem 0.8rem",
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
            borderRadius: "10px",
            padding: "0.5rem 0.8rem",
            fontWeight: 700,
            cursor: "pointer",
            color: theme === "light" ? "#14253b" : "#dbe7f9",
            background: theme === "light" ? "#e9f1fa" : "#243955"
        })
    }
};

createRoot(rootElement).render(html`<${App} />`);
