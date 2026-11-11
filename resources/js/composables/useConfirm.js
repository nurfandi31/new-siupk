import { reactive } from 'vue';

const defaultIcon = {
    danger: 'warning',
    error: 'warning',
    warning: 'notification_important',
    success: 'check_circle',
    info: 'info',
    primary: 'help',
};

const state = reactive({
    open: false,
    title: 'Konfirmasi',
    message: '',
    confirmLabel: 'Hapus',
    cancelLabel: 'Batal',
    variant: 'danger',
    icon: 'warning',
    _resolve: null,
});

function confirm({
    title = 'Konfirmasi',
    message,
    confirmLabel,
    confirmText,
    cancelLabel = 'Batal',
    cancelText,
    variant = 'danger',
    icon = null,
} = {}) {
    return new Promise((resolve) => {
        Object.assign(state, {
            open: true,
            title,
            message,
            confirmLabel: confirmLabel || confirmText || 'Hapus',
            cancelLabel: cancelText != null ? cancelText : cancelLabel,
            variant,
            icon: icon || defaultIcon[variant] || 'help',
            _resolve: resolve,
        });
    });
}

function showAlert({ title = 'Pemberitahuan', message, confirmLabel = 'OK', variant = 'primary', icon = 'info' } = {}) {
    return confirm({ title, message, confirmLabel, cancelLabel: '', variant, icon });
}

function handleConfirm() {
    state._resolve?.(true);
    state.open = false;
}

function handleCancel() {
    state._resolve?.(false);
    state.open = false;
}

export function useConfirm() {
    return { confirmState: state, confirm, showAlert, handleConfirm, handleCancel };
}
