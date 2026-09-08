'use strict';
(() => {
    const form = document.getElementById('fx-example-form');
    if (!form) return;
    const status = document.getElementById('fx-example-status');
    const button = form.querySelector('button');
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (button.disabled || !form.reportValidity()) return;
        button.disabled = true;
        status.textContent = 'Salvando…';
        try {
            const response = await fetch(window.FxExampleSettings.url, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': window.FxExampleSettings.nonce},
                body: JSON.stringify({name: form.elements.name.value})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Não foi possível salvar.');
            form.elements.name.value = data.name;
            status.textContent = 'Configuração salva.';
        } catch (error) {
            status.textContent = error.message || 'Falha de conexão. Tente novamente.';
        } finally {
            button.disabled = false;
        }
    });
})();
