<?php
/*
 * File: register.php
 * Purpose: Registration page — split layout matching login.php design.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShipSmart | Create Account</title>
  <link rel="stylesheet" href="global/main.css">
  <style>
    *{ box-sizing:border-box; margin:0; padding:0; }

    body{
      min-height:100vh;
      display:flex;
      flex-direction:column;
      background:#fff;
    }

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
    .auth-panel::before{
      content:"";
      position:absolute;
      inset:0;
      background-image:radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
      background-size:28px 28px;
      pointer-events:none;
    }
    .auth-panel::after{
      content:"";
      position:absolute;
      width:480px; height:480px;
      border-radius:50%;
      background:radial-gradient(circle at 40% 40%, rgba(255,138,31,0.22), transparent 60%),
                 radial-gradient(circle at 70% 70%, rgba(123,43,106,0.35), transparent 55%);
      bottom:-140px; right:-120px;
      pointer-events:none;
    }

    .panel-logo{
      display:flex; align-items:center; gap:12px;
      position:relative; z-index:1; text-decoration:none;
    }
    .panel-logo img{
      width:44px; height:44px;
      filter:brightness(0) invert(1); opacity:0.95;
    }
    .panel-logo span{
      font-size:1.2rem; font-weight:900; color:#fff; letter-spacing:-0.3px;
    }

    .panel-body{ position:relative; z-index:1; }
    .panel-body h2{
      font-size:clamp(1.8rem, 2.8vw, 2.6rem);
      font-weight:900; color:#fff;
      line-height:1.1; letter-spacing:-0.5px; margin-bottom:16px;
    }
    .panel-body h2 em{ font-style:normal; color:var(--accent); }
    .panel-body p{
      color:rgba(255,255,255,0.6); font-size:0.95rem;
      line-height:1.65; max-width:32ch; margin-bottom:32px;
    }

    /* steps */
    .panel-steps{ display:flex; flex-direction:column; gap:16px; }
    .panel-step{
      display:flex; align-items:flex-start; gap:14px;
    }
    .step-num{
      width:32px; height:32px; border-radius:50%;
      background:rgba(255,138,31,0.25);
      border:1px solid rgba(255,138,31,0.4);
      color:var(--accent);
      font-weight:900; font-size:0.85rem;
      display:flex; align-items:center; justify-content:center;
      flex-shrink:0; margin-top:2px;
    }
    .step-text strong{ color:#fff; font-size:0.9rem; display:block; margin-bottom:2px; }
    .step-text p{ color:rgba(255,255,255,0.55); font-size:0.82rem; margin:0; max-width:none; }

    .panel-footer{
      position:relative; z-index:1;
      font-size:0.78rem; color:rgba(255,255,255,0.3);
    }

    /* ── Right form ── */
    .auth-form-side{
      display:flex; align-items:center; justify-content:center;
      padding:48px 40px; background:#fff; overflow-y:auto;
    }
    .auth-form-box{ width:100%; max-width:400px; }

    .auth-top{ margin-bottom:28px; }
    .auth-top h1{
      font-size:1.9rem; font-weight:900; color:var(--primary);
      letter-spacing:-0.4px; margin-bottom:6px;
    }
    .auth-top p{ color:var(--muted); font-size:0.9rem; }

    .auth-field{ margin-bottom:16px; }
    .auth-field label{
      display:block; font-weight:800; font-size:0.88rem;
      color:#333; margin-bottom:7px;
    }
    .auth-field input{
      width:100%; padding:13px 16px; border-radius:14px;
      border:1.5px solid var(--border);
      font-size:0.95rem; font-family:inherit;
      background:#fff; color:var(--text); outline:none;
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
      margin:5px 0 0; font-size:0.79rem;
      font-weight:700; color:#b00020; min-height:15px;
    }

    /* password strength bar */
    .pw-strength{
      height:3px; border-radius:999px;
      background:var(--border); margin-top:7px; overflow:hidden;
    }
    .pw-strength-bar{
      height:100%; width:0%; border-radius:999px;
      transition:width 0.3s ease, background 0.3s ease;
    }
    .pw-hint{
      font-size:0.75rem; color:var(--muted);
      margin-top:4px; min-height:14px;
    }

    .auth-general-err{
      background:#fff0f0; border:1px solid rgba(176,0,32,0.2);
      border-radius:10px; padding:10px 14px;
      font-size:0.85rem; font-weight:700; color:#b00020;
      margin-bottom:16px; display:none;
    }
    .auth-general-err.visible{ display:block; }

    .auth-submit{
      width:100%; padding:14px; border-radius:14px; border:none;
      background:var(--primary); color:#fff;
      font-size:1rem; font-weight:900; cursor:pointer;
      transition:filter 0.18s, transform 0.12s;
      margin-top:6px; letter-spacing:0.2px;
    }
    .auth-submit:hover{ filter:brightness(1.07); }
    .auth-submit:active{ transform:scale(0.985); }
    .auth-submit:disabled{ opacity:0.6; cursor:not-allowed; filter:none; }

    .auth-terms{
      font-size:0.78rem; color:var(--muted);
      text-align:center; margin-top:12px; line-height:1.5;
    }

    .auth-switch{
      text-align:center; margin-top:20px;
      font-size:0.88rem; color:var(--muted);
    }
    .auth-switch a{
      color:var(--primary); font-weight:800; text-decoration:none;
    }
    .auth-switch a:hover{ text-decoration:underline; }

    /* success */
    .auth-success{
      text-align:center; padding:10px 0;
    }
    .auth-success-icon{
      width:68px; height:68px; border-radius:50%;
      background:var(--primary); color:#fff;
      font-size:2rem; display:flex;
      align-items:center; justify-content:center; margin:0 auto 16px;
    }
    .auth-success h3{ color:var(--primary); margin:0 0 8px; font-size:1.3rem; }
    .auth-success p{ color:var(--muted); font-size:0.9rem; }

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
      <h2>Start tracking in <em>60 seconds.</em></h2>
      <p>Create your free account and get instant access to live shipment tracking across all major Saudi carriers.</p>

      <div class="panel-steps">
        <div class="panel-step">
          <div class="step-num">1</div>
          <div class="step-text">
            <strong>Create your account</strong>
            <p>Fill in your name, email, and a secure password</p>
          </div>
        </div>
        <div class="panel-step">
          <div class="step-num">2</div>
          <div class="step-text">
            <strong>Sign in</strong>
            <p>Access your personal tracking dashboard</p>
          </div>
        </div>
        <div class="panel-step">
          <div class="step-num">3</div>
          <div class="step-text">
            <strong>Track anything</strong>
            <p>Enter any tracking number and get live status</p>
          </div>
        </div>
      </div>
    </div>

    <div class="panel-footer">&copy; 2026 ShipSmart &mdash; CPCS 403 Project</div>
  </div>

  <!-- ── Right form ── -->
  <div class="auth-form-side">
    <div class="auth-form-box">

      <div class="auth-top">
        <h1>Create account</h1>
        <p>Join ShipSmart — it's free and takes under a minute</p>
      </div>

      <div class="auth-general-err" id="generalError"></div>

      <form id="registerForm" novalidate>

        <div class="auth-field">
          <label for="regName">Full Name</label>
          <input type="text" id="regName" name="full_name"
                 placeholder="e.g., Sara Ahmed"
                 autocomplete="name" required>
          <p class="err" id="nameError"></p>
        </div>

        <div class="auth-field">
          <label for="regEmail">Email Address</label>
          <input type="email" id="regEmail" name="email"
                 placeholder="e.g., sara@example.com"
                 autocomplete="email" required>
          <p class="err" id="emailError"></p>
        </div>

        <div class="auth-field">
          <label for="regPassword">Password</label>
          <input type="password" id="regPassword" name="password"
                 placeholder="Min 8 chars, 1 uppercase, 1 number"
                 autocomplete="new-password" required>
          <div class="pw-strength">
            <div class="pw-strength-bar" id="pwBar"></div>
          </div>
          <p class="pw-hint" id="pwHint"></p>
          <p class="err" id="passwordError"></p>
        </div>

        <div class="auth-field">
          <label for="regConfirm">Confirm Password</label>
          <input type="password" id="regConfirm" name="confirm"
                 placeholder="Repeat your password"
                 autocomplete="new-password" required>
          <p class="err" id="confirmError"></p>
        </div>

        <button class="auth-submit" type="submit" id="registerBtn">
          Create Account
        </button>

        <p class="auth-terms">
          By registering you agree to use this for CPCS 403 course purposes.
        </p>
      </form>

      <!-- Success state -->
      <div id="registerSuccess" hidden class="auth-success">
        <div class="auth-success-icon">✓</div>
        <h3>Account Created!</h3>
        <p>Redirecting you to sign in…</p>
      </div>

      <div class="auth-switch">
        Already have an account? <a href="login.php">Sign in</a>
      </div>

    </div>
  </div>

</div>

<script src="scripts/main.js"></script>
<script>
(function () {
  "use strict";

  const form       = document.getElementById("registerForm");
  const btn        = document.getElementById("registerBtn");
  const successBox = document.getElementById("registerSuccess");
  const generalErr = document.getElementById("generalError");

  const fields = {
    full_name: { input: document.getElementById("regName"),     err: document.getElementById("nameError") },
    email:     { input: document.getElementById("regEmail"),    err: document.getElementById("emailError") },
    password:  { input: document.getElementById("regPassword"), err: document.getElementById("passwordError") },
    confirm:   { input: document.getElementById("regConfirm"),  err: document.getElementById("confirmError") },
  };

  const pwBar  = document.getElementById("pwBar");
  const pwHint = document.getElementById("pwHint");

  const setErr = (key, msg) => {
    fields[key].err.textContent = msg;
    fields[key].input.classList.toggle("has-error", !!msg);
  };
  const clrAll = () => {
    Object.keys(fields).forEach(k => setErr(k, ""));
    generalErr.textContent = "";
    generalErr.classList.remove("visible");
  };

  // Password strength meter
  fields.password.input.addEventListener("input", () => {
    const pw = fields.password.input.value;
    let score = 0;
    if (pw.length >= 8)          score++;
    if (/[A-Z]/.test(pw))        score++;
    if (/[0-9]/.test(pw))        score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const colors = ["#e74c3c","#e67e22","#f1c40f","#2ecc71"];
    const hints  = ["Too short","Add uppercase & numbers","Almost there","Strong password ✓"];
    const widths = ["25%","50%","75%","100%"];

    if (pw.length === 0) {
      pwBar.style.width = "0%";
      pwHint.textContent = "";
    } else {
      const i = Math.min(score - 1, 3);
      pwBar.style.width      = widths[i] || "25%";
      pwBar.style.background = colors[i] || colors[0];
      pwHint.textContent     = hints[i]  || hints[0];
      pwHint.style.color     = colors[i] || colors[0];
    }
  });

  const validateClient = () => {
    clrAll();
    let ok = true;
    const name  = fields.full_name.input.value.trim();
    const email = fields.email.input.value.trim();
    const pw    = fields.password.input.value;
    const cf    = fields.confirm.input.value;

    if (name.length < 2) {
      setErr("full_name", "Full name must be at least 2 characters."); ok = false;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
      setErr("email", "Please enter a valid email address."); ok = false;
    }
    if (pw.length < 8 || !/[A-Z]/.test(pw) || !/[0-9]/.test(pw)) {
      setErr("password", "Min 8 chars, 1 uppercase letter, 1 number."); ok = false;
    }
    if (pw !== cf) {
      setErr("confirm", "Passwords do not match."); ok = false;
    }
    return ok;
  };

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    if (!validateClient()) return;

    btn.disabled    = true;
    btn.textContent = "Creating account…";

    fetch("../api/register.php", { method: "POST", body: new FormData(form) })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          form.hidden      = true;
          successBox.hidden = false;
          setTimeout(() => window.location.href = "login.php", 1800);
        } else {
          if (data.errors) {
            Object.entries(data.errors).forEach(([k, v]) => {
              if (fields[k]) setErr(k, v);
              else {
                generalErr.textContent = v;
                generalErr.classList.add("visible");
              }
            });
          } else {
            generalErr.textContent = data.message || "Something went wrong.";
            generalErr.classList.add("visible");
          }
        }
      })
      .catch(() => {
        generalErr.textContent = "Network error. Please try again.";
        generalErr.classList.add("visible");
      })
      .finally(() => { btn.disabled = false; btn.textContent = "Create Account"; });
  });

  // Reset border on type
  Object.values(fields).forEach(({ input }) => {
    input.addEventListener("input", () => input.classList.remove("has-error"));
  });
})();
</script>
</body>
</html>