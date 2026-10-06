/**
 * Copy buttons. A button with data-copy-target="field-id" copies that field's value.
 * Buttons start hidden and appear only when the browser can copy, so without
 * JavaScript the read-only field (which selects itself on focus) still works.
 */
document.querySelectorAll('[data-copy-target]').forEach((button) => {
    const field = document.getElementById(button.dataset.copyTarget);
    const status = button.parentElement?.querySelector('[data-copy-status]');

    if (!field || !navigator.clipboard) {
        return;
    }

    button.hidden = false;

    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(field.value);

            if (status) {
                status.textContent = 'Link copied.';
            }
        } catch {
            field.select();

            if (status) {
                status.textContent = 'Press Ctrl+C (or ⌘C) to copy the selected link.';
            }
        }
    });
});
