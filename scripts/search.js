/*
 * File: scripts/search.js
 * Purpose: Live search and filtering for ShipSmart shipments page
 */

(() => {
  "use strict";

  const searchInput    = document.getElementById("searchQuery");
  const searchForm     = document.getElementById("searchForm");
  const searchBtn      = document.getElementById("searchBtn");
  const lookupExternalBtn = document.getElementById('lookupExternalBtn');
  const filterCarrier  = document.getElementById("filterCarrier");
  const filterStatus   = document.getElementById("filterStatus");
  const filterCategory = document.getElementById("filterCategory");
  const dateFrom       = document.getElementById("dateFrom");
  const dateTo         = document.getElementById("dateTo");
  const clearBtn       = document.getElementById("clearFiltersBtn");
  const loadingEl      = document.getElementById("searchLoading");
  const resultsEl      = document.getElementById("searchResults");
  const countEl        = document.getElementById("resultCount");
  const emptyEl        = document.getElementById("noResults");

  if (!resultsEl) return;

  const API_URL     = "../api/search.php";
  const DEBOUNCE_MS = 400;
  let debounceTimer = null;

  // ── Helpers ────────────────────────────────────────────────────────────

  const carrierLabels = {
    aramex: "Aramex",
    dhl: "DHL",
    fedex: "FedEx",
    'smsa-express': "SMSA",
    smsa: "SMSA",
  };

  const statusLabels = {
    created: "Created",
    picked_up: "Picked Up",
    in_transit: "In Transit",
    out_for_delivery: "Out for Delivery",
    delivered: "Delivered",
  };

  const categoryLabels = {
    standard: "Standard",
    express: "Express",
    freight: "Freight",
  };

  const statusClass = (s) => {
    if (!s) return 'pending';
    if (s.includes('deliver')) return 'delivered';
    if (s.includes('transit') || s.includes('picked') || s.includes('out_for')) return 'in-transit';
    return s.replace(/\s+/g, '-').toLowerCase();
  };

  const formatRelativeTime = (iso) => {
    if (!iso) return '';
    const then = new Date(iso);
    if (isNaN(then.getTime())) return iso;
    const diff = Date.now() - then.getTime();
    const sec  = Math.floor(diff / 1000);
    if (sec < 60)  return `${sec}s ago`;
    const min = Math.floor(sec / 60);
    if (min < 60)  return `${min}m ago`;
    const hr  = Math.floor(min / 60);
    if (hr  < 24)  return `${hr}h ago`;
    const days = Math.floor(hr / 24);
    if (days < 30) return `${days}d ago`;
    return then.toLocaleDateString();
  };

  const getStatusIcon = (s) => {
    const cls = statusClass(s);
    if (cls === 'delivered')
      return '<span class="status-icon status-delivered"><svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
    if (cls === 'in-transit')
      return '<span class="status-icon status-in-transit"><svg viewBox="0 0 24 24" fill="none"><path d="M3 12h14l4 4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 19a2 2 0 100-4 2 2 0 000 4zM17 19a2 2 0 100-4 2 2 0 000 4z" fill="currentColor"/></svg></span>';
    return '<span class="status-icon status-pending"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7v6l3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
  };

  const formatDate = (value) => {
    if (!value) return "—";
    const d = new Date(value + "T00:00:00");
    return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString();
  };

  const formatWeight = (kg) => {
    const n = parseFloat(kg);
    return Number.isNaN(n) ? "—" : n.toFixed(2) + " kg";
  };

  const escapeHtml = (str) => {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  };

  // ── Check if user has applied any filter or query ──────────────────────
  const hasActiveSearch = () =>
    (searchInput?.value || '').trim() !== ''
    || !!filterCarrier?.value
    || !!filterStatus?.value
    || !!filterCategory?.value
    || !!dateFrom?.value
    || !!dateTo?.value;

  // ── Params ─────────────────────────────────────────────────────────────
  const buildParams = () => {
    const params = new URLSearchParams();
    const q = (searchInput?.value || "").trim();
    if (q) params.set("q", q);
    if (filterCarrier?.value && filterCarrier.value !== 'auto')
      params.set("carrier", filterCarrier.value);
    if (filterStatus?.value)   params.set("status",    filterStatus.value);
    if (filterCategory?.value) params.set("category",  filterCategory.value);
    if (dateFrom?.value)       params.set("date_from", dateFrom.value);
    if (dateTo?.value)         params.set("date_to",   dateTo.value);
    const looksLikeTracking = q !== '' && /^[A-Z0-9\-]{8,40}$/i.test(q.replace(/\s+/g, ''));
    if (looksLikeTracking) params.set('source', 'trackingmore');
    return params;
  };

  // ── Loading ─────────────────────────────────────────────────────────────
  const setLoading = (on) => {
    if (loadingEl) loadingEl.hidden = !on;
  };

  // ── Render card ─────────────────────────────────────────────────────────
  const renderCard = (shipment) => {
    const status   = shipment.status || "created";
    const stClass  = statusClass(status);
    const card     = document.createElement("article");
    card.className = "card shipment-card";
    card.dataset.eta     = shipment.estimated_delivery || "";
    card.dataset.status  = status;
    card.dataset.carrier = shipment.carrier || "";

    const latestCp = Array.isArray(shipment.checkpoints) && shipment.checkpoints.length
      ? shipment.checkpoints[shipment.checkpoints.length - 1]
      : null;
    const latestSummary = latestCp
      ? `${formatRelativeTime(latestCp.time)} — ${latestCp.status || ''}`
      : (shipment.last_updated ? `Updated ${formatRelativeTime(shipment.last_updated)}` : 'No updates yet');

    card.innerHTML = `
      <div class="shipment-card-head">
        <h3 class="shipment-tracking">${escapeHtml(shipment.tracking_number)}</h3>
        <div style="display:flex;align-items:center;gap:8px;">
          ${getStatusIcon(status)}
          <span class="badge badge-status badge-status-${escapeHtml(stClass)}">${escapeHtml(statusLabels[status] || status)}</span>
        </div>
      </div>
      <p class="shipment-route">
        <strong>${escapeHtml(shipment.origin_city)}</strong>
        <span class="route-arrow" aria-hidden="true">→</span>
        <strong>${escapeHtml(shipment.destination_city)}</strong>
      </p>
      <p class="shipment-latest muted small">${escapeHtml(latestSummary)}</p>
      <div class="shipment-meta">
        <p><span class="meta-label">Carrier</span> ${escapeHtml(carrierLabels[shipment.carrier] || shipment.carrier)}</p>
        <p><span class="meta-label">Category</span> ${escapeHtml(categoryLabels[shipment.category] || shipment.category)}</p>
        <p><span class="meta-label">Weight</span> ${formatWeight(shipment.weight_kg)}</p>
        <p><span class="meta-label">ETA</span> ${formatDate(shipment.estimated_delivery)}</p>
      </div>
      ${shipment.tracking_link ? `<p class="shipment-link"><a href="${escapeHtml(shipment.tracking_link)}" target="_blank" rel="noopener">View carrier tracking</a></p>` : ''}
      ${Array.isArray(shipment.checkpoints) && shipment.checkpoints.length > 0
        ? `<details class="shipment-checkpoints">
             <summary>Checkpoints (${shipment.checkpoints.length})</summary>
             <ul class="checkpoint-list">
               ${shipment.checkpoints.map(cp => `
                 <li>
                   <span class="checkpoint-time">${escapeHtml(formatRelativeTime(cp.time) || cp.time || '')}</span>
                   <span class="checkpoint-status">${escapeHtml(cp.status || '')}${cp.location ? ' <em>(' + escapeHtml(cp.location) + ')</em>' : ''}</span>
                 </li>`).join('')}
             </ul>
           </details>`
        : ''}
    `;
    return card;
  };

  // ── Render results ──────────────────────────────────────────────────────
  const renderResults = (shipments) => {
    resultsEl.innerHTML = "";
    const count = Array.isArray(shipments) ? shipments.length : 0;

    if (countEl) countEl.textContent = "Showing " + count + " result" + (count === 1 ? "" : "s");

    // Only show empty state if the user has actively searched/filtered
    if (emptyEl) emptyEl.hidden = !hasActiveSearch() || count > 0;

    if (count === 0) return;

    const fragment = document.createDocumentFragment();
    shipments.forEach((s) => fragment.appendChild(renderCard(s)));
    resultsEl.appendChild(fragment);
  };

  // ── Run search ──────────────────────────────────────────────────────────
  const runSearch = () => {
    const params = buildParams();
    const url    = params.toString() ? API_URL + "?" + params.toString() : API_URL;

    setLoading(true);

    fetch(url)
      .then((res) => {
        if (!res.ok) throw new Error("Search request failed");
        return res.json();
      })
      .then((data) => renderResults(Array.isArray(data) ? data : []))
      .catch(() => {
        renderResults([]);
        if (countEl) countEl.textContent = "Unable to load shipments";
      })
      .finally(() => setLoading(false));
  };

  // ── Schedule debounced search ───────────────────────────────────────────
  const scheduleSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runSearch, DEBOUNCE_MS);
  };

  // ── Event listeners ─────────────────────────────────────────────────────
  searchInput?.addEventListener("input", scheduleSearch);

  searchForm?.addEventListener("submit", (e) => {
    e.preventDefault();
    clearTimeout(debounceTimer);
    runSearch();
  });

  searchBtn?.addEventListener("click", (e) => {
    e.preventDefault();
    clearTimeout(debounceTimer);
    runSearch();
  });

  // External lookup via TrackingMore
  lookupExternalBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    const params = buildParams();
    params.set('source', 'trackingmore');
    const url = params.toString()
      ? API_URL + "?" + params.toString()
      : API_URL + "?source=trackingmore";
    setLoading(true);
    fetch(url)
      .then((res) => { if (!res.ok) throw new Error('Lookup failed'); return res.json(); })
      .then((data) => renderResults(Array.isArray(data) ? data : []))
      .catch(() => {
        renderResults([]);
        if (countEl) countEl.textContent = 'Lookup failed';
      })
      .finally(() => setLoading(false));
  });

  [filterCarrier, filterStatus, filterCategory, dateFrom, dateTo].forEach((el) => {
    el?.addEventListener("change", runSearch);
  });

  clearBtn?.addEventListener("click", () => {
    if (searchInput)    searchInput.value    = "";
    if (filterCarrier)  filterCarrier.value  = "";
    if (filterStatus)   filterStatus.value   = "";
    if (filterCategory) filterCategory.value = "";
    if (dateFrom)       dateFrom.value       = "";
    if (dateTo)         dateTo.value         = "";
    runSearch();
  });

  // ── Initial load — hide empty state, load all shipments ────────────────
  if (emptyEl) emptyEl.hidden = true;
  runSearch();

})();