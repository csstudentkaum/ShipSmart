<?php
/*
 * File: login.php
 * Purpose: Login page — split layout, branded left panel, clean form right.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShipSmart | Sign In</title>
  <link rel="stylesheet" href="global/main.css">
  <style>
    *{ box-sizing:border-box; margin:0; padding:0; }

    body{
      min-height:100vh;
      display:flex;
      flex-direction:column;
      background:#fff;
    }

    /* ── Split layout ── */
    .auth-wrap{
      flex:1;
      display:grid;
      grid-template-columns: 1fr 1fr;
      min-height:100vh;
    }

    /* ── Left panel ── */
    .auth-panel{
      position:relative;
      background:
        radial-gradient(ellipse 700px 500px at 20% 110%, rgba(255,138,31,0.25), transparent 60%),
        radial-gradient(ellipse 600px 500px at 90% -10%, rgba(123,43,106,0.4), transparent 60%),
        #4f1b43;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      padding:48px 52px;
      overflow:hidden;
    }

    /* dot-grid texture */
    .auth-panel::before{
      content:"";
      position:absolute;
      inset:0;
      background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
      background-size:28px 28px;
      pointer-events:none;
    }

    /* glowing accent circle */
    .auth-panel::after{
      content:"";
      position:absolute;
      width:480px; height:480px;
      border-radius:50%;
      background: radial-gradient(circle at 40% 40%,
        rgba(255,138,31,0.22), transparent 60%),
        radial-gradient(circle at 70% 70%,
        rgba(123,43,106,0.35), transparent 55%);
      bottom:-140px; right:-120px;
      pointer-events:none;
    }

    .panel-logo{
      display:flex;
      align-items:center;
      gap:12px;
      position:relative;
      z-index:1;
      text-decoration:none;
    }
    .panel-logo img{
      width:44px; height:44px;
      filter:brightness(0) invert(1);
      opacity:0.95;
    }
    .panel-logo span{
      font-size:1.2rem;
      font-weight:900;
      color:#fff;
      letter-spacing:-0.3px;
    }

    .panel-body{
      position:relative;
      z-index:1;
    }
    .panel-body h2{
      font-size:clamp(1.8rem, 2.8vw, 2.6rem);
      font-weight:900;
      color:#fff;
      line-height:1.1;
      letter-spacing:-0.5px;
      margin-bottom:16px;
    }
    .panel-body h2 em{
      font-style:normal;
      color:var(--accent);
    }
    .panel-body p{
      color:rgba(255,255,255,0.6);
      font-size:0.95rem;
      line-height:1.65;
      max-width:32ch;
      margin-bottom:32px;
    }

    /* feature pills */
    .panel-features{
      display:flex;
      flex-direction:column;
      gap:12px;
    }
    .panel-feature{
      display:flex;
      align-items:center;
      gap:12px;
      padding:12px 16px;
      border-radius:12px;
      background:rgba(255,255,255,0.08);
      border:1px solid rgba(255,255,255,0.1);
      backdrop-filter:blur(4px);
    }
    .panel-feature-icon{
      width:36px; height:36px;
      border-radius:9px;
      background:rgba(255,138,31,0.2);
      border:1px solid rgba(255,138,31,0.3);
      display:flex; align-items:center; justify-content:center;
      font-size:1rem;
      flex-shrink:0;
    }
    .panel-feature p{
      margin:0;
      font-size:0.85rem;
      color:rgba(255,255,255,0.75);
      line-height:1.4;
      max-width:none;
    }
    .panel-feature strong{
      color:#fff;
      display:block;
      font-size:0.88rem;
      margin-bottom:2px;
    }

    .panel-footer{
      position:relative;
      z-index:1;
      font-size:0.78rem;
      color:rgba(255,255,255,0.3);
    }

    /* ── Right form panel ── */
    .auth-form-side{
      display:flex;
      align-items:center;
      justify-content:center;
      padding:48px 40px;
      background:#fff;
    }

    .auth-form-box{
      width:100%;
      max-width:400px;
    }

    .auth-form-box .auth-top{
      margin-bottom:36px;
    }
    .auth-form-box .auth-top h1{
      font-size:1.9rem;
      font-weight:900;
      color:var(--primary);
      letter-spacing:-0.4px;
      margin-bottom:6px;
    }
    .auth-form-box .auth-top p{
      color:var(--muted);
      font-size:0.9rem;
    }

    /* form inputs */
    .auth-field{
      margin-bottom:18px;
    }
    .auth-field label{
      display:block;
      font-weight:800;
      font-size:0.88rem;
      color:#333;
      margin-bottom:7px;
    }
    .auth-field input{
      width:100%;
      padding:13px 16px;
      border-radius:14px;
      border:1.5px solid var(--border);
      font-size:0.95rem;
      font-family:inherit;
      background:#fff;
      color:var(--text);
      outline:none;
      transition:border-color 0.18s, box-shadow 0.18s;
    }
    .auth-field input:focus{
      border-color:rgba(123,43,106,0.6);
      box-shadow:0 0 0 4px rgba(123,43,106,0.1);
    }
    .auth-field input.has-error{
      border-color:#b00020;
      box-shadow:0 0 0 3px rgba(176,0,32,0.08);
    }
    .auth-field .err{
      margin:6px 0 0;
      font-size:0.8rem;
      font-weight:700;
      color:#b00020;
      min-height:16px;
    }

    /* general error */
    .auth-general-err{
      background:#fff0f0;
      border:1px solid rgba(176,0,32,0.2);
      border-radius:10px;
      padding:10px 14px;
      font-size:0.85rem;
      font-weight:700;
      color:#b00020;
      margin-bottom:18px;
      display:none;
    }
    .auth-general-err.visible{ display:block; }

    /* info message (not error) */
    .auth-info-msg{
      background:var(--soft);
      border:1px solid rgba(123,43,106,0.2);
      border-radius:10px;
      padding:10px 14px;
      font-size:0.85rem;
      font-weight:700;
      color:var(--primary);
      margin-bottom:18px;
      display:none;
    }
    .auth-info-msg.visible{ display:block; }

    /* submit button */
    .auth-submit{
      width:100%;
      padding:14px;
      border-radius:14px;
      border:none;
      background:var(--primary);
      color:#fff;
      font-size:1rem;
      font-weight:900;
      cursor:pointer;
      transition:filter 0.18s, transform 0.12s;
      margin-top:6px;
      letter-spacing:0.2px;
    }
    .auth-submit:hover{ filter:brightness(1.07); }
    .auth-submit:active{ transform:scale(0.985); }
    .auth-submit:disabled{ opacity:0.6; cursor:not-allowed; filter:none; }

    /* divider */
    .auth-divider{
      display:flex;
      align-items:center;
      gap:12px;
      margin:22px 0;
      color:var(--muted);
      font-size:0.8rem;
    }
    .auth-divider::before,
    .auth-divider::after{
      content:"";
      flex:1;
      height:1px;
      background:var(--border);
    }

    /* demo credentials card */
    .auth-demo{
      border-radius:12px;
      border:1px solid rgba(123,43,106,0.15);
      background:var(--soft);
      padding:14px 16px;
    }
    .auth-demo p{
      margin:0 0 4px;
      font-size:0.82rem;
      color:var(--muted);
    }
    .auth-demo p:last-child{ margin:0; }
    .auth-demo strong{ color:var(--primary); }
    .auth-demo code{
      background:rgba(123,43,106,0.1);
      border-radius:5px;
      padding:1px 6px;
      font-size:0.8rem;
      color:var(--primary);
      font-family:monospace;
    }

    /* switch link */
    .auth-switch{
      text-align:center;
      margin-top:22px;
      font-size:0.88rem;
      color:var(--muted);
    }
    .auth-switch a{
      color:var(--primary);
      font-weight:800;
      text-decoration:none;
    }
    .auth-switch a:hover{ text-decoration:underline; }

    /* success state */
    .auth-success{
      text-align:center;
      padding:20px 0;
    }
    .auth-success-icon{
      width:68px; height:68px;
      border-radius:50%;
      background:var(--primary);
      color:#fff;
      font-size:2rem;
      display:flex;
      align-items:center;
      justify-content:center;
      margin:0 auto 16px;
    }
    .auth-success h3{
      color:var(--primary);
      margin:0 0 8px;
      font-size:1.3rem;
    }
    .auth-success p{
      color:var(--muted);
      font-size:0.9rem;
    }

    /* ── Responsive ── */
    @media(max-width:860px){
      .auth-wrap{ grid-template-columns:1fr; }
      .auth-panel{ display:none; }
      .auth-form-side{ padding:32px 20px; min-height:100vh; }
    }
  </style>
