import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

export function useKeyboardShortcuts() {
    const showShortcutsModal = ref(false);

    function isInputElement(el) {
        if (!el) return false;
        const tag = el.tagName?.toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
    }

    function handleKeyDown(event) {
        const isTyping = isInputElement(document.activeElement);
        const key = event.key?.toLowerCase();
        const code = event.code;

        // 1. Open Shortcuts Help Modal: (Ctrl+/ or Cmd+/) or Shift+? (when not typing)
        if (
            ((event.ctrlKey || event.metaKey) && (key === '/' || key === '?')) ||
            (!isTyping && event.shiftKey && (key === '?' || code === 'Slash'))
        ) {
            event.preventDefault();
            showShortcutsModal.value = !showShortcutsModal.value;
            return;
        }

        // 2. Escape closes shortcuts modal if open
        if (event.key === 'Escape' && showShortcutsModal.value) {
            event.preventDefault();
            showShortcutsModal.value = false;
            return;
        }

        // 3. Alt-based Quick Navigation & Actions (works everywhere, including inside inputs)
        if (event.altKey && !event.ctrlKey && !event.metaKey) {
            switch (key) {
                case 'd':
                    event.preventDefault();
                    router.visit('/dashboard');
                    break;
                case 'j':
                    event.preventDefault();
                    router.visit('/accounting/journals');
                    break;
                case 'l':
                    event.preventDefault();
                    router.visit('/lending/loans');
                    break;
                case 'm':
                    event.preventDefault();
                    router.visit('/membership/members');
                    break;
                case 'g':
                    event.preventDefault();
                    router.visit('/membership/groups');
                    break;
                case 'r':
                    event.preventDefault();
                    router.visit('/accounting/reports');
                    break;
                case 'b':
                    event.preventDefault();
                    router.visit('/budgeting');
                    break;
                case 't':
                    event.preventDefault();
                    router.visit('/accounting/period-close');
                    break;
                case 's':
                    event.preventDefault();
                    window.dispatchEvent(new CustomEvent('app:trigger-sync'));
                    break;
                case 'a':
                    event.preventDefault();
                    window.dispatchEvent(new CustomEvent('assistant:toggle'));
                    break;
                case 'n':
                    event.preventDefault();
                    window.dispatchEvent(new CustomEvent('notifications:toggle'));
                    break;
                case 'p':
                    event.preventDefault();
                    window.print();
                    break;
            }
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDown);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', handleKeyDown);
    });

    return {
        showShortcutsModal,
    };
}