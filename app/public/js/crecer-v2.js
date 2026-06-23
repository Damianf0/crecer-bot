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
        const data = await r.json().catch(() => null);
        if (!r.ok) {
            // Propaga el mensaje del server (ej: validación 422 con {error}) para que el UI lo muestre.
            const err = new Error((data && (data.error || data.message)) || ('HTTP ' + r.status));
            err.status = r.status; err.data = data;
            throw err;
        }
        return data;
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

    let cfg   = { usuarios: [], meId: 0, area: null, onChanged: null };
    let state = { panelId: null, conv: null, readOnly: false, modo: 'mensaje' };
    let respuestas = [];          // respuestas rápidas del área, cacheadas por área
    const rrCache  = {};

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

    // Respuestas rápidas del área (mismo endpoint y cache que producción).
    async function cargarRespuestas(area) {
        respuestas = [];
        if (!area) return;
        if (rrCache[area]) { respuestas = rrCache[area]; return; }
        try {
            const r = await get(`/atencion/respuestas-rapidas/${area}`);
            respuestas = r.data || [];
            rrCache[area] = respuestas;
        } catch (e) { /* sin respuestas — el menú lo muestra */ }
    }

    function rrRender() {
        const menu = document.getElementById('rr-menu');
        if (!menu) return;
        if (!respuestas.length) {
            menu.innerHTML = `<div class="v2-rr-empty">Sin respuestas rápidas para esta área.</div>
                <a class="v2-rr-foot" href="/admin/respuestas-rapidas" target="_blank">+ Agregar</a>`;
            return;
        }
        menu.innerHTML = respuestas.map(r =>
            `<div class="v2-rr-item" onclick="V2Conv.rrInsertar(${r.id})">
                <div class="rr-t">${esc(r.titulo)}</div>
                <div class="rr-p">${esc((r.texto || '').slice(0, 90))}</div>
            </div>`
        ).join('') + `<a class="v2-rr-foot" href="/admin/respuestas-rapidas" target="_blank">Administrar respuestas</a>`;
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
                    <div class="v2-rr-wrap">
                        <button type="button" class="v2-rr-btn" id="btn-rr" onclick="V2Conv.rrToggle(event)" title="Insertar una respuesta rápida">📋 Respuestas</button>
                        <div class="v2-rr-menu" id="rr-menu" style="display:none;"></div>
                    </div>
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
                <button class="v2-btn primary" style="width:100%;margin-top:8px;" onclick="V2Conv.modalAgregarContacto()">+ Agregar a contactos</button>
                <div style="font-size:11px;color:var(--v2-text-mute);margin-top:6px;padding:0 2px;">No está en el directorio. Al agregarlo, esta conversación queda vinculada.</div>`}
        `;
    }

    // Modal "Agregar a contactos" — <dialog> nativo creado una sola vez y reusado.
    // Mismo patrón (.v2-dialog) que el resto de la v2; pega contra el endpoint de
    // producción /atencion/conversacion/{id}/agregar-contacto.
    function ensureModalContacto() {
        let dlg = document.getElementById('v2-modal-contacto');
        if (dlg) return dlg;
        dlg = document.createElement('dialog');
        dlg.className = 'v2-dialog';
        dlg.id = 'v2-modal-contacto';
        dlg.style.width = 'min(440px, calc(100vw - 40px))';
        dlg.innerHTML = `
            <h3>Agregar a contactos</h3>
            <div id="v2-mc-jid" class="mono" style="font-size:11px;color:var(--v2-text-mute);margin-bottom:2px;"></div>
            <label class="v2-label">Nombre completo *</label>
            <input class="v2-field" id="v2-mc-nombre" placeholder="Ej: Juan Pérez" autocomplete="off">
            <div class="v2-grid2">
                <div>
                    <label class="v2-label">Teléfono *</label>
                    <input class="v2-field" id="v2-mc-tel" placeholder="549..." autocomplete="off" inputmode="numeric">
                </div>
                <div>
                    <label class="v2-label">DNI</label>
                    <input class="v2-field" id="v2-mc-dni" placeholder="Sin puntos" autocomplete="off" inputmode="numeric">
                </div>
            </div>
            <div id="v2-mc-aviso" style="display:none;font-size:11.5px;color:var(--v2-warn);margin-top:8px;line-height:1.4;"></div>
            <div class="v2-dialog-foot">
                <button class="v2-btn" onclick="document.getElementById('v2-modal-contacto').close()">Cancelar</button>
                <button class="v2-btn primary" id="v2-mc-guardar" onclick="V2Conv.guardarContactoNuevo()">Guardar contacto</button>
            </div>`;
        document.body.appendChild(dlg);
        // Enter en cualquier input guarda; Escape lo cierra el <dialog> nativo.
        dlg.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); V2Conv.guardarContactoNuevo(); }
        });
        return dlg;
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
                cargarRespuestas(cfg.area || (d.conv && d.conv.area));
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

        // Abre el modal para dar de alta en el directorio a quien escribe en una
        // conversación huérfana. Precarga el teléfono si el JID es @c.us.
        modalAgregarContacto() {
            if (!state.conv) return;
            const c = state.conv.conv;
            const dlg = ensureModalContacto();
            document.getElementById('v2-mc-nombre').value = '';
            document.getElementById('v2-mc-tel').value    = c.telefono_sugerido || '';
            document.getElementById('v2-mc-dni').value    = '';
            document.getElementById('v2-mc-jid').textContent = c.jid ? ('WhatsApp: ' + c.jid) : '';
            const aviso = document.getElementById('v2-mc-aviso');
            // Para @lid (la mayoría) WhatsApp no expone el número real, así que se
            // carga a mano. Para @c.us el teléfono ya viene precargado del JID.
            if ((c.jid || '').endsWith('@lid') && !c.telefono_sugerido) {
                aviso.style.display = '';
                aviso.textContent = 'Este chat usa un ID interno de WhatsApp (@lid): cargá el teléfono a mano.';
            } else {
                aviso.style.display = 'none';
            }
            dlg.showModal();
            document.getElementById('v2-mc-nombre').focus();
        },

        async guardarContactoNuevo() {
            if (!state.panelId) return;
            const nombre   = document.getElementById('v2-mc-nombre').value.trim();
            const telefono = document.getElementById('v2-mc-tel').value.trim();
            const dni      = document.getElementById('v2-mc-dni').value.trim();
            if (!nombre || !telefono) { v2toast('Nombre y teléfono son obligatorios', 'err'); return; }
            const btn = document.getElementById('v2-mc-guardar');
            btn.disabled = true;
            try {
                const r = await post(`/atencion/conversacion/${state.panelId}/agregar-contacto`, { nombre, telefono, dni });
                document.getElementById('v2-modal-contacto').close();
                v2toast(r.vinculado ? 'Vinculado a un contacto existente' : 'Contacto agregado y conversación vinculada');
                await V2Conv.refrescar();           // recarga legajo: ya no es huérfana
                if (cfg.onChanged) cfg.onChanged('contacto');  // refresca la bandeja (muestra el nombre)
            } catch (e) {
                // api() propaga el {error} del server (ej: teléfono duplicado / inválido).
                v2toast(e.message || 'No se pudo agregar el contacto', 'err');
            } finally {
                btn.disabled = false;
            }
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

        rrToggle(ev) {
            ev.stopPropagation();
            const menu = document.getElementById('rr-menu');
            if (!menu) return;
            if (menu.style.display === 'block') { menu.style.display = 'none'; return; }
            rrRender();
            menu.style.display = 'block';
            const cerrar = e => {
                if (!menu.contains(e.target) && e.target.id !== 'btn-rr') {
                    menu.style.display = 'none';
                    document.removeEventListener('click', cerrar);
                }
            };
            setTimeout(() => document.addEventListener('click', cerrar), 0);
        },

        rrInsertar(id) {
            const r = respuestas.find(x => x.id === id);
            if (!r) return;
            if (state.modo !== 'mensaje') this.setModo('mensaje');
            const ta = document.getElementById('compose');
            if (ta) { ta.value = r.texto; ta.focus(); ta.dispatchEvent(new Event('input')); }
            const menu = document.getElementById('rr-menu');
            if (menu) menu.style.display = 'none';
        },
    };
})();
