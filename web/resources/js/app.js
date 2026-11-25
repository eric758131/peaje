import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { categoria, csrf, enviarImagen, esVideo, formatoBs, insignia, recorrerVideo } from './frames';
import { iniciarRegistro } from './registro';
import { iniciarChat, iniciarChatFlotante } from './chat';

/* ---------- Menú lateral en celular ---------- */

const barra = document.getElementById('barra-lateral');
const fondo = document.getElementById('fondo-menu');
const alternarMenu = (abrir) => {
    barra?.classList.toggle('-translate-x-full', !abrir);
    fondo?.classList.toggle('hidden', !abrir);
};
document.getElementById('abrir-menu')?.addEventListener('click', () => alternarMenu(true));
fondo?.addEventListener('click', () => alternarMenu(false));

/* ---------- Tiempo real con Pusher ----------
 * El canal es privado: solo cuentas autorizadas se suscriben (routes/channels.php y /broadcasting/auth).
 */
window.Pusher = Pusher;

if (import.meta.env.VITE_PUSHER_APP_KEY && document.getElementById('barra-lateral')) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
        auth: { headers: { 'X-CSRF-TOKEN': csrf() } },
    });
}

function marcarEnVivo(activo) {
    const indicador = document.getElementById('indicador-vivo');
    if (!indicador) return;
    indicador.className = `inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${activo ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`;
    indicador.innerHTML = `<span class="size-1.5 rounded-full ${activo ? 'animate-pulse bg-emerald-500' : 'bg-slate-400'}"></span> ${activo ? 'en vivo' : 'sin conexión en vivo'}`;
}

function actualizarContadores(resumen) {
    if (!resumen) return;
    const poner = (id, valor) => { const el = document.getElementById(id); if (el) el.textContent = valor; };
    poner('contador-pasos', resumen.pasos);
    poner('contador-recaudado', formatoBs(resumen.recaudado));
    poner('contador-alertas', resumen.alertas_pendientes);

    const insigniaAlertas = document.getElementById('insignia-alertas');
    if (insigniaAlertas) {
        insigniaAlertas.textContent = resumen.alertas_pendientes;
        insigniaAlertas.classList.toggle('hidden', !resumen.alertas_pendientes);
    }
}

function agregarPaso(paso) {
    const tbody = document.getElementById('tabla-pasos');
    const plantilla = document.getElementById('plantilla-paso');
    if (!tbody || !plantilla) return;

    document.getElementById('sin-pasos')?.remove();
    const fila = plantilla.content.firstElementChild.cloneNode(true);
    const campo = (nombre) => fila.querySelector(`[data-campo="${nombre}"]`);

    campo('hora').textContent = paso.hora;
    campo('caseta').textContent = paso.caseta ?? '';
    campo('placa-enlace').textContent = paso.placa ?? '—';
    campo('placa-enlace').href = paso.url ?? '#';
    campo('categoria').textContent = categoria(paso.categoria);
    campo('ejes').textContent = paso.ejes ?? '—';
    campo('pasajeros-texto').textContent = paso.pasajeros != null ? ` · ${paso.pasajeros} pasajero(s)` : '';
    campo('monto').textContent = formatoBs(paso.monto);
    insignia(campo('estado'), paso.estado);
    if (paso.imagen_url) {
        campo('evidencia').src = paso.imagen_url;
        campo('evidencia').classList.remove('invisible');
        campo('evidencia-enlace').href = paso.imagen_url;
    }

    fila.classList.add('bg-sky-50');
    tbody.prepend(fila);
    setTimeout(() => fila.classList.remove('bg-sky-50'), 4000);
    while (tbody.rows.length > 12) tbody.deleteRow(-1);

    const porCategoria = document.getElementById(`categoria-${paso.categoria}`);
    if (porCategoria) porCategoria.textContent = Number(porCategoria.textContent) + 1;
}

function agregarAlerta(alerta) {
    const lista = document.getElementById('lista-alertas');
    if (!lista) return;
    document.getElementById('sin-alertas')?.remove();

    const li = document.createElement('li');
    li.className = 'flex items-start gap-3 rounded-xl bg-rose-50 p-3 ring-1 ring-rose-200 transition';
    const titulo = document.createElement('p');
    titulo.className = 'text-sm font-medium text-slate-900';
    titulo.textContent = alerta.tipo === 'no_registrada' ? 'Placa no registrada ' : 'Sin saldo ';
    const placa = document.createElement('span');
    placa.className = 'placa ml-1 text-xs';
    placa.textContent = alerta.placa ?? '';
    titulo.append(placa);
    const detalle = document.createElement('p');
    detalle.className = 'mt-0.5 text-xs text-slate-500';
    detalle.textContent = `${alerta.caseta ?? ''} · ${alerta.hora}`;
    const cuerpo = document.createElement('div');
    cuerpo.append(titulo, detalle);
    li.append(cuerpo);
    lista.prepend(li);
    while (lista.children.length > 8) lista.lastElementChild.remove();
}

