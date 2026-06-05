/*
Name: Sama Salem Saloum
ID: 2205679
Section: CPCS403
Date: 27-02-2026

Member 1:
  Name: Wareef Alzubaidi
  Student ID: 2207221
  Section: DAR

Member 2:
  Name: Sama Salem Salloum
  Student ID: 2205679
  Section: DAR

Member 3:
  Name: Manar Abdullah Alharbi
  Student ID: 2206712
  Section: DAR

File: scripts/main.js
Purpose: JavaScript functionality — navigation toggle, tracking form validation, demo mode results, timeline rendering, and feedback form validation with success handling.
*/

(function () {
  "use strict";

  // Show Dashboard nav item for admins when page is static HTML
  (function revealAdminNav(){
    try{
      fetch('/api/whoami.php', { credentials: 'include' })
        .then(r => r.json())
        .then(j => {
          if (j && j.role === 'admin') {
            const el = document.getElementById('nav-dashboard');
            if (el) el.style.display = '';
          }
        }).catch(()=>{});
    }catch(e){}
  })();

  // ===== Common elements (may be missing on some pages) =====
  const navToggle = document.querySelector(".nav-toggle");
  const trackForm = document.getElementById("trackForm");
  const trackingInput = document.getElementById("trackingNumber");
  const carrierSelect = document.getElementById("carrier") || document.getElementById("preferredCarrier");
  const demoMode = document.getElementById("demoMode");

  const trackingError = document.getElementById("trackingError");
  const carrierError = document.getElementById("carrierError");

  const loadingBox = document.getElementById("loadingBox");
  const resultArea = document.getElementById("resultArea");

  const statusBadge = document.getElementById("statusBadge");
  const rCarrier = document.getElementById("rCarrier");
  const rTracking = document.getElementById("rTracking");
  const rEta = document.getElementById("rEta");
  const rUpdate = document.getElementById("rUpdate");

  const newSearchBtn = document.getElementById("newSearchBtn");
  const steps = Array.from(document.querySelectorAll(".step"));

  // ===== Helpers =====
  const labelCarrier = (val) => {
    const map = { aramex: "Aramex", dhl: "DHL", fedex: "FedEx", smsa: "SMSA" };
    return map[val] || "—";
  };

  const setBadge = (text, type = "info") => {
    if (!statusBadge) return;
    statusBadge.textContent = text;
    statusBadge.className = "badge";
    if (type === "ok") statusBadge.classList.add("ok");
    if (type === "warn") statusBadge.classList.add("warn");
  };

  const clearErrors = () => {
    if (trackingError) trackingError.textContent = "";
    if (carrierError) carrierError.textContent = "";
  };

  const isValidTracking = (v) => {
    const value = (v || "").trim();
    return value.length >= 6 && /^[A-Za-z0-9-]+$/.test(value);
  };

  const validate = () => {
    clearErrors();
    let ok = true;

    if (trackingInput && !isValidTracking(trackingInput.value)) {
      if (trackingError) trackingError.textContent = "Enter a valid tracking number (min 6 characters, letters/numbers).";
      ok = false;
    }
    if (carrierSelect && !carrierSelect.value) {
      if (carrierError) carrierError.textContent = "Please select a carrier.";
      ok = false;
    }
    return ok;
  };

  const showLoading = (on) => {
    if (!loadingBox) return;
    loadingBox.hidden = !on;
    loadingBox.style.display = on ? "" : "none";
  };
  const showResult = (on) => {
    if (!resultArea) return;
    resultArea.hidden = !on;
    resultArea.style.display = on ? "" : "none";
  };

  const resetTimeline = () => {
    steps.forEach((s) => s.classList.remove("done", "active"));
  };

  const setTimeline = (key) => {
    const order = ["created", "picked", "transit", "out", "delivered"];
    const idx = order.indexOf(key);
    resetTimeline();

    steps.forEach((li) => {
      const i = order.indexOf(li.dataset.step);
      if (i < idx) li.classList.add("done");
      if (i === idx) li.classList.add("active");
    });
  };

  // Save last carrier — wrapped in try/catch for iOS private mode
  const STORAGE_KEY = "shipsmart_last_carrier";
  if (carrierSelect) {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) carrierSelect.value = saved;
    } catch (e) {}

    carrierSelect.addEventListener("change", () => {
      try {
        if (carrierSelect.value) localStorage.setItem(STORAGE_KEY, carrierSelect.value);
      } catch (e) {}
    });
  }

  // Demo scenarios
  const demo = [
    { status: "In Transit", type: "info", step: "transit", etaDays: 3 },
    { status: "Out for Delivery", type: "warn", step: "out", etaDays: 1 },
    { status: "Delivered", type: "ok", step: "delivered", etaDays: 0 },
  ];

  const renderDemo = () => {
    showLoading(false);

    const pick = demo[Math.floor(Math.random() * demo.length)];
    const now = new Date();

    const eta = new Date(now);
    eta.setDate(eta.getDate() + pick.etaDays);

    if (rCarrier) rCarrier.textContent = labelCarrier(carrierSelect?.value);
    if (rTracking) rTracking.textContent = trackingInput?.value.trim().toUpperCase();
    if (rEta) rEta.textContent = pick.etaDays === 0 ? "Delivered" : eta.toDateString();
    if (rUpdate) rUpdate.textContent = now.toLocaleString();

    setBadge(pick.status, pick.type);
    setTimeline(pick.step);

    showResult(true);
  };

  // ===== Demo-mode checkbox: visual feedback only =====
  const trackBtn = document.getElementById("trackBtn");

  const syncTrackBtn = () => {
    if (!trackBtn || !demoMode) return;
    const enabled = demoMode.checked;
    trackBtn.style.opacity = enabled ? "" : "0.45";
    trackBtn.style.cursor = enabled ? "" : "not-allowed";
  };

  demoMode?.addEventListener("change", syncTrackBtn);
  demoMode?.addEventListener("click", syncTrackBtn);
  syncTrackBtn();

  // ===== Nav toggle =====
  navToggle?.addEventListener("click", () => {
    const opened = document.body.classList.toggle("menu-open");
    navToggle.setAttribute("aria-expanded", String(opened));
  });

  // ===== Convert header nav to side-nav on wide screens =====
  const setupSideNav = () => {
    // only on desktop widths
    if (window.innerWidth < 861) {
      document.body.classList.remove('layout-sidebar');
      const existing = document.getElementById('site-side-nav');
      if (existing) existing.remove();
      return;
    }

    // create side-nav container if not exists
    let side = document.getElementById('site-side-nav');
    if (!side) {
      side = document.createElement('aside');
      side.id = 'site-side-nav';
      side.className = 'side-nav';
      // clone brand and nav-list
      const header = document.querySelector('.site-header .brand');
      if (header) side.appendChild(header.cloneNode(true));
      const nav = document.querySelector('.site-header .nav');
      if (nav) side.appendChild(nav.cloneNode(true));
      document.body.appendChild(side);
    }
    document.body.classList.add('layout-sidebar');
    // expose dashboard link if admin
    const adminLi = document.getElementById('nav-dashboard');
    if (adminLi) adminLi.style.display = '';
  };

  // run on load and resize (debounced)
  let resizeTimer = null;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(setupSideNav, 120);
  });
  // initial
  setupSideNav();

  // ===== Track button =====
  const doTrack = () => {
    const isDemoChecked = !!document.getElementById("demoMode")?.checked;
    const hint = document.getElementById("demohint");

    if (!isDemoChecked) {
      if (hint) hint.style.display = "block";
      return;
    }
    if (hint) hint.style.display = "none";

    if (!validate()) return;

    showResult(false);
    resetTimeline();
    setBadge("Loading", "info");
    showLoading(true);
    setTimeout(() => {
      showLoading(false);
      renderDemo();
    }, 900);
  };

  trackBtn?.addEventListener("click", doTrack);

  // Prevent native form submission
  trackForm?.addEventListener("submit", (e) => {
    e.preventDefault();
  });

  newSearchBtn?.addEventListener("click", () => {
    showResult(false);
    showLoading(false);
    resetTimeline();
    clearErrors();
    if (trackingInput) trackingInput.value = "";
    trackingInput?.focus();
  });

  // ====================================================================
  //  FEEDBACK FORM VALIDATION (pages/feedback.html)
  // ====================================================================
  const feedbackForm = document.getElementById("feedbackForm");
  if (feedbackForm) {
  // If the page includes the success overlay element, move it to body so
  // it is not constrained by parent containers (prevents it from appearing
  // at the end of the page when shown).
  const existingSuccess = document.getElementById("feedbackSuccess");
  if (existingSuccess && existingSuccess.parentElement !== document.body) {
    document.body.appendChild(existingSuccess);
    // ensure it's hidden initially
    existingSuccess.hidden = true;
    existingSuccess.style.display = "none";
  }

  const feedbackForm = document.getElementById("feedbackForm");

  if (!feedbackForm) return;
    const fFirstName = document.getElementById("firstName");
    const fLastName = document.getElementById("lastName");
    const fEmail = document.getElementById("email");
    const fCarrier = document.getElementById("preferredCarrier");
    const fComments = document.getElementById("comments");
    const fCharCount = document.getElementById("charCount");
    const fSuccess = document.getElementById("feedbackSuccess");
    const fResetBtn = document.getElementById("resetBtn");

    const firstNameErr = document.getElementById("firstNameError");
    const lastNameErr = document.getElementById("lastNameError");
    const emailErr = document.getElementById("emailError");
    const ratingErr = document.getElementById("ratingError");
    const servicesErr = document.getElementById("servicesError");
    const carrierPErr = document.getElementById("carrierPrefError");

    const MAX_CHARS = 500;

    const setErr = (el, msg) => {
      if (!el) return;
      el.textContent = msg;
      el.closest(".form-group, .form-fieldset")?.classList.add("has-error");
    };
    const clrErr = (...els) => {
      els.forEach((e) => {
        if (!e) return;
        e.textContent = "";
        e.closest(".form-group, .form-fieldset")?.classList.remove("has-error");
      });
    };

    const isValidName = (v) => {
      const val = (v || "").trim();
      return val.length >= 2 && /^[A-Za-z - -\u06FF]+$/.test(val.replace(/\s+/g, ""));
    };

    const isValidEmail = (v) => {
      const val = (v || "").trim();
      if (!val) return false;
      const pattern = /^[^\s@]{2,}@[^^\s@]{2,}\.[^\s@]{2,}$/;
      return pattern.test(val);
    };

    // Live validation
    fFirstName?.addEventListener("blur", () => {
      if (fFirstName.value.trim() === "") {
        clrErr(firstNameErr);
      } else if (!isValidName(fFirstName.value)) {
        setErr(firstNameErr, "First name must be at least 2 letters, no numbers or symbols.");
      } else {
        clrErr(firstNameErr);
      }
    });

    fLastName?.addEventListener("blur", () => {
      if (fLastName.value.trim() === "") {
        clrErr(lastNameErr);
      } else if (!isValidName(fLastName.value)) {
        setErr(lastNameErr, "Last name must be at least 2 letters, no numbers or symbols.");
      } else {
        clrErr(lastNameErr);
      }
    });

    fEmail?.addEventListener("blur", () => {
      if (fEmail.value.trim() === "") {
        clrErr(emailErr);
      } else if (!isValidEmail(fEmail.value)) {
        setErr(emailErr, "Please enter a valid email address (e.g., name@example.com).");
      } else {
        clrErr(emailErr);
      }
    });

    fCarrier?.addEventListener("change", () => {
      if (fCarrier.value) clrErr(carrierPErr);
    });

    fComments?.addEventListener("input", () => {
      const len = fComments.value.length;
      if (fCharCount) fCharCount.textContent = len + " / " + MAX_CHARS + " characters";
      if (len > MAX_CHARS) {
        fComments.value = fComments.value.substring(0, MAX_CHARS);
        if (fCharCount) fCharCount.textContent = MAX_CHARS + " / " + MAX_CHARS + " characters";
      }
    });

    const validateFeedback = () => {
      clrErr(firstNameErr, lastNameErr, emailErr, ratingErr, servicesErr, carrierPErr);
      let ok = true;

      if (!isValidName(fFirstName?.value)) {
        setErr(firstNameErr, "First name must be at least 2 letters, no numbers or symbols.");
        ok = false;
      }

      if (!isValidName(fLastName?.value)) {
        setErr(lastNameErr, "Last name must be at least 2 letters, no numbers or symbols.");
        ok = false;
      }

      if (!isValidEmail(fEmail?.value)) {
        setErr(emailErr, "Please enter a valid email address (e.g., name@example.com).");
        ok = false;
      }

      const ratingChecked = feedbackForm.querySelector('input[name="rating"]:checked');
      if (!ratingChecked) {
        setErr(ratingErr, "Please select a rating.");
        ok = false;
      }

      const servicesChecked = feedbackForm.querySelectorAll('input[name="services[]"]:checked');
      if (servicesChecked.length === 0) {
        setErr(servicesErr, "Please select at least one service.");
        ok = false;
      }

      if (!fCarrier?.value) {
        setErr(carrierPErr, "Please select your preferred carrier.");
        ok = false;
      }

      return ok;
    };

    const submitBtn = document.getElementById("submitBtn");

    const handleSubmit = (e) => {
      if (e && e.cancelable) e.preventDefault();
      if (!validateFeedback()) {
        const firstErr = feedbackForm.querySelector(".error:not(:empty)");
        if (firstErr) firstErr.scrollIntoView({ behavior: "smooth", block: "center" });
        return;
      }

      feedbackForm.reset();
      clrErr(firstNameErr, lastNameErr, emailErr, ratingErr, servicesErr, carrierPErr);
      if (fCharCount) fCharCount.textContent = "0 / " + MAX_CHARS + " characters";

      if (fSuccess) {
        // move overlay to document body to avoid being constrained by parent containers
        if (fSuccess.parentElement !== document.body) {
          document.body.appendChild(fSuccess);
        }
        // show overlay by removing hidden and ensure it's visible
        fSuccess.hidden = false;
        fSuccess.style.display = "flex";
        fSuccess.style.position = "fixed";
        fSuccess.style.top = 0;
        fSuccess.style.left = 0;
        fSuccess.style.right = 0;
        fSuccess.style.bottom = 0;
        fSuccess.style.zIndex = 10000;
        // set focus to modal box for accessibility
        const modalBox = fSuccess.querySelector('.modal-box');
        if (modalBox) {
          modalBox.setAttribute('tabindex', '-1');
          modalBox.focus();
        }
      }
      // prevent background scroll
      document.body.style.overflow = "hidden";
    };

    submitBtn?.addEventListener("click", handleSubmit);
    submitBtn?.addEventListener("touchend", handleSubmit);

    fResetBtn?.addEventListener("click", () => {
      setTimeout(() => {
        clrErr(firstNameErr, lastNameErr, emailErr, ratingErr, servicesErr, carrierPErr);
        if (fCharCount) fCharCount.textContent = "0 / " + MAX_CHARS + " characters";
      }, 0);
    });

    const submitAnotherBtn = document.getElementById("submitAnotherBtn");
    submitAnotherBtn?.addEventListener("click", () => {
      if (fSuccess) {
        fSuccess.hidden = true;
        fSuccess.style.display = "none";
        // remove inline positioning styles
        fSuccess.style.position = "";
        fSuccess.style.top = "";
        fSuccess.style.left = "";
        fSuccess.style.right = "";
        fSuccess.style.bottom = "";
        fSuccess.style.zIndex = "";
      }
      document.body.style.overflow = "";
      window.scrollTo({ top: 0, behavior: "smooth" });
      fFirstName?.focus();
    });
  }
})();