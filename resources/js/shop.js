import Offcanvas from 'bootstrap/js/dist/offcanvas';

document.querySelectorAll('form[data-wait]').forEach(function (form) {
    form.addEventListener('submit', function () {
        var button = form.querySelector('[type="submit"]');
        if (!button || button.disabled) {
            return;
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        var spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm me-2';
        spinner.setAttribute('aria-hidden', 'true');
        button.replaceChildren(spinner, document.createTextNode(button.getAttribute('data-busy') || 'Patientez…'));
    });
});

document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
        var value = button.getAttribute('data-copy') || '';
        var done = function () { button.textContent = 'Code copié'; };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(done).catch(function () { window.prompt('Copiez le code', value); });
            return;
        }
        window.prompt('Copiez le code', value);
    });
});

document.querySelectorAll('[data-buy]').forEach(function (button) {
    button.addEventListener('click', function () {
        var panel = document.getElementById(button.getAttribute('data-buy'));
        if (!panel) {
            window.location = button.getAttribute('data-fallback');
            return;
        }
        Offcanvas.getOrCreateInstance(panel).show();
    });
});

document.querySelectorAll('[data-reopen]').forEach(function (panel) {
    Offcanvas.getOrCreateInstance(panel).show();
});

var poll = document.querySelector('[data-poll]');
if (poll) {
    var seconds = parseInt(poll.getAttribute('data-poll'), 10) || 15;
    window.setTimeout(function () { window.location.reload(); }, seconds * 1000);
}
