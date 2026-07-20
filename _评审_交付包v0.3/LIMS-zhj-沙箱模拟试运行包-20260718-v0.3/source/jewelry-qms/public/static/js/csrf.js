(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) {
        return;
    }

    var token = meta.getAttribute('content') || '';
    if (!token) {
        return;
    }

    window.qmsRefreshCsrfToken = function (newToken) {
        if (!newToken) {
            return;
        }
        token = newToken;
        meta.setAttribute('content', newToken);
        document.querySelectorAll('input[name="__token__"]').forEach(function (input) {
            input.value = newToken;
        });
    };

    document.querySelectorAll('form').forEach(function (form) {
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post' || form.querySelector('input[name="__token__"]')) {
            return;
        }

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = '__token__';
        input.value = token;
        form.appendChild(input);
    });

    if (window.jQuery && window.jQuery.ajaxSetup) {
        window.jQuery.ajaxSetup({
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-CSRF-TOKEN', token);
            }
        });
    }
})();
