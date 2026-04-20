(function () {
    "use strict";

    var settings = window.M2BASettings || {};
    var messages = settings.messages || {};

    function t(key, fallback) {
        return messages[key] || fallback;
    }

    function buildHeaders() {
        var headers = {
            "Content-Type": "application/json"
        };

        if (settings.restNonce) {
            headers["X-WP-Nonce"] = settings.restNonce;
        }

        return headers;
    }

    function getZoom(app) {
        var zoom = Number(app.getAttribute("data-zoom") || settings.defaultZoom || 16);

        if (!Number.isFinite(zoom)) {
            return 16;
        }

        return Math.max(0, Math.min(20, Math.round(zoom)));
    }

    function getBulkLimit(app) {
        var value = Number(app.getAttribute("data-bulk-max") || settings.bulkMaxUrls || 10);

        if (!Number.isFinite(value)) {
            return 10;
        }

        return Math.max(1, Math.min(10, Math.round(value)));
    }

    function setStatus(statusNode, message, state) {
        statusNode.textContent = message || "";
        statusNode.className = "m2ba-status";

        if (message) {
            statusNode.classList.add("is-visible");
        }

        if (state) {
            statusNode.classList.add("is-" + state);
        }
    }

    function setLoading(button, active) {
        var label = button.querySelector(".m2ba-submit-label");
        var defaultLabel = button.getAttribute("data-default-label") || "";
        var loadingLabel = button.getAttribute("data-loading-label") || defaultLabel;

        button.disabled = active;
        button.classList.toggle("is-loading", active);

        if (label) {
            label.textContent = active ? loadingLabel : defaultLabel;
        }
    }

    function parseBulkUrls(value) {
        return value
            .split(/\r?\n/)
            .map(function (item) {
                return item.trim();
            })
            .filter(function (item) {
                return item.length > 0;
            });
    }

    function switchPanel(app, target) {
        app.querySelectorAll("[data-role='switch']").forEach(function (button) {
            var isActive = button.getAttribute("data-target") === target;
            button.classList.toggle("is-active", isActive);
            button.setAttribute("aria-selected", isActive ? "true" : "false");
        });

        app.querySelectorAll("[data-panel]").forEach(function (panel) {
            var isActive = panel.getAttribute("data-panel") === target;
            panel.classList.toggle("is-active", isActive);
            panel.hidden = !isActive;
        });
    }

    async function copyToClipboard(text) {
        await navigator.clipboard.writeText(text);
    }

    function renderSingleResult(app, payload) {
        var resultNode = app.querySelector("[data-role='single-result']");
        var wgs84Node = app.querySelector("[data-role='single-wgs84']");
        var utmNode = app.querySelector("[data-role='single-utm']");
        var linkNode = app.querySelector("[data-role='single-open-link']");
        var linkTextNode = app.querySelector("[data-role='single-link-text']");
        var buttonLinkNode = app.querySelector("[data-role='single-open-link-button']");

        wgs84Node.textContent = payload.coordinates.lat.toFixed(6) + ", " + payload.coordinates.lon.toFixed(6);
        utmNode.textContent = payload.coordinates.easting + " / " + payload.coordinates.northing;
        linkNode.href = payload.bayernatlas_url;
        buttonLinkNode.href = payload.bayernatlas_url;
        linkTextNode.textContent = payload.bayernatlas_url;
        resultNode.hidden = false;
    }

    function hideSingleResult(app) {
        app.querySelector("[data-role='single-result']").hidden = true;
    }

    function updateBulkCounter(app) {
        var textarea = app.querySelector(".m2ba-textarea");
        var counter = app.querySelector("[data-role='bulk-counter']");
        var count = parseBulkUrls(textarea.value).length;
        var limit = getBulkLimit(app);
        var overLimit = count > limit;

        counter.textContent = count + " von " + limit + " URLs erkannt";
        counter.classList.toggle("is-over-limit", overLimit);
    }

    function hideBulkResult(app) {
        app.querySelector("[data-role='bulk-result']").hidden = true;
    }

    function formatBulkOutput(results) {
        return results
            .filter(function (item) {
                return item.success && item.bayernatlas_url;
            })
            .map(function (item) {
                return item.bayernatlas_url;
            })
            .join("\n");
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function renderBulkList(app, results) {
        var listNode = app.querySelector("[data-role='bulk-results']");

        if (!results.length) {
            listNode.innerHTML = "";
            return;
        }

        listNode.innerHTML = results
            .map(function (item) {
                if (item.success) {
                    return [
                        "<article class='m2ba-bulk-item is-success'>",
                        "<div class='m2ba-bulk-meta'>",
                        "<span class='m2ba-badge m2ba-badge-success'>OK</span>",
                        "<span class='m2ba-bulk-index'>#" + escapeHtml(item.index) + "</span>",
                        "</div>",
                        "<div class='m2ba-bulk-main'>",
                        "<code class='m2ba-bulk-source'>" + escapeHtml(item.source_url) + "</code>",
                        "<div class='m2ba-bulk-details'>",
                        "<span>" + escapeHtml(item.coordinates.lat.toFixed(6) + ", " + item.coordinates.lon.toFixed(6)) + "</span>",
                        "<span>" + escapeHtml(item.coordinates.easting + " / " + item.coordinates.northing) + "</span>",
                        "</div>",
                        "<a class='m2ba-bulk-link' href='" + escapeHtml(item.bayernatlas_url) + "' target='_blank' rel='noopener noreferrer'>" + escapeHtml(item.bayernatlas_url) + "</a>",
                        "</div>",
                        "<div class='m2ba-bulk-actions'>",
                        "<a class='m2ba-action m2ba-action-primary' href='" + escapeHtml(item.bayernatlas_url) + "' target='_blank' rel='noopener noreferrer'>Öffnen</a>",
                        "<button class='m2ba-action m2ba-action-secondary' type='button' data-copy-url='" + escapeHtml(item.bayernatlas_url) + "'>Kopieren</button>",
                        "</div>",
                        "</article>"
                    ].join("");
                }

                return [
                    "<article class='m2ba-bulk-item is-error'>",
                    "<div class='m2ba-bulk-meta'>",
                    "<span class='m2ba-badge m2ba-badge-error'>Fehler</span>",
                    "<span class='m2ba-bulk-index'>#" + escapeHtml(item.index) + "</span>",
                    "</div>",
                    "<div class='m2ba-bulk-main'>",
                    "<code class='m2ba-bulk-source'>" + escapeHtml(item.source_url) + "</code>",
                    "<p class='m2ba-bulk-error'>" + escapeHtml(item.message || t("genericError", "Die Umwandlung ist fehlgeschlagen.")) + "</p>",
                    "</div>",
                    "</article>"
                ].join("");
            })
            .join("");
    }

    function renderBulkResult(app, payload) {
        var resultNode = app.querySelector("[data-role='bulk-result']");
        var totalNode = app.querySelector("[data-role='bulk-total']");
        var successNode = app.querySelector("[data-role='bulk-success']");
        var failedNode = app.querySelector("[data-role='bulk-failed']");
        var outputNode = app.querySelector("[data-role='bulk-output']");
        var outputValue = formatBulkOutput(payload.results || []);

        totalNode.textContent = String(payload.summary.total || 0);
        successNode.textContent = String(payload.summary.successful || 0);
        failedNode.textContent = String(payload.summary.failed || 0);
        outputNode.value = outputValue || t("bulkOutputEmpty", "Noch keine erfolgreichen BayernAtlas-Links vorhanden.");

        renderBulkList(app, payload.results || []);
        resultNode.hidden = false;
    }

    async function fetchJson(url, body) {
        var response = await fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: buildHeaders(),
            body: JSON.stringify(body)
        });

        var payload = await response.json().catch(function () {
            return {};
        });

        if (!response.ok) {
            throw new Error(payload.message || t("genericError", "Die Umwandlung ist fehlgeschlagen."));
        }

        return payload;
    }

    async function handleSingleSubmit(app, event) {
        event.preventDefault();

        var input = app.querySelector(".m2ba-input");
        var button = app.querySelector("[data-role='single-form'] .m2ba-submit");
        var statusNode = app.querySelector("[data-role='single-status']");
        var url = input.value.trim();

        if (!url) {
            hideSingleResult(app);
            setStatus(statusNode, t("emptyUrl", "Bitte gib einen Google-Maps- oder OpenStreetMap-Link ein."), "error");
            input.focus();
            return;
        }

        setLoading(button, true);
        hideSingleResult(app);
        setStatus(statusNode, t("singleLoading", "Link wird umgewandelt …"), "info");

        try {
            var payload = await fetchJson(settings.singleRestUrl, {
                maps_url: url,
                zoom: getZoom(app)
            });

            renderSingleResult(app, payload);
            setStatus(statusNode, "", "");
        } catch (error) {
            hideSingleResult(app);
            setStatus(statusNode, error && error.message ? error.message : t("genericError", "Die Umwandlung ist fehlgeschlagen."), "error");
        } finally {
            setLoading(button, false);
        }
    }

    async function handleBulkSubmit(app, event) {
        event.preventDefault();

        var textarea = app.querySelector(".m2ba-textarea");
        var button = app.querySelector("[data-role='bulk-form'] .m2ba-submit");
        var statusNode = app.querySelector("[data-role='bulk-status']");
        var urls = parseBulkUrls(textarea.value);
        var limit = getBulkLimit(app);

        if (!urls.length) {
            hideBulkResult(app);
            setStatus(statusNode, t("emptyBulk", "Bitte füge mindestens eine URL ein."), "error");
            textarea.focus();
            return;
        }

        if (urls.length > limit) {
            hideBulkResult(app);
            setStatus(statusNode, t("bulkTooMany", "Bitte füge weniger URLs ein."), "error");
            textarea.focus();
            return;
        }

        setLoading(button, true);
        hideBulkResult(app);
        setStatus(statusNode, t("bulkLoading", "Bulk-Umwandlung läuft …"), "info");

        try {
            var payload = await fetchJson(settings.bulkRestUrl, {
                maps_urls: urls,
                zoom: getZoom(app)
            });

            renderBulkResult(app, payload);

            if (payload.summary && payload.summary.failed > 0) {
                setStatus(statusNode, payload.summary.successful + " erfolgreich, " + payload.summary.failed + " mit Fehler.", "info");
            } else {
                setStatus(statusNode, payload.summary.successful + " URLs erfolgreich umgewandelt.", "success");
            }
        } catch (error) {
            hideBulkResult(app);
            setStatus(statusNode, error && error.message ? error.message : t("genericError", "Die Umwandlung ist fehlgeschlagen."), "error");
        } finally {
            setLoading(button, false);
        }
    }

    async function handleSingleCopy(app) {
        var linkNode = app.querySelector("[data-role='single-open-link']");
        var statusNode = app.querySelector("[data-role='single-status']");
        var url = linkNode.getAttribute("href");

        if (!url || url === "#") {
            return;
        }

        try {
            await copyToClipboard(url);
            setStatus(statusNode, t("copySuccess", "Der BayernAtlas-Link wurde in die Zwischenablage kopiert."), "success");
        } catch (error) {
            setStatus(statusNode, t("copyError", "Der Link konnte nicht kopiert werden."), "error");
        }
    }

    async function handleBulkCopyAll(app) {
        var outputNode = app.querySelector("[data-role='bulk-output']");
        var statusNode = app.querySelector("[data-role='bulk-status']");
        var value = outputNode.value.trim();

        if (!value || value === t("bulkOutputEmpty", "Noch keine erfolgreichen BayernAtlas-Links vorhanden.")) {
            return;
        }

        try {
            await copyToClipboard(value);
            setStatus(statusNode, t("copyAllSuccess", "Alle erfolgreichen BayernAtlas-Links wurden kopiert."), "success");
        } catch (error) {
            setStatus(statusNode, t("copyError", "Der Link konnte nicht kopiert werden."), "error");
        }
    }

    function initApp(app) {
        if (app.getAttribute("data-m2ba-ready") === "1") {
            return;
        }

        app.setAttribute("data-m2ba-ready", "1");

        var singleForm = app.querySelector("[data-role='single-form']");
        var bulkForm = app.querySelector("[data-role='bulk-form']");
        var bulkTextarea = app.querySelector(".m2ba-textarea");
        var singleCopyButton = app.querySelector("[data-role='single-copy-link']");
        var bulkCopyAllButton = app.querySelector("[data-role='bulk-copy-all']");
        var bulkResults = app.querySelector("[data-role='bulk-results']");

        app.querySelectorAll("[data-role='switch']").forEach(function (button) {
            button.addEventListener("click", function () {
                switchPanel(app, button.getAttribute("data-target"));
            });
        });

        singleForm.addEventListener("submit", function (event) {
            handleSingleSubmit(app, event);
        });

        bulkForm.addEventListener("submit", function (event) {
            handleBulkSubmit(app, event);
        });

        bulkTextarea.addEventListener("input", function () {
            updateBulkCounter(app);
        });

        singleCopyButton.addEventListener("click", function () {
            handleSingleCopy(app);
        });

        bulkCopyAllButton.addEventListener("click", function () {
            handleBulkCopyAll(app);
        });

        bulkResults.addEventListener("click", function (event) {
            var target = event.target.closest("[data-copy-url]");

            if (!target) {
                return;
            }

            copyToClipboard(target.getAttribute("data-copy-url"))
                .then(function () {
                    setStatus(app.querySelector("[data-role='bulk-status']"), t("copySuccess", "Der BayernAtlas-Link wurde in die Zwischenablage kopiert."), "success");
                })
                .catch(function () {
                    setStatus(app.querySelector("[data-role='bulk-status']"), t("copyError", "Der Link konnte nicht kopiert werden."), "error");
                });
        });

        updateBulkCounter(app);
        switchPanel(app, "single");
    }

    function boot() {
        document.querySelectorAll(".m2ba-app").forEach(initApp);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
