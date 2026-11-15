import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

/**
 * Profile photo cropper.
 * The file input (data-avatar-crop) opens a modal where the user moves, zooms and rotates the photo.
 * The cropped square is put back into the file input as a 512x512 JPEG, then the form is submitted.
 */
const input = document.querySelector('input[data-avatar-crop]');
const modal = document.getElementById('avatar-crop-modal');

if (input && modal) {
    const form = input.form;
    const image = modal.querySelector('[data-crop-image]');
    const zoom = modal.querySelector('[data-crop-zoom]');
    const error = modal.querySelector('[data-crop-error]');
    const saveButton = modal.querySelector('[data-crop-save]');
    let cropper = null;
    let objectUrl = null;

    const open = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        saveButton.focus();
    };

    const close = (clearInput = true) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        cropper?.destroy();
        cropper = null;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        if (clearInput) input.value = '';
    };

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;

        error.textContent = '';

        // The server only accepts JPG, PNG and WebP up to 2 MB: say so before cropping.
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            error.textContent = 'Please choose a JPG, PNG or WebP image.';
            open();
            image.removeAttribute('src');
            return;
        }

        objectUrl = URL.createObjectURL(file);
        image.src = objectUrl;
        open();

        cropper?.destroy();
        cropper = new Cropper(image, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            background: false,
            guides: false,
            center: false,
            highlight: false,
            cropBoxMovable: false,
            cropBoxResizable: false,
            toggleDragModeOnDblclick: false,
            ready() {
                // The slider runs from the smallest zoom that still fills the box up to 4x that.
                const data = cropper.getImageData();
                const min = data.width / data.naturalWidth;
                zoom.min = min;
                zoom.max = min * 4;
                zoom.step = min / 50;
                zoom.value = min;
            },
            zoom(event) {
                zoom.value = event.detail.ratio;
            },
        });
    });

    zoom.addEventListener('input', () => cropper?.zoomTo(parseFloat(zoom.value)));
    modal.querySelector('[data-crop-rotate]').addEventListener('click', () => cropper?.rotate(90));
    modal.querySelector('[data-crop-cancel]').addEventListener('click', () => close());
    modal.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

    saveButton.addEventListener('click', () => {
        if (!cropper) { close(); return; }

        cropper.getCroppedCanvas({ width: 512, height: 512, fillColor: '#ffffff', imageSmoothingQuality: 'high' })
            .toBlob((blob) => {
                if (!blob) { error.textContent = 'Could not crop this image. Try another one.'; return; }

                const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;

                close(false);
                form.requestSubmit();
            }, 'image/jpeg', 0.9);
    });
}
