/*
 * Foto de un documento (p. ej. el acuse firmado) con la cámara del dispositivo, sin salir de la página.
 * - Con HTTPS (o localhost) abre la cámara en vivo: celular (cámara trasera) o webcam de la PC.
 * - Sin HTTPS el navegador no presta la cámara en vivo: se abre la cámara nativa del celular (input capture).
 * La foto se sube a la propiedad Livewire indicada, igual que si se hubiera elegido un archivo.
 */
function camaraFoto(modelo) {
    return {
        abierto: false,
        listo: false,
        subiendo: false,
        mensaje: '',
        stream: null,
        wire: null,

        // La ventana de la cámara se teletransporta al <body>: se guarda aquí el componente Livewire del campo.
        init() {
            this.wire = this.$wire;
        },

        get enVivo() {
            return window.isSecureContext && !!navigator.mediaDevices?.getUserMedia;
        },

        async tomar() {
            if (!this.enVivo) {
                this.$refs.nativa.value = '';
                this.$refs.nativa.click();
                return;
            }

            this.mensaje = '';
            this.listo = false;
            this.abierto = true;
            await this.$nextTick();

            try {
                // Resolución alta: la firma y el texto del acuse tienen que leerse.
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.listo = true;
            } catch (e) {
                this.mensaje = e?.name === 'NotAllowedError'
                    ? 'No se dio permiso para usar la cámara. Actívalo en el navegador o usa "Adjuntar archivo".'
                    : 'No se pudo abrir la cámara. Usa "Adjuntar archivo".';
            }
        },

        disparar() {
            const video = this.$refs.video;
            if (!this.listo || !video.videoWidth) return;

            const lienzo = document.createElement('canvas');
            lienzo.width = video.videoWidth;
            lienzo.height = video.videoHeight;
            lienzo.getContext('2d').drawImage(video, 0, 0);

            lienzo.toBlob((blob) => {
                if (!blob) return;
                const archivo = new File([blob], `${modelo}-camara.jpg`, { type: 'image/jpeg' });
                this.cerrar();
                this.subir(archivo);
            }, 'image/jpeg', 0.9);
        },

        subir(archivo) {
            this.subiendo = true;
            this.wire.upload(
                modelo,
                archivo,
                () => { this.subiendo = false; navigator.vibrate?.(60); },
                () => {
                    this.subiendo = false;
                    this.$dispatch('toast', { tipo: 'error', mensaje: 'No se pudo subir la foto. Intenta de nuevo.' });
                },
            );
        },

        cerrar() {
            this.stream?.getTracks().forEach((t) => t.stop());
            this.stream = null;
            this.listo = false;
            this.abierto = false;
        },

        destroy() {
            this.cerrar();
        },
    };
}

document.addEventListener('alpine:init', () => window.Alpine.data('camaraFoto', camaraFoto));
