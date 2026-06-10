/* ============================================================================
   crecer-v2.js — helpers + módulo de conversación compartidos de la PoC V2
   ============================================================================
   Lo usan todas las vistas /v2/*. Dos piezas:

   - window.V2     : helpers (esc, api, avatarHtml, time, pills)
   - window.V2Conv : detalle + legajo de una conversación WA (timeline, composer,
                     acciones tomar/delegar/urgente/resolver/reabrir). Requiere
                     en la página: #det-empty, #det-body y #leg-body (opcional).

   Consume los endpoints de producción existentes — sin lógica de negocio nueva.
   ============================================================================ */

window.V2 = (function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    async function api(method, url, body) {
        const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
        if (body) opts.body = JSON.stringify(body);
        const r = await fetch(url, opts);
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }
    function avatarHtml(url, nombre, size) {
        if (url) return `<img class="v2-av" src="${esc(url)}" width="${size}" height="${size}" alt="">`;
        const ini = esc((nombre || '?').trim().charAt(0).toUpperCase());
        return `<span class="v2-av-fb" style="width:${size}px;height:${size}px;font-size:${Math.round(size*0.42)}px;">${ini}</span>`;
    }

    return {
        esc, api, avatarHtml,
        get:  url => api('GET', url),
        post: (url, b) => api('POST', url, b),
        patch:(url, b) => api('PATCH', url, b),
        del:  url => api('DELETE', url),
    };
})();

