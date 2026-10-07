/* New Life Fitness Club — shared client-side JS */

/* ---------- Delete confirmation ---------- */
document.addEventListener('click', function (e) {
  const link = e.target.closest('[data-confirm]');
  if (!link) return;
  const msg = link.getAttribute('data-confirm') || 'Are you sure you want to delete this record? This cannot be undone.';
  if (!confirm(msg)) e.preventDefault();
});

/* ---------- Form validation helper ---------- */
window.validateForm = function (form) {
  let ok = true;
  form.querySelectorAll('[required]').forEach(function (field) {
    const wrap = field.closest('.form-field');
    let bad = false;
    if (field.type === 'checkbox') { bad = !field.checked; }
    else if (field.tagName === 'SELECT') { bad = (field.value === '' || field.value === null); }
    else { bad = (field.value.trim() === ''); }

    if (bad) {
      ok = false;
      if (wrap && !wrap.querySelector('.field-err')) {
        const err = document.createElement('span');
        err.className = 'field-err';
        err.style.cssText = 'color:#d64545;font-size:12px;margin-top:2px;';
        err.textContent = 'This field is required.';
        wrap.appendChild(err);
        field.style.borderColor = '#d64545';
      }
    }
  });
  /* email format if present */
  form.querySelectorAll('input[type=email]').forEach(function (f) {
    if (f.value.trim() && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(f.value.trim())) {
      ok = false;
      const wrap = f.closest('.form-field');
      if (wrap && !wrap.querySelector('.field-err')) {
        const err = document.createElement('span');
        err.className = 'field-err';
        err.style.cssText = 'color:#d64545;font-size:12px;margin-top:2px;';
        err.textContent = 'Please enter a valid email address.';
        wrap.appendChild(err);
      }
    }
  });
  return ok;
};

/* clear field errors on input */
document.addEventListener('input', function (e) {
  const wrap = e.target.closest('.form-field');
  if (wrap) {
    const err = wrap.querySelector('.field-err');
    if (err) { err.remove(); e.target.style.borderColor = ''; }
  }
});

/* attach validation to forms with data-validate */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.validateForm(form)) e.preventDefault();
    });
  });
});

/* ---------- Live table search ---------- */
window.initTableSearch = function (inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function () {
    const q = input.value.trim().toLowerCase();
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      const hit = tr.textContent.toLowerCase().indexOf(q) !== -1;
      tr.style.display = hit ? '' : 'none';
    });
  });
};

/* ---------- Table sorting ---------- */
window.initTableSort = function (tableId) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const headers = table.querySelectorAll('th:not(.no-sort)');
  headers.forEach(function (th, idx) {
    th.addEventListener('click', function () {
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const asc = th.dataset.sort === 'asc' ? false : true;
      th.dataset.sort = asc ? 'asc' : 'desc';
      headers.forEach(function (h) { h.classList.remove('sort-asc', 'sort-desc'); });
      th.classList.add(asc ? 'sort-asc' : 'sort-desc');
      const col = Array.from(th.parentNode.children).indexOf(th);
      rows.sort(function (a, b) {
        let av = a.children[col] ? a.children[col].textContent.trim() : '';
        let bv = b.children[col] ? b.children[col].textContent.trim() : '';
        const an = parseFloat(av.replace(/[^0-9.\-]/g, ''));
        const bn = parseFloat(bv.replace(/[^0-9.\-]/g, ''));
        if (!isNaN(an) && !isNaN(bn)) { av = an; bv = bn; }
        if (av < bv) return asc ? -1 : 1;
        if (av > bv) return asc ? 1 : -1;
        return 0;
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
    });
  });
};

/* ---------- Auto-init via data attributes ---------- */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('table[data-sortable]').forEach(function (t) { window.initTableSort(t.id); });
});

