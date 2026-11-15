import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

if (document.querySelector('canvas[data-chart]')) {
    import('./charts');
}

if (document.querySelector('input[data-avatar-crop]')) {
    import('./avatar-crop');
}