</head>
<body>

<div class="auth-wrap">

  <!-- ── Left branded panel ── -->
  <div class="auth-panel">
    <a class="panel-logo" href="index.html">
      <img src="images/shipsmart-logo-3.svg" alt="ShipSmart logo">
      <span>ShipSmart</span>
    </a>

    <div class="panel-body">
      <h2>Track every shipment from <em>one place.</em></h2>
      <p>The universal tracker for Aramex, DHL, FedEx, and SMSA — with live status, ETA, and full shipment history.</p>

      <div class="panel-features">
        <div class="panel-feature">
          <div class="panel-feature-icon">📦</div>
          <div>
            <strong>Multi-Carrier Tracking</strong>
            <p>Aramex, DHL, FedEx, SMSA in one dashboard</p>
          </div>
        </div>
        <div class="panel-feature">
          <div class="panel-feature-icon">⚡</div>
          <div>
            <strong>Live Status Updates</strong>
            <p>Real-time checkpoints via TrackingMore API</p>
          </div>
        </div>
        <div class="panel-feature">
          <div class="panel-feature-icon">🛡️</div>
          <div>
            <strong>Secure & Role-Based</strong>
            <p>Admin dashboard with full shipment control</p>
          </div>
        </div>
      </div>
    </div>

    <div class="panel-footer">&copy; 2026 ShipSmart &mdash; CPCS 403 Project</div>
  </div>

  <!-- ── Right form panel ── -->
  <div class="auth-form-side">
    <div class="auth-form-box">

      <div class="auth-top">
        <h1>Welcome back</h1>
        <p>Sign in to your ShipSmart account</p>
      </div>

      <!-- Info message (shown when redirected from protected page) -->
      <div class="auth-info-msg" id="infoMsg"></div>

      <!-- General error -->
      <div class="auth-general-err" id="generalError"></div>

      <form id="loginForm" novalidate>

        <div class="auth-field">
          <label for="loginEmail">Email Address</label>
          <input type="email" id="loginEmail" name="email"
                 placeholder="e.g., sara@example.com" autocomplete="email" required>
          <p class="err" id="emailError"></p>
        </div>

        <div class="auth-field">
          <label for="loginPassword">Password</label>
          <input type="password" id="loginPassword" name="password"
                 placeholder="Your password" autocomplete="current-password" required>
          <p class="err" id="passwordError"></p>
        </div>

        <button class="auth-submit" type="submit" id="loginBtn">Sign In</button>
      </form>

      <div class="auth-divider">or</div>

      <!-- Demo credentials -->
      <div class="auth-demo">
        <p><strong>Demo admin:</strong> <code>admin@shipsmart.com</code> / <code>Admin@1234</code></p>
        <p>Register a new account to test the User role.</p>
      </div>

      <div class="auth-switch">
        No account yet? <a href="register.php">Create one</a>
      </div>

    </div>
  </div>

