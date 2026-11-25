/*
 * Registrar paso desde el navegador: foto, video o cámara.
 * Cada frame se envía a /registrar/frame y espera la respuesta antes del siguiente.
 */
import { capturar, categoria, dormir, enviarImagen, formatoBs, insignia, recorrerVideo } from './frames';

export function iniciarRegistro(raiz) {
    const $ = (id) => document.getElementById(id);
    const url = raiz.dataset.url;
    const contadores = { frames: 0, pasos: 0, sin: 0 };
    let corriendo = false;
    let flujo = null;
    // En un video, el resultado principal muestra el frame más útil de la corrida,
    // no el último (que suele ser la calle vacía). La bitácora muestra todos.
    let mejorDelVideo = null;

    const prioridad = (status, json) => {
        if (status >= 400) return 4;
        if (json.resultado === 'registrado') return 3;
        if (json.resultado === 'duplicado') return 2;
        return json.lectura?.categoria || json.lectura?.placa ? 1 : 0;
    };

    /* ---------- Pestañas ---------- */
    raiz.querySelectorAll('.modo').forEach((boton) => boton.addEventListener('click', () => {
        if (corriendo) return;
        raiz.querySelectorAll('.modo').forEach((b) => {
            const activa = b === boton;
            b.classList.toggle('bg-white', activa);
            b.classList.toggle('text-slate-900', activa);
            b.classList.toggle('shadow-sm', activa);
            b.classList.toggle('text-slate-600', !activa);
        });
        raiz.querySelectorAll('[data-panel]').forEach((panel) => {
            const visible = panel.dataset.panel === boton.dataset.modo;
            panel.classList.toggle('hidden', !visible);
            panel.classList.toggle('flex', visible);
        });
    }));

    /* ---------- Estado de la interfaz ---------- */
    const intervalo = () => Number($('intervalo').value) || 1;
    const caseta = () => $('caseta').value;

    function ocupado(si) {
        corriendo = si;
        $('detener-caja').classList.toggle('hidden', !si);
        ['analizar-imagen', 'procesar-video', 'iniciar-camara'].forEach((id) => { $(id).disabled = si; });
    }

    function progreso(actual, total) {
        const visible = total > 0;
        $('progreso').classList.toggle('hidden', !visible);
        if (visible) $('progreso-barra').style.width = `${Math.min(100, (actual / total) * 100)}%`;
        $('progreso-texto').textContent = visible ? `${actual.toFixed(1)} / ${total.toFixed(1)} s` : '';
    }

    function mostrarEvidencia(src) {
        if (!src) return;
        if (flujo) {
            // Con la cámara encendida, la evidencia va en miniatura para no tapar el video.
            $('evidencia-mini').src = src;
            $('evidencia-mini').classList.remove('hidden');
            return;
        }
        $('evidencia').src = src;
        $('evidencia').classList.remove('hidden');
        $('vista-video').classList.add('hidden');
        $('vista-vacia').classList.add('hidden');
    }

    const poner = (campo, valor) => { $(`r-${campo}`).textContent = valor ?? '—'; };

    function mostrarResultado(status, json) {
        const mensaje = $('mensaje');

        if (status >= 400) {
            mensaje.className = 'mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700';
            mensaje.textContent = json.message ?? 'Error al procesar la imagen.';
            return;
        }

        mostrarEvidencia(json.evidencia);

        const l = json.lectura ?? {};
        const p = json.paso;
        const colores = {
            registrado: 'bg-emerald-50 text-emerald-800',
            duplicado: 'bg-slate-100 text-slate-700',
            sin_placa: 'bg-amber-50 text-amber-800',
        };
        mensaje.className = `mb-4 rounded-xl px-4 py-3 text-sm ${colores[json.resultado] ?? 'bg-slate-50 text-slate-600'}`;
        mensaje.textContent = json.mensaje;

        poner('placa', p?.placa ?? l.placa ?? 'No leída');
        poner('confianza', l.confianza != null ? `${Math.round(l.confianza * 100)} %` : null);
        poner('categoria', categoria(p?.categoria ?? l.categoria));
        poner('ejes', (p?.ejes ?? l.ejes) != null ? `${p?.ejes ?? l.ejes}${l.detalle?.ejes_estimados ? ' (estimado)' : ''}` : null);
        poner('pasajeros', l.pasajeros);
        poner('monto', p ? formatoBs(p.monto) : null);
        poner('latencia', l.latencia_ms != null ? `${l.latencia_ms} ms` : null);
        poner('propietario', p?.propietario);
        poner('saldo', p?.saldo != null ? formatoBs(p.saldo) : null);

        const estado = $('r-estado');
        if (p) insignia(estado, p.estado); else estado.textContent = '—';

        const enlace = $('r-enlace');
        enlace.classList.toggle('hidden', !p?.url);
        if (p?.url) enlace.href = p.url;
    }

    function anotar(status, json, miniatura, esFrame = true) {
        if (esFrame) contadores.frames++;
        if (json.resultado === 'registrado') contadores.pasos++;
        if (esFrame && json.resultado === 'sin_placa') contadores.sin++;
        $('c-frames').textContent = contadores.frames;
        $('c-pasos').textContent = contadores.pasos;
        $('c-sin').textContent = contadores.sin;

        const lista = $('bitacora');
        if (esFrame && contadores.frames === 1) lista.innerHTML = '';

        const li = document.createElement('li');
        li.className = 'flex items-center gap-3 px-4 py-2.5';

        const img = document.createElement('img');
        img.className = 'h-9 w-14 shrink-0 rounded object-cover ring-1 ring-slate-200';
        img.src = json.evidencia ?? miniatura;

        const texto = document.createElement('div');
        texto.className = 'min-w-0 flex-1';
        const placa = document.createElement('p');
        placa.className = 'font-mono text-sm font-semibold';
        const dudosa = json.lectura?.motivo === 'lectura_dudosa';
        placa.textContent = json.paso?.placa
            ?? (dudosa ? `${json.lectura.placa}?` : null)
            ?? (status >= 400 ? 'Error' : 'Sin placa');
        const detalle = document.createElement('p');
        detalle.className = 'truncate text-xs text-slate-500';
        detalle.textContent = status >= 400
            ? (json.message ?? '')
            : `${new Date().toLocaleTimeString()} · ${categoria(json.lectura?.categoria)} · ${json.lectura?.latencia_ms ?? '?'} ms`;
        texto.append(placa, detalle);

        const estado = insignia(document.createElement('span'), status >= 400 ? 'sin_placa' : json.resultado);
        if (status >= 400) estado.textContent = 'Error';

        li.append(img, texto, estado);
        lista.prepend(li);
    }

    /* ---------- Varios frames a la vez ---------- */
    // Hasta EN_VUELO frames esperando respuesta al mismo tiempo: así se alcanzan 2-5 frames por
    // segundo aunque cada respuesta tarde (red + inferencia). El servidor evita cobrar dos veces.
    const EN_VUELO = Math.max(1, Number(raiz.dataset.enVuelo) || 3);
    const pendientes = new Set();

    function enviarSinEsperar(blob) {
        const tarea = procesar(blob).finally(() => pendientes.delete(tarea));
        pendientes.add(tarea);
        return tarea;
    }

    /** Espera a que haya un lugar libre (menos de EN_VUELO frames en camino). */
    async function lugarLibre() {
        while (pendientes.size >= EN_VUELO) await Promise.race(pendientes);
    }

    const terminarPendientes = () => Promise.allSettled([...pendientes]);

    async function procesar(blob, nombre = 'frame.jpg') {
        $('analizando').classList.replace('hidden', 'flex');
        const miniatura = URL.createObjectURL(blob);
        try {
            const [status, json] = await enviarImagen(url, blob, { caseta_id: caseta() }, nombre);
            const p = prioridad(status, json);
            if (mejorDelVideo === null || p >= mejorDelVideo) {
                mostrarResultado(status, json);
                // Un error se muestra, pero no impide que el siguiente paso nuevo lo reemplace.
                if (mejorDelVideo !== null) mejorDelVideo = Math.min(p, 3);
            }
            anotar(status, json, miniatura);
            // Los demás vehículos con placa del mismo frame: una fila por cada uno.
            (json.pasos ?? [])
                .filter((p) => p.paso && p.paso.id !== json.paso?.id)
                .forEach((p) => anotar(status, {
                    resultado: p.resultado,
                    paso: p.paso,
                    lectura: { categoria: p.paso.categoria, latencia_ms: json.lectura?.latencia_ms },
                    evidencia: json.evidencia,
                }, miniatura, false));
            return status;
        } catch {
            mostrarResultado(500, { message: 'No hay conexión con el servidor.' });
            return 500;
        } finally {
            // El indicador se apaga cuando no queda ningún otro frame en camino.
            if (pendientes.size <= 1) $('analizando').classList.replace('flex', 'hidden');
        }
    }

    /* ---------- Foto ---------- */
    $('analizar-imagen').addEventListener('click', async () => {
        const archivo = $('archivo-imagen').files[0];
        if (!archivo) return $('archivo-imagen').click();
        ocupado(true);
        await procesar(archivo, archivo.name);
        ocupado(false);
    });

    /* ---------- Video ---------- */
    $('procesar-video').addEventListener('click', async () => {
        const archivo = $('archivo-video').files[0];
        if (!archivo) return $('archivo-video').click();
        ocupado(true);
        mejorDelVideo = 0;
        try {
            await recorrerVideo(archivo, intervalo(), async (blob, t, duracion) => {
                progreso(t, duracion);
                await lugarLibre();
                enviarSinEsperar(blob);
            }, () => corriendo);
        } catch (e) {
            mostrarResultado(500, { message: e.message });
        }
        await terminarPendientes();
        mejorDelVideo = null;
        progreso(0, 0);
        ocupado(false);
    });

    /* ---------- Cámara ---------- */
    $('iniciar-camara').addEventListener('click', async () => {
        const video = $('vista-video');
        try {
            flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 } }, audio: false });
        } catch {
            return mostrarResultado(500, { message: 'No se pudo abrir la cámara. Revisa los permisos del navegador (y que la página use HTTPS).' });
        }

        video.srcObject = flujo;
        await video.play();
        video.classList.remove('hidden');
        $('evidencia').classList.add('hidden');
        $('vista-vacia').classList.add('hidden');
        ocupado(true);

        // Captura un frame cada `intervalo` (2-5 por segundo) sin esperar la respuesta anterior;
        // si ya hay EN_VUELO frames en camino, espera un lugar (el servidor marca el ritmo real).
        while (corriendo) {
            const inicio = performance.now();
            await lugarLibre();
            if (!corriendo) break;
            enviarSinEsperar(await capturar(video));
            await dormir(Math.max(100, intervalo() * 1000 - (performance.now() - inicio)));
        }
        await terminarPendientes();
    });

    $('detener').addEventListener('click', () => {
        ocupado(false);
        if (flujo) {
            flujo.getTracks().forEach((pista) => pista.stop());
            flujo = null;
            $('vista-video').classList.add('hidden');
            const mini = $('evidencia-mini');
            mini.classList.add('hidden');
            mostrarEvidencia(mini.src);
        }
    });
}
