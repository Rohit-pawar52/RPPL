import './bootstrap';

import Swal from 'sweetalert2';
import { initFlashMessages, confirmAction } from './flash';
import { initAdminSidebar } from './admin-nav';
import { initConfirmDeleteForms } from './confirm-delete';

window.Swal = Swal;
window.confirmAction = confirmAction;

document.addEventListener('DOMContentLoaded', () => {
    initFlashMessages();
    initAdminSidebar();
    initConfirmDeleteForms();
});
