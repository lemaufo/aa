{{-- Avisos breves. Se disparan desde Livewire con: $this->dispatch('toast', tipo: 'ok', mensaje: '...') --}}
<div x-data="{
        items: [],
        agregar(d) {
            const id = Date.now() + Math.random();
            this.items.push({ id, tipo: d.tipo ?? 'ok', mensaje: d.mensaje ?? '' });
            setTimeout(() => { this.items = this.items.filter(i => i.id !== id) }, 5000);
        },
    }"
     x-on:toast.window="agregar($event.detail)"
     class="pointer-events-none fixed inset-x-4 bottom-24 z-50 grid gap-2 lg:inset-x-auto lg:bottom-6 lg:right-6 lg:w-[26rem]"
     aria-live="polite">
    <template x-for="t in items" :key="t.id">
        <div x-transition:enter="transition duration-200 ease-out"
             x-transition:enter-start="translate-y-2 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             class="pointer-events-auto flex items-start gap-3 rounded-md border bg-white px-4 py-3 shadow-lg"
             :class="t.tipo === 'error' ? 'border-peligro' : 'border-ok'">
            <span class="mt-0.5 shrink-0" :class="t.tipo === 'error' ? 'text-peligro' : 'text-ok'" aria-hidden="true">
                <x-aa.icon name="check-circle" x-show="t.tipo !== 'error'" />
                <x-aa.icon name="exclamation-triangle" x-show="t.tipo === 'error'" />
            </span>
            <p class="text-[16px] font-medium leading-snug" x-text="t.mensaje"></p>
        </div>
    </template>
</div>