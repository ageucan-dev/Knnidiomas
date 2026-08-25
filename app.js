(() => {
  'use strict';

  if (window.__knnCapiBridgeLoaded) return;
  window.__knnCapiBridgeLoaded = true;
  window.dataLayer = window.dataLayer || [];

  let pendingLeadEventId = '';

  function getCookie(name) {
    const prefix = name + '=';
    const parts = document.cookie ? document.cookie.split(';') : [];

    for (const part of parts) {
      const value = part.trim();
      if (value.startsWith(prefix)) {
        return decodeURIComponent(value.slice(prefix.length));
      }
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

  function sendMetaServerEvent(payload) {
    const body = JSON.stringify(payload);

    try {
      if (navigator.sendBeacon) {
        const blob = new Blob([body], { type: 'application/json' });
        if (navigator.sendBeacon('/api/meta-event', blob)) return;
      }
    } catch (_) {}

    window.fetch('/api/meta-event', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json;charset=UTF-8' },
      body,
      keepalive: true
    }).catch(() => {});
  }

  // Intercepta apenas o POST do formulário para anexar event_id e sinais de atribuição.
  const originalFetch = window.fetch.bind(window);

  window.fetch = function (input, init) {
    try {
      const url = typeof input === 'string' ? input : (input && input.url) || '';
      const method = String((init && init.method) || 'GET').toUpperCase();

      if (method === 'POST' && /\/api\/lead\/?(?:\?|$)/.test(url) && init && typeof init.body === 'string') {
        const payload = JSON.parse(init.body);
        pendingLeadEventId = newEventId('lead');

        init = {
          ...init,
          body: JSON.stringify({
            ...payload,
            event_id: pendingLeadEventId,
            ...attribution()
          })
        };
      }
    } catch (_) {}

    return originalFetch(input, init);
  };

  // Enriquece os eventos já disparados pelo site sem enviar PII ao dataLayer.
  const originalPush = window.dataLayer.push.bind(window.dataLayer);

  window.dataLayer.push = function (...items) {
    const enriched = items.map((item) => {
      if (!item || typeof item !== 'object') return item;

      if (item.event === 'lead') {
        return {
          ...item,
          event_id: pendingLeadEventId || newEventId('lead'),
          conversion_type: 'formulario',
          ...attribution()
        };
      }

      if (item.event === 'whatsapp_click') {
        const eventId = newEventId('contact');
        const tracking = attribution();

        sendMetaServerEvent({
          event_name: 'Contact',
          event_id: eventId,
          ...tracking,
          custom_data: {
            content_name: 'WhatsApp KNN Barretos',
            contact_method: 'whatsapp'
          }
        });

        return {
          ...item,
          event_id: eventId,
          contact_method: 'whatsapp',
          button_location: 'header',
          ...tracking
        };
      }

      return item;
    });

    return originalPush(...enriched);
  };

  originalPush({
    event: 'capi_bridge_ready',
    parceria_id: 37061,
    unidade: 'KNN Barretos',
    ...attribution()
  });
})();
