/*
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
(() => {
  "use strict";

  // ===== Elements =====
  const navToggle = document.querySelector(".nav-toggle");
  const trackForm = document.getElementById("trackForm");
  const trackingInput = document.getElementById("trackingNumber");
  const carrierSelect = document.getElementById("carrier");
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
    // simple rule: at least 6 chars, letters/numbers/- allowed
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

  const showLoading = (on) => { if (loadingBox) loadingBox.hidden = !on; };
  const showResult = (on) => { if (resultArea) resultArea.hidden = !on; };

  const resetTimeline = () => {
    steps.forEach(s => s.classList.remove("done", "active"));
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

  // Save last carrier
  const STORAGE_KEY = "shipsmart_last_carrier";
  if (carrierSelect) {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) carrierSelect.value = saved;

    carrierSelect.addEventListener("change", () => {
      if (carrierSelect.value) localStorage.setItem(STORAGE_KEY, carrierSelect.value);
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

    rCarrier.textContent = labelCarrier(carrierSelect.value);
    rTracking.textContent = trackingInput.value.trim().toUpperCase();
    rEta.textContent = pick.etaDays === 0 ? "Delivered" : eta.toDateString();
    rUpdate.textContent = now.toLocaleString();

    setBadge(pick.status, pick.type);
    setTimeline(pick.step);

    showResult(true);
  };

  // ===== Events =====
  navToggle?.addEventListener("click", () => {
    const opened = document.body.classList.toggle("menu-open");
    navToggle.setAttribute("aria-expanded", String(opened));
  });

  trackForm?.addEventListener("submit", (e) => {
    e.preventDefault();

    if (!validate()) {
      return;
    }

    // If demo mode enabled -> show result on this page (no PHP needed)
    if (demoMode?.checked) {
      showResult(false);
      resetTimeline();
      setBadge("Loading", "info");

      showLoading(true);
      setTimeout(() => {
        showLoading(false);
        renderDemo();
      }, 900);

      return;
    }

    // Otherwise: programmatic POST to server/track.php (submit() does not re-trigger this handler)
    trackForm.submit();
  });

  newSearchBtn?.addEventListener("click", () => {
    showResult(false);
    showLoading(false);
    resetTimeline();
    clearErrors();
    trackingInput.value = "";
    trackingInput.focus();
  });

  // ====================================================================
  //  FEEDBACK FORM VALIDATION (pages/feedback.html)
  // ====================================================================
  const feedbackForm = document.getElementById("feedbackForm");

  if (feedbackForm) {
    // — Elements —
    const fFirstName = document.getElementById("firstName");
    const fLastName  = document.getElementById("lastName");
    const fEmail     = document.getElementById("email");
    const fCarrier   = document.getElementById("preferredCarrier");
    const fComments  = document.getElementById("comments");
    const fCharCount = document.getElementById("charCount");
    const fSuccess   = document.getElementById("feedbackSuccess");
    const fResetBtn  = document.getElementById("resetBtn");

    // Error placeholders
    const firstNameErr = document.getElementById("firstNameError");
    const lastNameErr  = document.getElementById("lastNameError");
    const emailErr    = document.getElementById("emailError");
    const ratingErr   = document.getElementById("ratingError");
    const servicesErr = document.getElementById("servicesError");
    const carrierPErr = document.getElementById("carrierPrefError");

    const MAX_CHARS = 500;

    // — Helpers —
    // Mark the closest .form-group as having an error (red border on input)
    const setErr = (el, msg) => {
      if (!el) return;
      el.textContent = msg;
      el.closest(".form-group, .form-fieldset")?.classList.add("has-error");
    };
    const clrErr = (...els) => {
      els.forEach(e => {
        if (!e) return;
        e.textContent = "";
        e.closest(".form-group, .form-fieldset")?.classList.remove("has-error");
      });
    };

    const isValidName = (v) => {
      const val = (v || "").trim();
      // Min 2 letters, only English or Arabic letters (no digits, no spaces, no special chars)
      return val.length >= 2 && /^[A-Za-z\u0621-\u064A]+$/.test(val);
    };

    const isValidEmail = (v) => {
      const val = (v || "").trim();
      if (!val) return false;

      // Regex-based validation (GeeksforGeeks approach):
      // ^[^\s@]+ — local part: no spaces or @, min 2 chars
      // @
      // [^\s@]{2,} — domain name: no spaces or @, min 2 chars
      // \.
      // [^\s@]{2,}$ — TLD: no spaces or @, min 2 chars
      const pattern = /^[^\s@]{2,}@[^\s@]{2,}\.[^\s@]{2,}$/;
      return pattern.test(val);
    };

    // — Live on-blur validation —
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
        clrErr(emailErr);         // don't nag on empty until submit
      } else if (!isValidEmail(fEmail.value)) {
        setErr(emailErr, "Please enter a valid email address (e.g., name@example.com).");
      } else {
        clrErr(emailErr);
      }
    });

    fCarrier?.addEventListener("change", () => {
      if (fCarrier.value) clrErr(carrierPErr);
    });

    // — Character counter —
    fComments?.addEventListener("input", () => {
      const len = fComments.value.length;
      if (fCharCount) fCharCount.textContent = len + " / " + MAX_CHARS + " characters";

      // Prevent exceeding max
      if (len > MAX_CHARS) {
        fComments.value = fComments.value.substring(0, MAX_CHARS);
        if (fCharCount) fCharCount.textContent = MAX_CHARS + " / " + MAX_CHARS + " characters";
      }
    });

    // — Full validation —
    const validateFeedback = () => {
      clrErr(firstNameErr, lastNameErr, emailErr, ratingErr, servicesErr, carrierPErr);
      let ok = true;

      // 1. First Name
      if (!isValidName(fFirstName?.value)) {
        setErr(firstNameErr, "First name must be at least 2 letters, no numbers or symbols.");
        ok = false;
      }

      // 2. Last Name
      if (!isValidName(fLastName?.value)) {
        setErr(lastNameErr, "Last name must be at least 2 letters, no numbers or symbols.");
        ok = false;
      }

      // 3. Email
      if (!isValidEmail(fEmail?.value)) {
        setErr(emailErr, "Please enter a valid email address (e.g., name@example.com).");
        ok = false;
      }

      // 3. Rating (radio)
      const ratingChecked = feedbackForm.querySelector('input[name="rating"]:checked');
      if (!ratingChecked) {
        setErr(ratingErr, "Please select a rating.");
        ok = false;
      }

      // 4. Services (at least one checkbox)
      const servicesChecked = feedbackForm.querySelectorAll('input[name="services[]"]:checked');
      if (servicesChecked.length === 0) {
        setErr(servicesErr, "Please select at least one service.");
        ok = false;
      }

      // 5. Preferred carrier (dropdown)
      if (!fCarrier?.value) {
        setErr(carrierPErr, "Please select your preferred carrier.");
        ok = false;
      }

      return ok;
    };

    // — Submit handler —
    feedbackForm.addEventListener("submit", (e) => {
      e.preventDefault();

      // Run full validation — stop here if anything fails
      if (!validateFeedback()) {
        const firstErr = feedbackForm.querySelector(".error:not(:empty)");
        if (firstErr) firstErr.scrollIntoView({ behavior: "smooth", block: "center" });
        return; // ← hard stop: never reaches success below
      }

      // ── Every required field is valid — show success modal ──
      feedbackForm.reset();
      clrErr(firstNameErr, lastNameErr, emailErr, ratingErr, servicesErr, carrierPErr);
      if (fCharCount) fCharCount.textContent = "0 / " + MAX_CHARS + " characters";

      if (fSuccess) fSuccess.hidden = false;   // show modal overlay
      document.body.style.overflow = "hidden"; // prevent background scroll
    });

    // — Reset handler —
    fResetBtn?.addEventListener("click", () => {
      setTimeout(() => {
        clrErr(firstNameErr, lastNameErr, emailErr, ratingErr, servicesErr, carrierPErr);
        if (fCharCount) fCharCount.textContent = "0 / " + MAX_CHARS + " characters";
      }, 0);
    });

    // — "Submit Another" button: close modal, restore form & scroll —
    const submitAnotherBtn = document.getElementById("submitAnotherBtn");
    submitAnotherBtn?.addEventListener("click", () => {
      if (fSuccess) fSuccess.hidden = true;
      document.body.style.overflow = ""; // restore scroll
      window.scrollTo({ top: 0, behavior: "smooth" });
      fFirstName?.focus();
    });
  }
})();