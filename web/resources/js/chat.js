import { csrf, enviarImagen, esVideo, recorrerVideo } from './frames';

/*
 * Chat con el agente (página "Consultar placa" y botón flotante). Texto, foto (con o sin pregunta) o video.
 * La conversación se guarda en sessionStorage: se ve igual al cambiar de página o al abrir el botón flotante.
 * La memoria del agente (última imagen y últimos mensajes) vive en la sesión del servidor.
 */

const CLAVE = 'peaje-chat';
const MAXIMO = 40;
const BIENVENIDA = 'Hola. Escribe una placa (por ejemplo 1234ABC), o envía una foto y pregúntame qué hay: vehículos, tipos, llantas, personas y placas.';

const guardados = () => {
    try {
        return JSON.parse(sessionStorage.getItem(CLAVE)) ?? [];
    } catch {
        return [];
    }
};

const guardar = (mensaje) => {
    const lista = [...guardados(), mensaje].slice(-MAXIMO);
    sessionStorage.setItem(CLAVE, JSON.stringify(lista));
};

export function iniciarChat(raiz) {
    const salida = raiz.querySelector('[data-chat-salida]');
    const form = raiz.querySelector('[data-chat-form]');
    const archivoInput = raiz.querySelector('[data-chat-archivo]');
    const nombreArchivo = raiz.querySelector('[data-chat-nombre]');
    const boton = form.querySelector('button[type="submit"]');
    const url = form.action;

    const burbuja = (texto, autor, { imagen = null, nota = null, recordar = true } = {}) => {
        const div = document.createElement('div');
        div.className = autor === 'tu'
            ? 'ml-auto max-w-[85%] rounded-2xl rounded-br-md bg-sky-600 px-4 py-2.5 text-sm text-white shadow-sm'
            : 'mr-auto max-w-[85%] rounded-2xl rounded-bl-md bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm ring-1 ring-slate-200';
        if (imagen) {
            const img = document.createElement('img');
            img.src = imagen;
            img.className = 'mb-2 max-h-64 w-full rounded-lg object-contain';
            div.append(img);
        }
        const p = document.createElement('p');
        p.className = 'whitespace-pre-line';
        p.textContent = texto;
        div.append(p);
        if (nota) {
            const span = document.createElement('span');
            span.className = 'mt-1 block text-xs text-slate-400';
            span.textContent = nota;
            div.append(span);
        }
        salida.append(div);
        salida.scrollTop = salida.scrollHeight;
        // Las imágenes no se guardan (pesan mucho): solo el texto.
        if (recordar) guardar({ texto, autor, nota });
        return p;
    };

    const pintarGuardados = () => {
        salida.replaceChildren();
        burbuja(BIENVENIDA, 'agente', { recordar: false });
        guardados().forEach((m) => burbuja(m.texto, m.autor, { nota: m.nota, recordar: false }));
    };
    pintarGuardados();

    // Otro chat abierto (página o botón flotante) agregó mensajes.
    raiz.addEventListener('chat:actualizar', pintarGuardados);
    const avisar = () => document.querySelectorAll('[data-chat]').forEach((r) => r !== raiz && r.dispatchEvent(new Event('chat:actualizar')));

    archivoInput.addEventListener('change', () => {
        nombreArchivo.textContent = archivoInput.files[0]?.name ?? '';
    });

    raiz.querySelector('[data-chat-nueva]')?.addEventListener('click', async () => {
        sessionStorage.removeItem(CLAVE);
        await fetch(raiz.dataset.reiniciar, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } }).catch(() => {});
        pintarGuardados();
        avisar();
    });

    const preguntar = async (texto) => {
        const respuesta = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ mensaje: texto }),
        });
        const json = await respuesta.json();
        burbuja(json.respuesta ?? json.message ?? 'Sin respuesta.', 'agente', {
            // Colab apagado: la respuesta salió directo de la base de datos, sin el LLM.
            nota: json.modo === 'sin_llm' ? 'Respuesta directa de la base (el agente de Colab no está disponible).' : null,
        });
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const texto = form.mensaje.value.trim();
        const archivo = archivoInput.files[0];
        if (!texto && !archivo) return;

        form.reset();
        nombreArchivo.textContent = '';
        boton.disabled = true;

        try {
            if (archivo && esVideo(archivo)) {
                // Video: se revisan sus frames (1 por segundo, máx. 20) hasta encontrar una placa.
                burbuja(`[video] ${archivo.name}${texto ? ' · ' + texto : ''}`, 'tu');
                const estado = burbuja('Buscando una placa en el video…', 'agente', { recordar: false });
                let encontrado = null;
                let ultimo = null;
                let revisados = 0;
                await recorrerVideo(archivo, 1, async (blob, t) => {
                    revisados++;
                    estado.textContent = `Buscando una placa en el video… (segundo ${Math.round(t)})`;
                    const [, json] = await enviarImagen(url, blob, {}, 'frame.jpg');
                    ultimo = json;
                    if (json.detalle && json.detalle.estado !== 'sin_placa') encontrado = json;
                }, () => !encontrado && revisados < 20);

                estado.parentElement.remove();
                const final = encontrado ?? ultimo;
                burbuja(final?.respuesta ?? 'No encontré una placa legible en el video. Prueba con otro donde la placa se vea de cerca.', 'agente', { imagen: final?.evidencia });
                if (texto) await preguntar(texto);
            } else if (archivo) {
                burbuja(`[foto] ${archivo.name}${texto ? '\n' + texto : ''}`, 'tu', { imagen: URL.createObjectURL(archivo) });
                const [, json] = await enviarImagen(url, archivo, texto ? { mensaje: texto } : {}, archivo.name);
                burbuja(json.respuesta ?? json.message ?? 'Sin respuesta.', 'agente', { imagen: json.evidencia });
            } else {
                burbuja(texto, 'tu');
                await preguntar(texto);
            }
        } catch {
            burbuja('No se pudo conectar con el agente.', 'agente');
        } finally {
            boton.disabled = false;
            avisar();
        }
    });
}

/** Botón flotante: abre y cierra el panel del chat. */
export function iniciarChatFlotante(raiz) {
    const panel = raiz.querySelector('[data-chat-panel]');
    const abrir = raiz.querySelector('[data-chat-abrir]');
    const alternar = (mostrar) => {
        panel.classList.toggle('hidden', !mostrar);
        panel.classList.toggle('flex', mostrar);
        abrir.setAttribute('aria-expanded', String(mostrar));
        sessionStorage.setItem('peaje-chat-abierto', mostrar ? '1' : '');
        if (mostrar) {
            const salida = raiz.querySelector('[data-chat-salida]');
            salida.scrollTop = salida.scrollHeight;
            raiz.querySelector('input[name="mensaje"]').focus();
        }
    };
    abrir.addEventListener('click', () => alternar(panel.classList.contains('hidden')));
    raiz.querySelector('[data-chat-cerrar]').addEventListener('click', () => alternar(false));
    document.addEventListener('keydown', (e) => e.key === 'Escape' && !panel.classList.contains('hidden') && alternar(false));
    if (sessionStorage.getItem('peaje-chat-abierto')) alternar(true);
    iniciarChat(raiz);
}