if (window.Echo) {
    const canal = window.Echo.private('peaje');

    canal.listen('.paso.registrado', (evento) => {
        agregarPaso(evento.paso);
        actualizarContadores(evento.resumen);
    });

    canal.listen('.alerta.creada', (evento) => {
        agregarAlerta(evento.alerta);
        actualizarContadores(evento.resumen);
    });

    window.Echo.connector.pusher.connection.bind('connected', () => marcarEnVivo(true));
    window.Echo.connector.pusher.connection.bind('disconnected', () => marcarEnVivo(false));
    window.Echo.connector.pusher.connection.bind('unavailable', () => marcarEnVivo(false));
}

/* ---------- Registrar paso (foto, video o cámara) ---------- */

const registro = document.getElementById('registro');
if (registro) iniciarRegistro(registro);

/* ---------- Chat del agente: página "Consultar placa" y botón flotante ---------- */

document.querySelectorAll('[data-chat]:not([data-chat-flotante])').forEach(iniciarChat);
const chatFlotante = document.querySelector('[data-chat-flotante]');
if (chatFlotante) iniciarChatFlotante(chatFlotante);

/* ---------- Autocompletar vehículo con la inferencia ---------- */

const autocompletar = document.getElementById('autocompletar');
if (autocompletar) {
    const archivo = document.getElementById('archivo-autocompletar');
    const estado = document.getElementById('estado-autocompletar');
    const vista = document.getElementById('vista-autocompletar');
    const url = autocompletar.dataset.url;

    const aplicar = (lectura) => {
        const form = autocompletar.closest('form');
        if (lectura.placa) form.placa.value = lectura.placa.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        if (lectura.categoria) form.categoria.value = lectura.categoria;
        if (lectura.ejes) form.ejes.value = lectura.ejes;
    };

    archivo.addEventListener('change', async () => {
        const elegido = archivo.files[0];
        if (!elegido) return;
        estado.textContent = 'Analizando…';

        let mejor = null;
        try {
            if (esVideo(elegido)) {
                let revisados = 0;
                await recorrerVideo(elegido, 1, async (blob) => {
                    revisados++;
                    const [, json] = await enviarImagen(url, blob);
                    if (json.lectura && (!mejor || (json.lectura.placa && !mejor.lectura.placa))) mejor = json;
                }, () => !(mejor?.lectura?.placa) && revisados < 15);
            } else {
                const [status, json] = await enviarImagen(url, elegido, {}, elegido.name);
                if (status >= 400) throw new Error(json.message);
                mejor = json;
            }
        } catch (e) {
            estado.textContent = e.message || 'No se pudo analizar.';
            return;
        }

        if (!mejor?.lectura) {
            estado.textContent = 'No se pudo analizar.';
            return;
        }

        aplicar(mejor.lectura);
        const l = mejor.lectura;
        estado.textContent = l.placa
            ? `Detectado: ${l.placa} · ${categoria(l.categoria)} · ${l.ejes ?? '?'} ejes. Revisa los datos antes de guardar.`
            : `No se leyó la placa. Categoría: ${categoria(l.categoria)}, ${l.ejes ?? '?'} ejes.`;
        if (mejor.evidencia) {
            vista.src = mejor.evidencia;
            // La imagen inferida viaja con el formulario y queda guardada con el vehículo.
            document.getElementById('foto-inferida').value = mejor.evidencia;
            vista.classList.remove('hidden');
        }
    });
}

/* ---------- Probar la inferencia (página Estado) ---------- */

const prueba = document.getElementById('form-prueba');
if (prueba) {
    const archivo = document.getElementById('archivo-prueba');
    const salida = document.getElementById('salida-prueba');
    const vista = document.getElementById('vista-prueba');

    prueba.addEventListener('submit', async (e) => {
        e.preventDefault();
        const elegido = archivo.files[0];
        if (!elegido) return;
        salida.textContent = 'Analizando…';

        const mostrar = (status, json) => {
            if (json.evidencia) {
                vista.src = json.evidencia;
                vista.classList.remove('hidden');
            }
            salida.textContent = JSON.stringify(status >= 400 ? json : json.lectura, null, 2);
        };

        try {
            if (esVideo(elegido)) {
                // Se revisa un frame cada 2 s y al final se muestra el más útil (con placa, o al menos con vehículo),
                // no el último, que suele ser la calle vacía.
                const valor = (s, j) => (s >= 400 ? -1 : (j.lectura?.placa ? 2 : (j.lectura?.categoria ? 1 : 0)));
                let mejor = null;
                let frames = 0;
                await recorrerVideo(elegido, 2, async (blob, t, duracion) => {
                    salida.textContent = `Analizando video… segundo ${Math.round(t)} de ${Math.round(duracion)}`;
                    const [s, j] = await enviarImagen(prueba.action, blob);
                    frames++;
                    if (!mejor || valor(s, j) > valor(...mejor)) mejor = [s, j];
                }, () => true);
                if (mejor) {
                    mostrar(...mejor);
                    salida.textContent = `// Mejor frame de ${frames} revisados\n${salida.textContent}`;
                } else {
                    salida.textContent = 'El video no tiene frames que analizar.';
                }
            } else {
                mostrar(...await enviarImagen(prueba.action, elegido, {}, elegido.name));
            }
        } catch (e) {
            salida.textContent = e.message || 'No se pudo analizar el archivo.';
        }
    });
}