</div>

<script src="scripts/main.js"></script>
<script>
(function () {
  "use strict";

  const form       = document.getElementById("loginForm");
  const btn        = document.getElementById("loginBtn");
  const generalErr = document.getElementById("generalError");
  const infoMsg    = document.getElementById("infoMsg");
  const emailErr   = document.getElementById("emailError");
  const pwErr      = document.getElementById("passwordError");
  const emailInput = document.getElementById("loginEmail");
  const pwInput    = document.getElementById("loginPassword");

  const clrAll = () => {
    generalErr.textContent = ""; generalErr.classList.remove("visible");
    emailErr.textContent   = ""; emailInput.classList.remove("has-error");
    pwErr.textContent      = ""; pwInput.classList.remove("has-error");
  };

  const showErr = (el, input, msg) => {
    el.textContent = msg;
    if (input) input.classList.add("has-error");
  };

  // Show hint if redirected here from a protected page
  const params = new URLSearchParams(window.location.search);
  if (params.get("redirect")) {
    infoMsg.textContent = "Please sign in to access that page.";
    infoMsg.classList.add("visible");
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    clrAll();

    const email = emailInput.value.trim();
    const pw    = pwInput.value;
    let ok = true;

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
      showErr(emailErr, emailInput, "Please enter a valid email address."); ok = false;
    }
    if (!pw) {
      showErr(pwErr, pwInput, "Password is required."); ok = false;
    }
    if (!ok) return;

    btn.disabled    = true;
    btn.textContent = "Signing in…";

    fetch("../api/login.php", { method: "POST", body: new FormData(form) })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          btn.textContent = "Redirecting…";
          window.location.href = data.redirect;
        } else {
          if (data.errors) {
            if (data.errors.general) {
              generalErr.textContent = data.errors.general;
              generalErr.classList.add("visible");
            }
            if (data.errors.email)    showErr(emailErr, emailInput, data.errors.email);
            if (data.errors.password) showErr(pwErr,    pwInput,    data.errors.password);
          } else {
            generalErr.textContent = data.message || "Login failed.";
            generalErr.classList.add("visible");
          }
        }
      })
      .catch(() => {
        generalErr.textContent = "Network error. Please try again.";
        generalErr.classList.add("visible");
      })
      .finally(() => {
        if (btn.textContent !== "Redirecting…") {
          btn.disabled = false;
          btn.textContent = "Sign In";
        }
      });
  });

  // Real-time input border reset
  [emailInput, pwInput].forEach(input => {
    input.addEventListener("input", () => input.classList.remove("has-error"));
  });
})();
</script>
</body>
</html>