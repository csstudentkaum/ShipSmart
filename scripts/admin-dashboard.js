/*
 * File: scripts/admin-dashboard.js
 * Purpose: Admin dashboard — fetch shipments from api/admin/shipments.php (TrackingMore proxy).
 */

(() => {
  "use strict";

  const API_URL = "../api/admin/shipments.php";
  const ALLOWED_CARRIERS = ["aramex", "dhl", "fedex", "smsa", "smsa-express", "ups", "usps"];
  const TRACKING_RE = /^[A-Za-z0-9\-]{5,50}$/;

  const STATUS_COLORS = {
    pending: ["#ececec", "#444"],
    created: ["#ececec", "#444"],
    inforeceived: ["#ececec", "#444"],
    pickup: ["#eef3ff", "#1d4ed8"],
    picked_up: ["#eef3ff", "#1d4ed8"],
    transit: ["#fff4e6", "#c2410c"],
    in_transit: ["#fff4e6", "#c2410c"],
    outfordelivery: ["#f3e8ff", "#7e22ce"],
    out_for_delivery: ["#f3e8ff", "#7e22ce"],
    delivered: ["#e9fff0", "#0b6b2c"],
    exception: ["#fff0f0", "#b00020"],
    expired: ["#f5f5f5", "#888"],
  };

  const el = {
    flash: document.getElementById("dashFlash"),
    tbody: document.getElementById("shipmentsBody"),
    titleCount: document.getElementById("shipmentsCount"),
    kpiTotal: document.getElementById("kpiTotal"),
    kpiDelivered: document.getElementById("kpiDelivered"),
    kpiDeliveredSub: document.getElementById("kpiDeliveredSub"),
    kpiProgress: document.getElementById("kpiProgress"),
    kpiTransit: document.getElementById("kpiTransit"),
    kpiExceptions: document.getElementById("kpiExceptions"),
    chartsGrid: document.getElementById("chartsGrid"),
    refreshBtn: document.getElementById("refreshShipmentsBtn"),
    addForm: document.getElementById("addShipmentForm"),
    editForm: document.getElementById("editShipmentForm"),
  };

  let chartInstances = [];
  let shipments = [];

  const esc = (s) => {
    const d = document.createElement("div");
    d.textContent = s ?? "";
    return d.innerHTML;
  };

  const statusBadge = (status) => {
    const key = String(status || "pending")
      .toLowerCase()
      .replace(/[\s-]/g, "_");
    const [bg, fg] = STATUS_COLORS[key] || ["#ececec", "#444"];
    const label = key.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
    return `<span style="background:${bg};color:${fg};padding:3px 10px;border-radius:999px;font-size:0.78rem;font-weight:800">${esc(label)}</span>`;
  };

  const showFlash = (type, msg) => {
    if (!el.flash) return;
    const text = (msg || "").trim();
    if (!text) {
      el.flash.hidden = true;
      el.flash.textContent = "";
      return;
    }
    el.flash.className = `flash flash-${type}`;
    el.flash.textContent = text;
    el.flash.hidden = false;
    el.flash.scrollIntoView({ behavior: "smooth", block: "nearest" });
  };

  const destroyCharts = () => {
    chartInstances.forEach((c) => c.destroy());
    chartInstances = [];
  };

  const analytics = (list) => {
    const byStatus = {};
    const byCarrier = {};
    const byDay = [0, 0, 0, 0, 0, 0, 0];

    list.forEach((s) => {
      const st = s.status || "pending";
      byStatus[st] = (byStatus[st] || 0) + 1;
      const ca = (s.carrier || "unknown").toUpperCase();
      byCarrier[ca] = (byCarrier[ca] || 0) + 1;
      if (s.created_at) {
        const dow = new Date(s.created_at).getDay();
        if (!Number.isNaN(dow)) byDay[dow]++;
      }
    });

    const total = list.length;
    const delivered = byStatus.delivered || 0;
    const inTransit = byStatus.transit || byStatus.in_transit || 0;
    const exceptions = byStatus.exception || byStatus.undelivered || 0;
    const deliveryRate = total > 0 ? Math.round((delivered / total) * 100) : 0;

    return { byStatus, byCarrier, byDay, total, delivered, inTransit, exceptions, deliveryRate };
  };

  const updateKpis = (stats) => {
    if (el.kpiTotal) el.kpiTotal.textContent = stats.total;
    if (el.kpiDelivered) el.kpiDelivered.textContent = stats.delivered;
    if (el.kpiDeliveredSub) el.kpiDeliveredSub.textContent = `${stats.deliveryRate}% delivery rate`;
    if (el.kpiProgress) el.kpiProgress.style.width = `${stats.deliveryRate}%`;
    if (el.kpiTransit) el.kpiTransit.textContent = stats.inTransit;
    if (el.kpiExceptions) el.kpiExceptions.textContent = stats.exceptions;
    if (el.titleCount) el.titleCount.textContent = stats.total;
  };

  const renderCharts = (stats) => {
    if (!el.chartsGrid || typeof Chart === "undefined") return;

    el.chartsGrid.hidden = stats.total === 0;
    if (stats.total === 0) {
      destroyCharts();
      return;
    }

    destroyCharts();

    const primary = "#7b2b6a";
    const accent = "#ff8a1f";
    const border = "#ececec";
    const muted = "#6b6b6b";
    const statusPalette = ["#ececec", "#eef3ff", "#fff4e6", "#f3e8ff", "#e9fff0", "#fff0f0", "#f5f5f5", "#e0f0ff"];
    const statusBorderPalette = ["#aaa", "#1d4ed8", "#c2410c", "#7e22ce", "#0b6b2c", "#b00020", "#888", "#1a88dd"];

    const statusKeys = Object.keys(stats.byStatus);
    const statusData = Object.values(stats.byStatus);
    const carrierKeys = Object.keys(stats.byCarrier);
    const carrierData = Object.values(stats.byCarrier);
    const dayLabels = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

    Chart.defaults.font.family = "system-ui, -apple-system, sans-serif";
    Chart.defaults.font.size = 12;

    const ctxStatus = document.getElementById("chartStatus");
    if (ctxStatus && statusData.length) {
      chartInstances.push(
        new Chart(ctxStatus, {
          type: "doughnut",
          data: {
            labels: statusKeys.map((l) =>
              l.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase())
            ),
            datasets: [
              {
                data: statusData,
                backgroundColor: statusPalette.slice(0, statusData.length),
                borderColor: statusBorderPalette.slice(0, statusData.length),
                borderWidth: 2,
                hoverOffset: 6,
              },
            ],
          },
          options: {
            cutout: "68%",
            plugins: { legend: { position: "bottom", labels: { boxWidth: 10, padding: 12, color: muted } } },
          },
        })
      );
    }

    const ctxCarrier = document.getElementById("chartCarrier");
    if (ctxCarrier && carrierData.length) {
      chartInstances.push(
        new Chart(ctxCarrier, {
          type: "bar",
          data: {
            labels: carrierKeys,
            datasets: [
              {
                label: "Shipments",
                data: carrierData,
                backgroundColor: primary + "cc",
                borderColor: primary,
                borderWidth: 1.5,
                borderRadius: 6,
              },
            ],
          },
          options: {
            plugins: { legend: { display: false } },
            scales: {
              y: { beginAtZero: true, ticks: { stepSize: 1, color: muted }, grid: { color: border } },
              x: { ticks: { color: muted }, grid: { display: false } },
            },
          },
        })
      );
    }

    const ctxDay = document.getElementById("chartDay");
    if (ctxDay) {
      chartInstances.push(
        new Chart(ctxDay, {
          type: "line",
          data: {
            labels: dayLabels,
            datasets: [
              {
                label: "Shipments created",
                data: stats.byDay,
                borderColor: accent,
                backgroundColor: accent + "22",
                borderWidth: 2.5,
                pointBackgroundColor: accent,
                pointRadius: 4,
                fill: true,
                tension: 0.4,
              },
            ],
          },
          options: {
            plugins: { legend: { display: false } },
            scales: {
              y: { beginAtZero: true, ticks: { stepSize: 1, color: muted }, grid: { color: border } },
              x: { ticks: { color: muted }, grid: { display: false } },
            },
          },
        })
      );
    }
  };

  const renderTable = (list) => {
    if (!el.tbody) return;

    if (!list.length) {
      el.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px">No shipments yet. Add one using the button above.</td></tr>`;
      return;
    }

    el.tbody.innerHTML = list
      .map((s, i) => {
        const route =
          s.origin_city || s.destination_city
            ? `${esc(s.origin_city)}${s.origin_city && s.destination_city ? " → " : ""}${esc(s.destination_city)}`
            : '<span style="color:var(--muted)">—</span>';
        const event = s.latest_event
          ? `<div class="latest-event" title="${esc(s.latest_event)}">${esc(s.latest_event)}</div>`
          : '<span style="color:var(--muted)">—</span>';

        return `<tr>
          <td>${i + 1}</td>
          <td><strong style="color:var(--primary)">${esc(s.tracking_number)}</strong></td>
          <td>${esc((s.carrier || "").toUpperCase())}</td>
          <td>${route}</td>
          <td>${statusBadge(s.status)}</td>
          <td>${event}</td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn-sm btn-edit" type="button"
                data-id="${esc(s.id)}"
                data-tracking="${esc(s.tracking_number)}"
                data-carrier="${esc(s.carrier)}"
                data-origin="${esc(s.origin_city)}"
                data-dest="${esc(s.destination_city)}">Edit</button>
              <button class="btn-sm btn-del" type="button" data-delete-id="${esc(s.id)}">Delete</button>
            </div>
          </td>
        </tr>`;
      })
      .join("");

    el.tbody.querySelectorAll(".btn-edit").forEach((btn) => {
      btn.addEventListener("click", () => openEdit(btn));
    });
    el.tbody.querySelectorAll("[data-delete-id]").forEach((btn) => {
      btn.addEventListener("click", () => deleteShipment(btn.dataset.deleteId));
    });
  };

  const applyShipments = (list) => {
    shipments = list;
    const stats = analytics(list);
    updateKpis(stats);
    renderTable(list);
    renderCharts(stats);
  };

  const fetchShipments = async () => {
    if (el.refreshBtn) el.refreshBtn.disabled = true;
    if (el.tbody) {
      el.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px">Loading from TrackingMore…</td></tr>`;
    }

    try {
      const res = await fetch(API_URL, { credentials: "include", headers: { Accept: "application/json" } });
      const data = await res.json();

      if (!data.ok) {
        showFlash("err", data.message || "Could not load shipments from TrackingMore.");
        applyShipments([]);
        return data;
      }

      applyShipments(data.shipments || []);
      return data;
    } catch (err) {
      showFlash("err", "Network error loading shipments. Check that PHP is running and you are logged in as admin.");
      applyShipments([]);
      throw err;
    } finally {
      if (el.refreshBtn) el.refreshBtn.disabled = false;
    }
  };

  const postAction = async (formData) => {
    const res = await fetch(API_URL, {
      method: "POST",
      credentials: "include",
      body: formData,
    });
    return parseApiResponse(res);
  };

  const putEdit = async (form) => {
    const body = Object.fromEntries(new FormData(form).entries());
    const res = await fetch(API_URL, {
      method: "PUT",
      credentials: "include",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(body),
    });
    return parseApiResponse(res);
  };

  const parseApiResponse = async (res) => {
    const data = await res.json();
    if (!res.ok && !data.message) {
      throw new Error("Request failed");
    }
    return data;
  };

  const deleteShipment = async (id) => {
    if (!id || !confirm("Delete this shipment from TrackingMore?")) return;

    try {
      const res = await fetch(
        `${API_URL}?id=${encodeURIComponent(id)}`,
        {
          method: "DELETE",
          credentials: "include",
          headers: { Accept: "application/json" },
        }
      );
      const data = await parseApiResponse(res);
      showFlash(data.ok ? "ok" : "err", data.message || "Delete failed.");
      if (data.shipments) applyShipments(data.shipments);
      else await fetchShipments();
    } catch {
      showFlash("err", "Could not delete shipment. Check DELETE api/admin/shipments.php in Network.");
    }
  };

  // ── Modals ──
  const openModal = (id) => document.getElementById(id)?.classList.add("open");
  const closeModal = (id) => document.getElementById(id)?.classList.remove("open");

  window.openEdit = (btn) => {
    const d = btn.dataset;
    document.getElementById("editId").value = d.id;
    const editTracking = document.getElementById("editTracking");
    const editCarrier = document.getElementById("editCarrier");
    if (editTracking) editTracking.value = d.tracking || "";
    if (editCarrier) editCarrier.value = d.carrier || "";
    document.getElementById("eTrackingDisplay").textContent =
      `${(d.tracking || "").toUpperCase()} · ${(d.carrier || "").toUpperCase()}`;
    document.getElementById("eOrigin").value = d.origin || "";
    document.getElementById("eDest").value = d.dest || "";
    openModal("editModal");
  };

  // ── Add form validation ──
  const addFields = {
    tracking: document.getElementById("addTracking"),
    carrier: document.getElementById("addCarrier"),
    origin: document.getElementById("addOrigin"),
    dest: document.getElementById("addDest"),
  };
  const addErrors = {
    tracking: document.getElementById("addTrackingError"),
    carrier: document.getElementById("addCarrierError"),
    route: document.getElementById("addRouteError"),
  };

  const setFieldErr = (key, msg) => {
    const errEl = addErrors[key];
    if (errEl) errEl.textContent = msg || "";
    const inputMap = {
      tracking: addFields.tracking,
      carrier: addFields.carrier,
    };
    const input = inputMap[key];
    if (input) input.classList.toggle("is-invalid", Boolean(msg));
  };

  const validateAddShipment = () => {
    Object.keys(addErrors).forEach((k) => setFieldErr(k, ""));
    let ok = true;
    const tn = (addFields.tracking?.value || "").trim();
    const carrier = (addFields.carrier?.value || "").trim().toLowerCase();

    if (!tn) {
      setFieldErr("tracking", "Tracking number is required.");
      ok = false;
    } else if (!TRACKING_RE.test(tn)) {
      setFieldErr("tracking", "Invalid tracking number (5–50 letters, numbers, or hyphens).");
      ok = false;
    }
    if (!carrier) {
      setFieldErr("carrier", "Carrier is required.");
      ok = false;
    } else if (!ALLOWED_CARRIERS.includes(carrier)) {
      setFieldErr("carrier", "Invalid carrier. Please select a supported courier.");
      ok = false;
    }
    const origin = (addFields.origin?.value || "").trim();
    const dest = (addFields.dest?.value || "").trim();
    if (origin.length > 80 || dest.length > 80) {
      setFieldErr("route", "Route cities must be 80 characters or fewer.");
      ok = false;
    }
    return ok;
  };

  const bindForms = () => {
    document.getElementById("openAddBtn")?.addEventListener("click", () => openModal("addModal"));
    document.querySelectorAll(".modal-bg").forEach((bg) => {
      bg.addEventListener("click", (e) => {
        if (e.target === bg) bg.classList.remove("open");
      });
    });
    document.querySelectorAll("[data-close-modal]").forEach((btn) => {
      btn.addEventListener("click", () => closeModal(btn.dataset.closeModal));
    });

    el.addForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!validateAddShipment()) {
        (el.addForm.querySelector(".is-invalid") || addFields.tracking)?.focus();
        return;
      }

      const submitBtn = el.addForm.querySelector('[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const data = await postAction(new FormData(el.addForm));
        showFlash(data.ok ? "ok" : "err", data.message);
        if (data.ok) {
          el.addForm.reset();
          closeModal("addModal");
          applyShipments(data.shipments || []);
        }
      } catch {
        showFlash("err", "Could not add shipment. Check the Network tab for api/admin/shipments.php.");
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });

    el.editForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = (document.getElementById("editId")?.value || "").trim();
      if (!id) {
        showFlash("err", "Missing shipment ID. Close the dialog and open Edit again.");
        return;
      }

      const submitBtn = el.editForm.querySelector('[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;
      try {
        const data = await putEdit(el.editForm);
        showFlash(data.ok ? "ok" : "err", data.message);
        if (data.ok) {
          closeModal("editModal");
          if (data.shipments?.length) {
            applyShipments(data.shipments);
          } else {
            await fetchShipments();
          }
        }
      } catch {
        showFlash("err", "Could not update shipment. Check PUT api/admin/shipments.php in Network.");
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  };

  const init = () => {
    bindForms();
    el.refreshBtn?.addEventListener("click", () => fetchShipments());

    const params = new URLSearchParams(window.location.search);
    const urlMsg = (params.get("msg") || "").trim();
    const urlErr = (params.get("err") || "").trim();
    if (urlMsg) showFlash("ok", urlMsg);
    else if (urlErr) showFlash("err", urlErr);

    fetchShipments();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