window.V2Conv = (function () {
    const { esc, get, post, avatarHtml } = V2;

    let cfg   = { usuarios: [], meId: 0, onChanged: null };
    let state = { panelId: null, conv: null, readOnly: false, modo: 'mensaje' };

    const EVENTO_LBL = {
        tomada: 'tomó la conversación', delegada: 'la delegó', resuelta: 'la resolvió',
        reabierta: 'la reabrió', urgente_on: 'la marcó urgente', urgente_off: 'le sacó urgente',
        iniciada: 'inició la conversación', derivada_area: 'la derivó de área', reenviada: 'la reenvió',
    };

    function bubbleContenido(m) {
        let inner = '';
        if (m.tipo === 'imagen' && m.archivo_url) {
            inner += `<img src="${esc(m.archivo_url)}" onclick="window.open('${esc(m.archivo_url)}','_blank')" alt="Imagen">`;
            if (m.contenido) inner += `<div style="margin-top:5px;">${esc(m.contenido)}</div>`;
        } else if (m.tipo === 'audio' && m.archivo_url) {
            inner += `<audio controls src="${esc(m.archivo_url)}"></audio>`;
            if (m.contenido) inner += `<div class="transcripcion">"${esc(m.contenido)}"</div>`;
        } else if ((m.tipo === 'documento' || m.tipo === 'video') && m.archivo_url) {
            inner += `<a class="doc-link" href="${esc(m.archivo_url)}" target="_blank">📄 ${esc(m.contenido || 'Archivo adjunto')}</a>`;
        } else {
            inner += esc(m.contenido || '');
        }
        return inner;
    }

    function renderMsg(m) {
        if (m.direccion === 'nota_interna') {
            return `<div class="v2-msg" style="justify-content:center;">
                <div class="v2-bubble nota"><span class="nota-tag">NOTA INTERNA · no la ve el paciente</span>${bubbleContenido(m)}</div>
            </div>`;
        }
        const dir  = m.direccion === 'entrante' ? 'in' : 'out';
        const meta = dir === 'out' ? `${esc(m.usuario || 'Bot')} · ${esc(m.hora)}` : esc(m.hora);
        return `<div class="v2-msg ${dir}"><div style="max-width:70%;min-width:0;">
            <div class="v2-bubble">${bubbleContenido(m)}</div>
            <div class="v2-msg-meta">${meta}</div>
        </div></div>`;
    }

    function renderEvento(e) {
        const lbl  = EVENTO_LBL[e.tipo] || e.tipo;
        const dest = e.destino ? ` a <b>${esc(e.destino)}</b>` : '';
        return `<div class="v2-evento"><b>${esc(e.usuario || 'Sistema')}</b> ${lbl}${dest} · ${esc(e.hora)}</div>`;
    }

    function renderTimeline(d) {
        const items = [
            ...d.mensajes.map(m => ({ ts: m.ts, fecha: m.fecha, html: renderMsg(m) })),
            ...(d.eventos || []).map(e => ({ ts: e.ts, fecha: (e.fecha || '').split(' ')[0], html: renderEvento(e) })),
        ].sort((a, b) => a.ts - b.ts);

        let html = '', lastFecha = null;
        for (const it of items) {
            if (it.fecha && it.fecha !== lastFecha) {
                html += `<div class="v2-msg-date">${esc(it.fecha)}</div>`;
                lastFecha = it.fecha;
            }
            html += it.html;
        }
        return html || `<div class="v2-empty">Sin mensajes</div>`;
    }

    function renderDetalle(d) {
        const c = d.conv;
        const esMia = c.asig_id === cfg.meId;
        const acciones = [];
        if (state.readOnly) {
            acciones.push(`<button class="v2-btn" onclick="V2Conv.accion('reabrir')">Reabrir</button>`);
        } else {
            if (!c.asig_id) acciones.push(`<button class="v2-btn primary" onclick="V2Conv.accion('tomar')">Tomar</button>`);
            else if (!esMia) acciones.push(`<button class="v2-btn" onclick="V2Conv.accion('tomar')" title="Asignada a ${esc(c.asig_name || '')}">Tomarla yo</button>`);
            acciones.push(`<button class="v2-btn" onclick="V2Conv.menuDelegar(event)">Delegar ▾</button>`);
            acciones.push(`<button class="v2-btn" onclick="V2Conv.accion('urgente')" title="Marcar / desmarcar urgente">⚑</button>`);
            acciones.push(`<button class="v2-btn accent" onclick="V2Conv.accion('resolver')">Resolver</button>`);
        }

        const asig = state.readOnly
            ? `<span class="v2-pill neutral">Archivada</span>`
            : (c.asig_name
                ? `<span class="v2-pill ${esMia ? 'proceso' : 'espera'}">${esMia ? 'La tenés vos' : esc(c.asig_name.split(' ')[0]) + ' la tiene'}</span>`
                : `<span class="v2-pill nueva">Sin tomar</span>`);

        const compose = state.readOnly ? '' : `
            <div class="v2-compose">
                <div class="v2-compose-modos">
                    <button class="v2-compose-modo active" id="modo-msg" onclick="V2Conv.setModo('mensaje')">Responder</button>
                    <button class="v2-compose-modo" id="modo-nota" onclick="V2Conv.setModo('nota')">Nota interna</button>
                </div>
                <div class="v2-compose-row">
                    <textarea id="compose" placeholder="Escribí tu respuesta…"></textarea>
                    <button class="v2-btn primary" id="btn-enviar" onclick="V2Conv.enviar()">Enviar</button>
                </div>
                <div class="hint">Ctrl+Enter para enviar · la nota interna no le llega al paciente</div>
            </div>`;

        document.getElementById('det-body').innerHTML = `
            <div class="v2-det-head">
                ${avatarHtml(c.avatar_url, c.contacto, 36)}
                <div class="info">
                    <div class="nombre">${esc(c.contacto)}</div>
                    <div class="sub">${esc(c.telefono)}</div>
                </div>
                ${asig}
                <div class="acciones">${acciones.join('')}</div>
            </div>
            ${c.resumen ? `<div class="v2-resumen"><span class="tag">Resumen IA</span>${esc(c.resumen)}</div>` : ''}
            <div class="v2-msgs" id="msgs">${renderTimeline(d)}</div>
            ${compose}`;

        const list = document.getElementById('msgs');
        list.scrollTop = list.scrollHeight;
        const ta = document.getElementById('compose');
        if (ta) ta.addEventListener('keydown', e => { if (e.ctrlKey && e.key === 'Enter') V2Conv.enviar(); });
    }

    async function renderLegajo(d) {
        const leg = document.getElementById('leg-body');
        if (!leg) return;
        const c = d.conv;

        let contacto = null;
        if (c.contacto_id) {
            try { contacto = (await get(`/contactos/${c.contacto_id}`)).contacto; } catch (e) {}
        }

        const rows = [['Teléfono', `<span class="mono">${esc(c.telefono || '—')}</span>`]];
        if (contacto) {
            if (contacto.dni)   rows.push(['DNI', `<span class="mono">${esc(contacto.dni)}</span>`]);
            if (contacto.email) rows.push(['Email', esc(contacto.email)]);
            if (contacto.fecha_nacimiento) rows.push(['Nacimiento', esc(String(contacto.fecha_nacimiento).split('T')[0])]);
            if (contacto.notas) rows.push(['Notas', esc(contacto.notas)]);
        }

        const eventos = (d.eventos || []).slice(-8).reverse();

        leg.innerHTML = `
            <div class="v2-leg-id">
                ${avatarHtml(c.avatar_url, c.contacto, 38)}
                <div style="min-width:0;">
                    <div class="nombre">${esc(c.contacto)}</div>
                    ${c.es_huerfana ? '<div class="dni" style="color:var(--v2-warn);">No está en contactos</div>' : (contacto?.dni ? `<div class="dni">DNI ${esc(contacto.dni)}</div>` : '')}
                </div>
            </div>
            <div class="v2-leg-tabla">${rows.map(([k, v]) => `<div class="v2-leg-row"><span class="k">${k}</span><span class="v">${v}</span></div>`).join('')}</div>
            ${c.resumen ? `<div class="v2-leg-tabla" style="padding:9px 11px;font-size:12px;line-height:1.5;"><span style="font-size:10px;font-weight:700;color:var(--v2-accent);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:3px;">Lo de esta conversación</span>${esc(c.resumen)}</div>` : ''}
            <button class="v2-leg-acc" onclick="V2Conv.toggleAcc(this)">
                <span>Actividad</span><span class="n">${(d.eventos || []).length}</span><span class="chev">›</span>
            </button>
            <div class="v2-leg-panel" style="display:none;">
                ${eventos.length ? eventos.map(e => `<div class="ev"><span><b>${esc((e.usuario || 'Sistema').split(' ')[0])}</b> ${esc(EVENTO_LBL[e.tipo] || e.tipo)}</span><span class="t">${esc(e.hace || e.fecha)}</span></div>`).join('') : '<div style="padding:6px 0;">Sin actividad registrada.</div>'}
            </div>
            ${c.contacto_id ? `
                <a class="v2-leg-link" href="/pacientes/${c.contacto_id}/documentos" title="Abre en la UI actual">📄 Documentos del paciente ↗</a>
                <a class="v2-leg-link" href="/v2/contactos?sel=${c.contacto_id}">📇 Ficha en Contactos</a>` : `
                <div style="font-size:11.5px;color:var(--v2-text-mute);padding:6px 2px;">Para ver legajo completo, agregalo a contactos desde la UI actual.</div>`}
        `;
    }

    // ── API pública del módulo ────────────────────────────────────
    return {
        init(options) { cfg = { ...cfg, ...options }; },
        get panelId() { return state.panelId; },

        async abrir(id, opts = {}) {
            state.panelId  = id;
            state.readOnly = !!opts.readOnly;
            state.modo     = 'mensaje';
            document.getElementById('det-empty').style.display = 'none';
            const body = document.getElementById('det-body');
            body.style.display = 'flex';
            body.innerHTML = `<div class="v2-det-empty"><span>Cargando…</span></div>`;
            try {
                const d = await get(`/atencion/conversacion/${id}`);
                state.conv = d;
                renderDetalle(d);
                renderLegajo(d);
            } catch (e) {
                body.innerHTML = `<div class="v2-det-empty"><span>No se pudo cargar la conversación.</span></div>`;
            }
        },

        cerrar() {
            state.panelId = null; state.conv = null;
            document.getElementById('det-body').style.display = 'none';
            document.getElementById('det-empty').style.display = '';
            const leg = document.getElementById('leg-body');
            if (leg) leg.innerHTML = 'Sin conversación seleccionada.';
        },

        // Refresco preservando lo tipeado en el composer.
        async refrescar() {
            if (!state.panelId) return;
            try {
                const d = await get(`/atencion/conversacion/${state.panelId}`);
                const ta = document.getElementById('compose');
                const guard = ta ? { v: ta.value, focus: document.activeElement === ta, s: ta.selectionStart } : null;
                state.conv = d;
                renderDetalle(d);
                renderLegajo(d);
                if (guard) {
                    const nta = document.getElementById('compose');
                    if (nta) {
                        nta.value = guard.v;
                        if (guard.focus) { nta.focus(); nta.setSelectionRange(guard.s, guard.s); }
                    }
                }
            } catch (e) {}
        },

        setModo(m) {
            state.modo = m;
            document.getElementById('modo-msg').className  = 'v2-compose-modo' + (m === 'mensaje' ? ' active' : '');
            document.getElementById('modo-nota').className = 'v2-compose-modo' + (m === 'nota' ? ' active nota' : '');
            document.getElementById('compose').placeholder = m === 'nota' ? 'Nota interna (no se envía al paciente)…' : 'Escribí tu respuesta…';
            document.getElementById('btn-enviar').textContent = m === 'nota' ? 'Guardar nota' : 'Enviar';
        },

        async enviar() {
            const ta = document.getElementById('compose');
            const texto = ta.value.trim();
            if (!texto || !state.panelId) return;
            const btn = document.getElementById('btn-enviar');
            btn.disabled = true;
            try {
                await post('/atencion/enviar', { conv_id: state.panelId, texto, modo: state.modo });
                ta.value = '';
                v2toast(state.modo === 'nota' ? 'Nota guardada' : 'Enviado');
                await V2Conv.refrescar();
            } catch (e) {
                v2toast('No se pudo enviar — ¿bot conectado?', 'err');
            } finally {
                btn.disabled = false;
            }
        },

        async accion(tipo) {
            if (!state.panelId) return;
            try {
                if (tipo === 'tomar')    await post('/atencion/tomar',    { id: state.panelId, tipo: 'wa' });
                if (tipo === 'urgente')  await post('/atencion/urgente',  { id: state.panelId, tipo: 'wa' });
                if (tipo === 'reabrir') {
                    await post('/atencion/reabrir', { id: state.panelId, tipo: 'wa' });
                    v2toast('Reabierta — volvió a la cola de su área');
                    state.readOnly = false;
                    await V2Conv.refrescar();
                    if (cfg.onChanged) cfg.onChanged('reabrir');
                    return;
                }
                if (tipo === 'resolver') {
                    await post('/atencion/resolver', { id: state.panelId, tipo: 'wa' });
                    v2toast('Resuelta y archivada');
                    V2Conv.cerrar();
                    if (cfg.onChanged) cfg.onChanged('resolver');
                    return;
                }
                v2toast('Listo');
                await V2Conv.refrescar();
                if (cfg.onChanged) cfg.onChanged(tipo);
            } catch (e) { v2toast('No se pudo aplicar la acción', 'err'); }
        },

        menuDelegar(ev) {
            ev.stopPropagation();
            document.querySelectorAll('.v2-menu').forEach(m => m.remove());
            const menu = document.createElement('div');
            menu.className = 'v2-menu';
            menu.innerHTML = cfg.usuarios.map(u => `<div class="opt" data-id="${u.id}">${esc(u.nombre_completo)}</div>`).join('');
            document.body.appendChild(menu);
            const r = ev.currentTarget.getBoundingClientRect();
            menu.style.top  = (r.bottom + 4) + 'px';
            menu.style.left = Math.min(r.left, innerWidth - 220) + 'px';
            menu.onclick = async e => {
                const id = e.target.dataset.id;
                if (!id) return;
                menu.remove();
                try {
                    await post('/atencion/delegar', { id: state.panelId, tipo: 'wa', user_id: parseInt(id) });
                    v2toast('Delegada');
                    await V2Conv.refrescar();
                    if (cfg.onChanged) cfg.onChanged('delegar');
                } catch { v2toast('No se pudo delegar', 'err'); }
            };
            setTimeout(() => document.addEventListener('click', () => menu.remove(), { once: true }), 0);
        },

        toggleAcc(btn) {
            btn.classList.toggle('open');
            const panel = btn.nextElementSibling;
            panel.style.display = panel.style.display === 'none' ? '' : 'none';
        },
    };
})();
