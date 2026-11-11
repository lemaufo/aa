/*
 * Escáner del código de barras de la etiqueta (Code 128 con el folio).
 * - Pistola USB/Bluetooth: escribe el folio en el campo y manda Enter; no necesita nada de aquí.
 * - Celular o PC con cámara: video en vivo. El navegador solo presta la cámara en HTTPS (o localhost).
 * - Sin HTTPS: se toma una foto de la etiqueta y se lee de la imagen.
 */
// La librería pesa: se descarga hasta que alguien toca Escanear.
async function crearLector() {
    const [{ BrowserMultiFormatReader }, { BarcodeFormat, DecodeHintType }] = await Promise.all([
        import('@zxing/browser'),
        import('@zxing/library'),
    ]);
    const hints = new Map([
        [DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.CODE_128, BarcodeFormat.CODE_39, BarcodeFormat.QR_CODE]],
        [DecodeHintType.TRY_HARDER, true],
    ]);

    return new BrowserMultiFormatReader(hints, { delayBetweenScanAttempts: 150 });
}

function escaner() {
    return {
        abierto: false,
        leyendo: false,
        mensaje: '',
        controles: null,
        lector: null,

        get enVivo() {
            return window.isSecureContext && !!navigator.mediaDevices?.getUserMedia;
        },

        async nuevoLector() {
            return (this.lector ??= await crearLector());
        },

        async abrir() {
            if (!this.enVivo) {
                this.$refs.foto.value = '';
                this.$refs.foto.click();
                return;
            }

            this.mensaje = '';
            this.abierto = true;
            await this.$nextTick();

            try {
                this.controles = await (await this.nuevoLector()).decodeFromConstraints(
                    { video: { facingMode: { ideal: 'environment' } } },
                    this.$refs.video,
                    (resultado) => resultado && this.listo(resultado.getText()),
                );
            } catch (e) {
                this.mensaje = e?.name === 'NotAllowedError'
                    ? 'No se dio permiso para usar la cámara. Actívalo en el navegador o escribe el folio.'
                    : 'No se pudo abrir la cámara. Escribe el folio o usa la pistola.';
            }
        },

        async desdeFoto(evento) {
            const archivo = evento.target.files?.[0];
            if (!archivo) return;

            const url = URL.createObjectURL(archivo);
            this.leyendo = true;
            try {
                const resultado = await (await this.nuevoLector()).decodeFromImageUrl(url);
                this.listo(resultado.getText());
            } catch {
                this.$dispatch('toast', { tipo: 'error', mensaje: 'No se encontró el código en la foto. Acércate a la etiqueta, con buena luz, y vuelve a intentar.' });
            } finally {
                URL.revokeObjectURL(url);
                this.leyendo = false;
            }
        },

        listo(texto) {
            const input = this.$refs.campo;
            input.value = texto.trim().toUpperCase();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            this.cerrar();
            navigator.vibrate?.(80);
            input.focus();
        },

        cerrar() {
            this.controles?.stop();
            this.controles = null;
            this.abierto = false;
        },

        destroy() {
            this.cerrar();
        },
    };
}

document.addEventListener('alpine:init', () => window.Alpine.data('escaner', escaner));
