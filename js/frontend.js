/*global CSS */
'use strict';

/**
 * rateIt public vote without page reload.
 */
(() => {
  let config = {};
  try {
    config = JSON.parse(document.getElementById('rateit-data')?.textContent || '{}');
  } catch {
    config = {};
  }

  const update = (rsp) => {
    if (!rsp.item || !rsp.values) {
      return;
    }
    const item = CSS.escape(rsp.item);

    // Update values of this item everywhere in page
    for (const el of document.querySelectorAll(`[data-rateit-item="${item}"][data-rateit-field]`)) {
      const field = el.dataset.rateitField;
      if (field === 'total' && el.dataset.none !== undefined) {
        const n = rsp.total;
        el.textContent = (n === 0 ? el.dataset.none : n === 1 ? el.dataset.one : el.dataset.more).replace('%d', n);
      } else if (rsp.values[field] !== undefined) {
        el.textContent = rsp.values[field];
      }
    }

    // Update stars and disable forms of this item
    const note = Math.round(parseFloat(rsp.values.note));
    for (const form of document.querySelectorAll(`form.rateit-linker[data-rateit-item="${item}"]`)) {
      for (const input of form.querySelectorAll('input[name="note"]')) {
        input.checked = Number.parseInt(input.value, 10) === note;
      }
      for (const el of form.querySelectorAll('input[name="note"], button')) {
        el.disabled = true;
      }
      form.querySelector('.rateit-submit')?.remove();
      form.classList.add('rateit-voted');
    }
  };

  const send = (form, note) => {
    if (form.classList.contains('rateit-loading') || form.classList.contains('rateit-voted')) {
      return;
    }
    const body = new FormData(form);
    body.set('note', note);
    body.set('json', '1');

    const message = form.querySelector('.rateit-message');
    form.classList.add('rateit-loading');

    fetch(form.action, { method: 'POST', body, credentials: 'same-origin' })
      .then((response) => response.json())
      .then((rsp) => {
        update(rsp);
        if (message) {
          message.textContent = rsp.message || '';
        }
      })
      .catch(() => {
        if (message) {
          message.textContent = config.failed || '';
        }
      })
      .finally(() => form.classList.remove('rateit-loading'));
  };

  for (const form of document.querySelectorAll('form.rateit-linker')) {
    form.classList.add('rateit-js');

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const note = event.submitter?.value || form.querySelector('input[name="note"]:checked')?.value;
      if (note) {
        send(form, note);
      }
    });

    for (const input of form.querySelectorAll('input[name="note"]')) {
      input.addEventListener('change', () => {
        if (!input.disabled) {
          send(form, input.value);
        }
      });
    }
  }
})();
