/*
 * Utilidades para enviar imágenes a la inferencia.
 * Un video no se sube entero: el navegador extrae frames JPEG y los envía uno por uno.
 */

export const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

const LADO_MAXIMO = 1280;

/** Dibuja lo que se ve en un <video> o <img> y devuelve un Blob JPEG (máx. 1280 px). */
export function capturar(fuente, calidad = 0.85) {
    const ancho = fuente.videoWidth || fuente.naturalWidth;
    const alto = fuente.videoHeight || fuente.naturalHeight;
    const escala = Math.min(1, LADO_MAXIMO / Math.max(ancho, alto));

    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(ancho * escala);
    lienzo.height = Math.round(alto * escala);
    lienzo.getContext('2d').drawImage(fuente, 0, 0, lienzo.width, lienzo.height);

    return new Promise((resolver) => lienzo.toBlob(resolver, 'image/jpeg', calidad));
}

/** Espera un evento una sola vez. */
const evento = (elemento, nombre) => new Promise((resolver, rechazar) => {
    elemento.addEventListener(nombre, resolver, { once: true });
    elemento.addEventListener('error', () => rechazar(new Error('No se pudo leer el video.')), { once: true });
});

/**
 * Recorre un archivo de video cada `intervalo` segundos.
 * Llama a `alFrame(blob, segundo, duracion)` y espera a que termine antes de pasar al siguiente.
 * `continuar()` permite detener el recorrido.
 */
export async function recorrerVideo(archivo, intervalo, alFrame, continuar = () => true, video = null) {
    video ??= document.createElement('video');
    video.muted = true;
    video.playsInline = true;
    video.preload = 'auto';
    video.src = URL.createObjectURL(archivo);

    try {
        await evento(video, 'loadeddata');
        const duracion = Number.isFinite(video.duration) ? video.duration : 0;

        for (let t = 0; t <= duracion && continuar(); t += intervalo) {
            video.currentTime = Math.min(t, Math.max(0, duracion - 0.05));
            await evento(video, 'seeked');
            const blob = await capturar(video);
            await alFrame(blob, t, duracion);
        }
    } finally {
        URL.revokeObjectURL(video.src);
    }
}

/** Envía un Blob como `imagen` en multipart/form-data y devuelve [status, json]. */
export async function enviarImagen(url, blob, campos = {}, nombre = 'frame.jpg') {
    const datos = new FormData();
    datos.append('imagen', blob, nombre);
    Object.entries(campos).forEach(([clave, valor]) => datos.append(clave, valor));

    const respuesta = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: datos,
    });

    let json = {};
    try {
        json = await respuesta.json();
    } catch {
        json = { message: `Error ${respuesta.status} del servidor.` };
    }

    return [respuesta.status, json];
}

export const esVideo = (archivo) => archivo?.type?.startsWith('video/');

export const dormir = (ms) => new Promise((r) => setTimeout(r, ms));

/* ---------- Formato ---------- */

export const formatoBs = (n) => 'Bs ' + Number(n ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const categoria = (c) => window.PEAJE?.categorias?.[c] ?? (c ? c.charAt(0).toUpperCase() + c.slice(1) : '—');

export function insignia(elemento, estado) {
    const info = window.PEAJE?.estados?.[estado] ?? { texto: estado ?? '—', clases: 'bg-slate-100 text-slate-700 ring-slate-500/20' };
    elemento.className = `inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${info.clases}`;
    elemento.textContent = info.texto;
    return elemento;
}
