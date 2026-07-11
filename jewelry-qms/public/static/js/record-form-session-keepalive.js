(function () {
    'use strict';

    var intervalMs = 5 * 60 * 1000;
    var statusId = 'qms-session-keepalive-status';

    function showWarning(message) {
        var status = document.getElementById(statusId);
        if (!status) {
            status = document.createElement('div');
            status.id = statusId;
            status.className = 'alert alert-warning position-fixed bottom-0 end-0 m-3 shadow';
            status.setAttribute('role', 'alert');
            status.style.zIndex = '1080';
            document.body.appendChild(status);
        }
        status.textContent = message;
    }

    function clearWarning() {
        var status = document.getElementById(statusId);
        if (status) {
            status.remove();
        }
    }

    function keepAlive() {
        fetch('/dashboard/keepAlive', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('session keep-alive failed');
            }
            clearWarning();
        }).catch(function () {
            showWarning('会话保持失败，请先复制或保存当前填写内容，再重新登录。');
        });
    }

    setInterval(keepAlive, intervalMs);
}());
