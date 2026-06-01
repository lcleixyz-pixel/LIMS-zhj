(function () {
    if (!document.body || document.body.dataset.qmsCopilotEnabled !== '1') {
        return;
    }

    function qmsCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function qmsCollectPageMeta() {
        var body = document.body;
        return {
            controller: body.dataset.qmsController || '',
            action: body.dataset.qmsAction || '',
            record_id: body.dataset.qmsRecordId || '',
            route: body.dataset.qmsRoute || '',
            module: body.dataset.qmsModule || '',
            title: body.dataset.qmsTitle || ''
        };
    }

    function appendPageMeta(formData) {
        var meta = qmsCollectPageMeta();
        Object.keys(meta).forEach(function (key) {
            formData.append('page_meta[' + key + ']', meta[key]);
        });
    }

    function qmsCopilotFetch(url, formData) {
        formData = formData || new FormData();
        if (!formData.has('__token__')) {
            formData.append('__token__', qmsCsrfToken());
        }
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': qmsCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        }).then(function (response) {
            var contentType = response.headers.get('content-type') || '';
            if (!response.ok) {
                if (contentType.indexOf('application/json') >= 0) {
                    return response.json().then(function (res) {
                        throw new Error((res && res.msg) ? res.msg : ('HTTP ' + response.status));
                    });
                }
                throw new Error('服务器错误 (HTTP ' + response.status + ')，可能请求超时');
            }
            if (contentType.indexOf('application/json') < 0) {
                throw new Error('服务器未返回 JSON，可能 PHP 执行超时');
            }
            return response.json();
        }).then(function (res) {
            if (res && res.csrf_token && window.qmsRefreshCsrfToken) {
                window.qmsRefreshCsrfToken(res.csrf_token);
            }
            return res;
        }).catch(function (err) {
            if (err && err.message === 'Failed to fetch') {
                throw new Error('连接中断或请求超时，请稍后重试或改用更短的问题');
            }
            throw err;
        });
    }

    window.qmsApplyFormDraft = function (draft) {
        if (!draft || !draft.fields) {
            return false;
        }
        var body = document.body;
        var action = body.dataset.qmsAction || '';
        if (action !== 'add' && action !== 'edit') {
            return false;
        }
        if ((body.dataset.qmsModule || '') !== (draft.module || '')) {
            return false;
        }
        var allowed = draft.allowed_fields || Object.keys(draft.fields || {});
        var blocked = ['__token__', 'id', 'company_id', 'created_by', 'modified_by'];
        var filled = 0;
        allowed.forEach(function (name) {
            if (blocked.indexOf(name) >= 0) {
                return;
            }
            var selector = '[name="' + CSS.escape(name) + '"]';
            var el = document.querySelector(selector);
            if (!el || el.type === 'hidden' || el.disabled || el.classList.contains('csrf')) {
                return;
            }
            if ('value' in el) {
                el.value = draft.fields[name];
                filled++;
            }
        });
        return filled > 0;
    };

    var state = {
        sessionId: '',
        contextMode: 'context',
        sending: false
    };

    var messagesEl = document.getElementById('qmsCopilotMessages');
    var inputEl = document.getElementById('qmsCopilotInput');
    var sendBtn = document.getElementById('qmsCopilotSendBtn');
    var modeSelect = document.getElementById('qmsCopilotMode');
    var statusEl = document.getElementById('qmsCopilotStatus');

    function setStatus(text, isError) {
        if (!statusEl) return;
        statusEl.textContent = text || '';
        statusEl.className = 'small px-3 pb-1 ' + (isError ? 'text-danger' : 'text-muted');
    }

    function appendMessage(role, content, draft) {
        if (!messagesEl) return;
        var wrap = document.createElement('div');
        wrap.className = 'qms-copilot-msg ' + role;
        var bubble = document.createElement('div');
        bubble.className = 'bubble';
        bubble.textContent = content;
        wrap.appendChild(bubble);

        if (draft && role === 'assistant') {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-primary qms-copilot-draft-btn';
            btn.textContent = '填充表单草稿';
            btn.addEventListener('click', function () {
                if (window.qmsApplyFormDraft(draft)) {
                    setStatus('已填充，请核对后保存');
                } else {
                    setStatus('当前页面无法应用此草稿', true);
                }
            });
            wrap.appendChild(btn);
        }

        messagesEl.appendChild(wrap);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function ensureSession() {
        if (state.sessionId) {
            return Promise.resolve(state.sessionId);
        }
        var formData = new FormData();
        formData.append('context_mode', state.contextMode);
        appendPageMeta(formData);
        return qmsCopilotFetch('/ai_chat/create', formData).then(function (res) {
            if (res.code !== 0) {
                throw new Error(res.msg || '创建会话失败');
            }
            state.sessionId = (res.data && res.data.id) ? res.data.id : '';
            return state.sessionId;
        });
    }

    // ensureSession 保留供后续扩展；首条消息由 /ai_chat/send 自动建会话
    function sendMessage() {
        if (state.sending || !inputEl) return;
        var content = (inputEl.value || '').trim();
        if (!content) return;

        if (document.body.dataset.qmsAiConfigured !== '1') {
            setStatus('DeepSeek API 未配置，请联系管理员', true);
            return;
        }

        state.sending = true;
        sendBtn.disabled = true;
        appendMessage('user', content);
        inputEl.value = '';
        setStatus('思考中…（上下文模式可能需要 1-2 分钟）');

        var formData = new FormData();
        if (state.sessionId) {
            formData.append('session_id', state.sessionId);
        }
        formData.append('content', content);
        formData.append('context_mode', state.contextMode);
        appendPageMeta(formData);

        qmsCopilotFetch('/ai_chat/send', formData).then(function (res) {
            if (res.code !== 0) {
                throw new Error(res.msg || '发送失败');
            }
            var data = res.data || {};
            if (data.session_id) {
                state.sessionId = data.session_id;
            }
            appendMessage('assistant', data.content || '', data.draft || null);
            if (data.expert_placeholder) {
                setStatus('评审专家模式（预览）：建议仅供参考');
            } else {
                setStatus('');
            }
        }).catch(function (err) {
            setStatus(err.message || '请求失败', true);
        }).finally(function () {
            state.sending = false;
            sendBtn.disabled = false;
        });
    }

    if (modeSelect) {
        modeSelect.addEventListener('change', function () {
            state.contextMode = modeSelect.value || 'context';
        });
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', sendMessage);
    }

    if (inputEl) {
        inputEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        });
    }

    var newChatBtn = document.getElementById('qmsCopilotNewChat');
    if (newChatBtn) {
        newChatBtn.addEventListener('click', function () {
            state.sessionId = '';
            if (messagesEl) {
                messagesEl.innerHTML = '';
            }
            setStatus('已开始新对话');
        });
    }

    var clearMineBtn = document.getElementById('qmsCopilotClearMine');
    if (clearMineBtn) {
        clearMineBtn.addEventListener('click', function () {
            if (!confirm('确认清空我的全部聊天会话？')) return;
            var formData = new FormData();
            formData.append('scope', 'mine');
            qmsCopilotFetch('/ai_chat/purge', formData).then(function (res) {
                state.sessionId = '';
                if (messagesEl) messagesEl.innerHTML = '';
                setStatus(res.msg || '已清空');
            }).catch(function () {
                setStatus('清空失败', true);
            });
        });
    }
})();
