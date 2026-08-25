(() => {
  'use strict';

  const CONFIG = {
    parceriaId: 37061,
    statusId: 1,
    cdaId: null,
    whatsapp: '17997054178'
  };

  window.dataLayer = window.dataLayer || [];

  const $ = (selector) => document.querySelector(selector);
  const form = $('#leadForm');
  const phone = $('#telefone');
  const send = $('#send');
  const status = $('#status');
  const whatsapp = $('#whatsapp');

  function getCookie(name) {
    const prefix = name + '=';
    const parts = document.cookie ? document.cookie.split(';') : [];
    for (const part of parts) {
      const value = part.trim();
      if (value.startsWith(prefix)) return decodeURIComponent(value.slice(prefix.length));
    }
    return '';
  }

  function newEventId(prefix) {
    const uuid = (window.crypto && typeof window.crypto.randomUUID === 'function')
      ? window.crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
    return `${prefix}-${uuid}`;
  }

  function attribution() {
    const params = new URLSearchParams(window.location.search);
    const fbclid = params.get('fbclid') || '';
    let fbc = getCookie('_fbc');

    if (!fbc && fbclid) {
      fbc = `fb.1.${Date.now()}.${fbclid}`;
    }

    return {
      event_source_url: window.location.href,
      page_referrer: document.referrer || '',
      fbp: getCookie('_fbp'),
      fbc,
      fbclid,
      gclid: params.get('gclid') || '',
      gbraid: params.get('gbraid') || '',
      wbraid: params.get('wbraid') || '',
      msclkid: params.get('msclkid') || '',
      utm_source: params.get('utm_source') || '',
      utm_medium: params.get('utm_medium') || '',
      utm_campaign: params.get('utm_campaign') || '',
      utm_content: params.get('utm_content') || '',
      utm_term: params.get('utm_term') || ''
    };
  }

  function pushEvent(event, extra = {}) {
    window.dataLayer.push({
      event,
      parceria_id: CONFIG.parceriaId,
      unidade: 'KNN Barretos',
      ...extra
    });
  }

  function sendMetaServerEvent(payload) {
    const body = JSON.stringify(payload);

    try {
      if (navigator.sendBeacon) {
        const blob = new Blob([body], { type: 'application/json' });
        if (navigator.sendBeacon('/api/meta-event', blob)) return;
      }
    } catch (_) {}

    fetch('/api/meta-event', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json;charset=UTF-8' },
      body,
      keepalive: true
    }).catch(() => {});
  }

  pushEvent('tracking_context', attribution());

  if (whatsapp) {
    whatsapp.addEventListener('click', () => {
      const eventId = newEventId('contact');
      const tracking = attribution();

      pushEvent('whatsapp_click', {
        event_id: eventId,
        contact_method: 'whatsapp',
        button_location: 'header',
        ...tracking
      });

      sendMetaServerEvent({
        event_name: 'Contact',
        event_id: eventId,
        ...tracking,
        custom_data: {
          content_name: 'WhatsApp KNN Barretos',
          contact_method: 'whatsapp'
        }
      });

      window.open('https://wa.me/55' + CONFIG.whatsapp, '_blank', 'noopener,noreferrer');
    });
  }

  function formatPhone(value) {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    if (!digits) return '';
    if (digits.length <= 2) return `(${digits}`;
    if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
  }

  if (phone) {
    phone.addEventListener('input', (event) => {
      event.target.value = formatPhone(event.target.value);
    });
  }

  let started = false;
  if (form) {
    form.addEventListener('input', () => {
      if (started) return;
      started = true;
      pushEvent('form_start', attribution());
    });
  }

  function invalid(name, on) {
    const field = document.querySelector(`[data-field="${name}"]`);
    if (field) field.classList.toggle('invalid', on);
  }

  function validate() {
    const nome = $('#nome').value.trim();
    const email = $('#email').value.trim();
    const telefone = $('#telefone').value.trim();
    const idade = $('#idade').value.trim();

    const tests = {
      nome: !!nome,
      email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email),
      telefone: telefone.replace(/\D/g, '').length >= 10,
      idade: Number(idade) > 0 && Number(idade) <= 120
    };

    Object.entries(tests).forEach(([key, valid]) => invalid(key, !valid));
    return Object.values(tests).every(Boolean);
  }

  if (form) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      status.textContent = '';
      status.className = 'status';

      if (!validate()) return;

      const eventId = newEventId('lead');
      const tracking = attribution();

      const payload = {
        cda_id: null,
        email: $('#email').value.trim(),
        idade: $('#idade').value.trim(),
        nome: $('#nome').value.trim(),
        parceria_id: CONFIG.parceriaId,
        status_id: CONFIG.statusId,
        telefone: $('#telefone').value.trim(),
        event_id: eventId,
        ...tracking
      };

      send.disabled = true;
      send.textContent = 'Enviando...';

      try {
        const response = await fetch('/api/lead', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json;charset=UTF-8' },
          body: JSON.stringify(payload)
        });

        const raw = await response.text();
        let result = raw;
        try { result = JSON.parse(raw); } catch (_) {}

        if (!response.ok || result !== true) throw new Error('Falha no cadastro');

        pushEvent('lead', {
          event_id: eventId,
          conversion_type: 'formulario',
          ...tracking
        });

        status.textContent = 'Dados enviados com sucesso.';
        status.className = 'status ok';
        form.reset();
        started = false;
      } catch (error) {
        console.error(error);
        pushEvent('lead_error', {
          event_id: eventId,
          ...tracking
        });
        status.textContent = 'Não foi possível enviar agora. Tente novamente ou entre em contato pelo WhatsApp.';
        status.className = 'status bad';
      } finally {
        send.disabled = false;
        send.textContent = 'Enviar';
      }
    });
  }
})();