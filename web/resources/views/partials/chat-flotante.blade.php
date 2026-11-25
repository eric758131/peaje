{{-- Botón flotante (abajo a la derecha) con el chat del agente. No se muestra en la página "Consultar placa". --}}
<div data-chat data-chat-flotante data-reiniciar="{{ route('chat.reiniciar') }}" class="fixed right-4 bottom-4 z-40 flex flex-col items-end gap-3 sm:right-6 sm:bottom-6">
    <section data-chat-panel role="dialog" aria-label="Chat con el agente IA"
             class="hidden h-[min(34rem,calc(100vh-7rem))] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
        <header class="flex items-center gap-3 bg-slate-900 px-4 py-3 text-white">
            <span class="grid size-8 place-items-center rounded-full bg-gradient-to-br from-sky-400 to-indigo-500"><x-icono nombre="chat" clase="size-4" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">Agente IA</p>
                <p class="truncate text-xs text-slate-400">Placas y fotos del peaje</p>
            </div>
            <button type="button" data-chat-nueva class="rounded-lg px-2 py-1 text-xs text-slate-300 hover:bg-white/10 hover:text-white" title="Nueva conversación">Nueva</button>
            <button type="button" data-chat-cerrar class="rounded-lg p-1 text-slate-300 hover:bg-white/10 hover:text-white" aria-label="Cerrar chat">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div data-chat-salida class="flex flex-1 flex-col gap-3 overflow-y-auto bg-slate-50 p-4"></div>

        <form data-chat-form action="{{ route('chat.enviar') }}" method="POST" enctype="multipart/form-data" class="border-t border-slate-100 p-3">
            @csrf
            <div class="flex items-center gap-2">
                <label class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-lg text-slate-500 ring-1 ring-slate-300 hover:bg-slate-50" title="Adjuntar foto o video">
                    <x-icono nombre="foto" clase="size-4" />
                    <input data-chat-archivo name="imagen" type="file" accept="image/*,video/*" class="sr-only">
                </label>
                <input name="mensaje" type="text" maxlength="1000" placeholder="Placa o pregunta…" class="control min-w-0 flex-1" autocomplete="off">
                <button type="submit" class="grid size-9 shrink-0 place-items-center rounded-lg bg-sky-600 text-white hover:bg-sky-500 disabled:opacity-50" aria-label="Enviar">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
            </div>
            <p data-chat-nombre class="mt-1 truncate text-xs text-slate-500"></p>
        </form>
    </section>

    <button type="button" data-chat-abrir aria-expanded="false" aria-label="Abrir chat con el agente IA"
            class="grid size-14 place-items-center rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-white shadow-lg ring-4 ring-white transition hover:scale-105">
        <x-icono nombre="chat" clase="size-7" />
    </button>
</div>