/* ---------- Admin responsive sidebar toggle ---------- */
/* Wire the .sidebar-toggle button (admin topbar) to open/close the fixed
   sidebar below 900px. A click on the backdrop or any nav link closes it.
   On desktop the button is display:none, so this stays dormant. */
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.querySelector('.sidebar-toggle');
  var sidebar = document.querySelector('.sidebar');
  if (!btn || !sidebar) return;

  /* backdrop is injected once, just before .main, so it sits under the sidebar */
  var backdrop = document.createElement('div');
  backdrop.className = 'sidebar-backdrop';
  sidebar.parentNode.insertBefore(backdrop, sidebar.nextSibling);

  function setOpen(open) {
    sidebar.classList.toggle('open', open);
    backdrop.classList.toggle('show', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  btn.addEventListener('click', function () { setOpen(!sidebar.classList.contains('open')); });
  backdrop.addEventListener('click', function () { setOpen(false); });
  sidebar.addEventListener('click', function (e) {
    if (e.target.closest('a')) setOpen(false);   /* navigating closes the drawer */
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });
});

/* ---------- Flash auto-dismiss ---------- */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.alert.flash').forEach(function (a) {
    setTimeout(function () { a.style.transition = 'opacity .5s'; a.style.opacity = '0'; setTimeout(function(){ a.remove(); }, 500); }, 4000);
  });
});

/* ---------- Number / currency formatting ---------- */
window.fmtMoney = function (n, sym) {
  sym = sym || '₹';
  const num = Number(n) || 0;
  return sym + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

/* =====================================================================
   OFFERS & EMAIL CAMPAIGN MODULE
   ===================================================================== */

/* ---------- Live poster image preview before upload ----------
   Usage: <input type="file" id="posterInput" ...>
          <div id="posterPreview" class="poster-preview"><img id="posterPreviewImg" ...></div>
   Auto-initialises any [data-poster-input] / [data-poster-preview] pair. */
window.initPosterPreview = function (inputId, previewId, imgId) {
  const input = document.getElementById(inputId);
  const box   = document.getElementById(previewId);
  const img   = document.getElementById(imgId);
  if (!input || !box) return;
  input.addEventListener('change', function (e) {
    const file = e.target.files && e.target.files[0];
    if (file && file.type && file.type.match(/^image\//)) {
      if (img) { img.src = URL.createObjectURL(file); }
      box.style.display = 'flex';
    } else {
      box.style.display = 'none';
    }
  });
};
window.clearPosterPreview = function (inputId, previewId) {
  const input = document.getElementById(inputId);
  const box   = document.getElementById(previewId);
  if (input) input.value = '';
  if (box)   box.style.display = 'none';
};

/* ---------- Campaign batch-progress poller ----------
   Polls a batch-processing endpoint and updates a live progress bar.
   Required element IDs: barId, pctId, sentId, failedId, totalId, statusId, doneBoxId, doneAlertId
   endpoint: URL to the process.php AJAX worker (e.g. 'process.php?campaign=N&batch=5') */
window.initCampaignProgress = function (opts) {
  const endpoint    = opts.endpoint;
  const barId       = opts.barId       || 'progressBar';
  const pctId       = opts.pctId       || 'pctText';
  const sentId      = opts.sentId      || 'sentText';
  const failedId    = opts.failedId    || 'failedText';
  const totalId     = opts.totalId     || 'totalText';
  const statusId    = opts.statusId    || 'progressStatus';
  const doneBoxId   = opts.doneBoxId   || 'doneBox';
  const doneAlertId = opts.doneAlertId || 'doneAlert';
  const onComplete  = opts.onComplete  || null;
  let done = false;

  function poll() {
    if (done) return;
    fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || d.error) { setTimeout(poll, 1500); return; }
        const pct = d.percent || 0;
        const bar = document.getElementById(barId);
        if (bar) bar.style.width = pct + '%';
        const pctEl = document.getElementById(pctId);     if (pctEl) pctEl.textContent = pct + '%';
        const sentEl = document.getElementById(sentId);   if (sentEl) sentEl.textContent = (d.sent_total != null ? d.sent_total : (d.processed || 0));
        const failEl = document.getElementById(failedId); if (failEl) failEl.textContent = (d.failed_total != null ? d.failed_total : 0);
        const totEl  = document.getElementById(totalId);  if (totEl)  totEl.textContent = d.total;
        const stEl   = document.getElementById(statusId);
        if (stEl) {
          stEl.textContent = d.status;
          stEl.className = 'badge ' + (d.status === 'Completed' ? 'green' : d.status === 'Failed' ? 'red' : 'steel');
        }
        if (d.done) {
          done = true;
          if (bar) bar.style.width = '100%';
          const pctEl2 = document.getElementById(pctId); if (pctEl2) pctEl2.textContent = '100%';
          const box = document.getElementById(doneBoxId);
          const alert = document.getElementById(doneAlertId);
          if (box) box.style.display = 'block';
          if (alert) {
            const sent = d.sent_total || 0, failed = d.failed_total || 0, total = d.total || 0;
            if (failed === 0) {
              alert.className = 'alert ok';
              alert.innerHTML = '✅ Campaign complete! <strong>' + sent + '</strong> of ' + total +
                ' email(s) sent successfully to active members. No failures.';
            } else {
              alert.className = 'alert warn';
              alert.innerHTML = '⚠️ Campaign finished. <strong>' + sent + '</strong> sent, <strong>' + failed +
                '</strong> failed. You can re-send the failed emails from the offer page or campaign dashboard.';
            }
          }
          if (typeof onComplete === 'function') onComplete(d);
        } else {
          setTimeout(poll, 1200);
        }
      })
      .catch(function () { setTimeout(poll, 2000); });
  }
  poll();
};

/* ---------- Schedule send-mode toggle ----------
   Toggles visibility of the schedule datetime field based on radio selection. */
window.initScheduleToggle = function (radioName, scheduleBoxId, scheduleInputId) {
  const radios = document.querySelectorAll('input[name="' + radioName + '"]');
  const box    = document.getElementById(scheduleBoxId);
  const inp    = document.getElementById(scheduleInputId);
  if (!radios.length || !box) return;
  function update() {
    let sched = false;
    radios.forEach(function (r) { if (r.checked && r.value === 'schedule') sched = true; });
    box.style.display = sched ? 'block' : 'none';
    if (inp) inp.required = sched;
  }
  radios.forEach(function (r) { r.addEventListener('change', update); });
  update();
};

/* ---------- Offer card client-side search ----------
   Filters .offer-card elements by their data-search attribute. */
window.initOfferCardSearch = function (inputId, gridId) {
  const input = document.getElementById(inputId);
  const grid  = document.getElementById(gridId);
  if (!input || !grid) return;
  input.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    grid.querySelectorAll('.offer-card').forEach(function (card) {
      const hay = card.getAttribute('data-search') || '';
      card.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
  });
};

/* ---------- Auto-init offer features via data attributes ---------- */
document.addEventListener('DOMContentLoaded', function () {
  /* poster preview auto-init */
  document.querySelectorAll('[data-poster-input]').forEach(function (el) {
    const inputId  = el.getAttribute('data-poster-input');
    const previewId = el.getAttribute('data-poster-preview') || 'posterPreview';
    const imgId    = el.getAttribute('data-poster-img') || 'posterPreviewImg';
    window.initPosterPreview(inputId, previewId, imgId);
  });
  /* schedule toggle auto-init */
  const schedToggle = document.querySelector('[data-schedule-toggle]');
  if (schedToggle) {
    window.initScheduleToggle(
      schedToggle.getAttribute('data-schedule-toggle'),
      schedToggle.getAttribute('data-schedule-box') || 'scheduleBox',
      schedToggle.getAttribute('data-schedule-input') || 'scheduleAt'
    );
  }
  /* offer card search auto-init */
  const offerSearch = document.querySelector('[data-offer-search]');
  if (offerSearch) {
    window.initOfferCardSearch(offerSearch.id, offerSearch.getAttribute('data-offer-grid') || 'offerGrid');
  }
});
