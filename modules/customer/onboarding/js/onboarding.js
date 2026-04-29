(function () {
    function toggleSource(type, radio) {
        const form = radio.closest('form');
        if (!form) return;

        form.querySelectorAll('.src-git-fields, .src-zip-fields').forEach(function (el) {
            el.classList.add('is-hidden');
        });

        const target = form.querySelector('.src-' + type + '-fields');
        if (target) {
            target.classList.remove('is-hidden');
        }
    }

    function switchRegisterTab(tab) {
        document.querySelectorAll('[data-register-tab]').forEach(function (button) {
            button.classList.toggle('active', button.dataset.registerTab === tab);
        });

        ['new', 'import'].forEach(function (tabName) {
            const panel = document.getElementById('tab-' + tabName);
            if (panel) {
                panel.classList.toggle('is-hidden', tabName !== tab);
            }
        });
    }

    function selectType(card) {
        const grid = card.closest('.type-grid') || document;
        grid.querySelectorAll('.type-card').forEach(function (item) {
            item.classList.remove('selected');
        });

        card.classList.add('selected');

        const input = card.querySelector('input[type="radio"]');
        if (input) {
            input.checked = true;
        }
    }

    function selectImport(card) {
        const grid = card.closest('.import-grid') || document;
        grid.querySelectorAll('.import-card').forEach(function (item) {
            item.classList.remove('selected');
        });

        card.classList.add('selected');

        const input = card.querySelector('input[type="radio"]');
        if (input) {
            input.checked = true;
        }

        const nameInput = document.getElementById('imp-name');
        const serverName = card.querySelector('.s-name');
        if (nameInput && serverName && !nameInput.dataset.edited) {
            nameInput.value = serverName.textContent.trim();
        }
    }

    function selectProvider(card) {
        document.querySelectorAll('.provider-card').forEach(function (item) {
            item.classList.remove('selected');
        });

        card.classList.add('selected');

        const input = card.querySelector('input[type="radio"]');
        if (input) {
            input.checked = true;
        }

        const button = document.getElementById('continue-btn');
        if (!button || !input) return;

        button.disabled = false;
        button.textContent = input.value === 'hetzner'
            ? 'Continue with Hetzner →'
            : 'Continue with Manual →';
    }

    function showStreamPanel(panel) {
        if (panel) {
            panel.classList.remove('is-hidden');
        }
    }

    function appendLog(log, line) {
        if (!log) return;
        log.textContent += line + '\n';
        log.scrollTop = log.scrollHeight;
    }

    function initProvisionStream(root) {
        const log = root.querySelector('[data-stream-log]');
        const msg = root.querySelector('[data-stream-status]');
        const panel = root.querySelector('[data-stream-panel]');
        const nextButton = document.getElementById('next-btn');

        function showNext(ok) {
            if (msg) {
                msg.textContent = ok
                    ? '✓ Provisioning complete!'
                    : '✗ Provisioning failed — you can retry from the server page.';
            }
            if (!ok && nextButton) {
                nextButton.textContent = 'Continue to Deploy ↠';
            }
            showStreamPanel(panel);
        }

        if (root.dataset.serverActive === '1') {
            if (log) {
                log.textContent = 'Server is already provisioned and active.';
            }
            showNext(true);
            return;
        }

        initEventStream(root.dataset.streamUrl, {
            onDone: function (result) {
                showNext(result.status === 'active');
            },
            onError: function () {
                appendLog(log, '\n[Connection closed]');
                if (msg) {
                    msg.textContent = 'Connection lost — check the server page for status.';
                }
                showStreamPanel(panel);
            },
        }, log);
    }

    function initDeploymentStream(root) {
        const log = root.querySelector('[data-stream-log]');
        const msg = root.querySelector('[data-stream-status]');
        const panel = root.querySelector('[data-stream-panel]');

        initEventStream(root.dataset.streamUrl, {
            onDone: function (result) {
                if (msg) {
                    msg.textContent = result.status === 'success'
                        ? '✓ Deployment complete!'
                        : '✗ Deployment failed — you can retry from the deployment page.';
                }
                showStreamPanel(panel);
            },
            onError: function () {
                appendLog(log, '\n[Connection closed]');
                showStreamPanel(panel);
            },
        }, log);
    }

    function initEventStream(url, callbacks, log) {
        if (!url || typeof EventSource === 'undefined') return;

        const stream = new EventSource(url);

        stream.onmessage = function (event) {
            appendLog(log, event.data);
        };

        stream.addEventListener('done', function (event) {
            stream.close();
            const result = JSON.parse(event.data);
            callbacks.onDone(result);
        });

        stream.onerror = function () {
            if (stream.readyState === EventSource.CLOSED) return;
            stream.close();
            callbacks.onError();
        };
    }

    function initOnboardingStreams() {
        document.querySelectorAll('[data-onboarding-stream]').forEach(function (root) {
            if (root.dataset.onboardingStream === 'provision') {
                initProvisionStream(root);
            } else if (root.dataset.onboardingStream === 'deployment') {
                initDeploymentStream(root);
            }
        });
    }

    function bindSubmitLoaders() {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                const submitButton = form.querySelector('button[type="submit"]');
                if (!submitButton) return;

                submitButton.classList.add('btn-loading');
                submitButton.disabled = true;
            });
        });
    }

    function bindRegisterDeploymentInteractions() {
        document.addEventListener('click', function (event) {
            const tabButton = event.target.closest('[data-register-tab]');
            if (tabButton) {
                switchRegisterTab(tabButton.dataset.registerTab);
                return;
            }

            const typeCard = event.target.closest('.type-card');
            if (typeCard) {
                selectType(typeCard);
                return;
            }

            const importCard = event.target.closest('.import-card');
            if (importCard) {
                selectImport(importCard);
                return;
            }

            const providerCard = event.target.closest('.provider-card');
            if (providerCard) {
                selectProvider(providerCard);
            }
        });

        document.addEventListener('change', function (event) {
            const sourceInput = event.target.closest('input[name="source_type"]');
            if (sourceInput) {
                toggleSource(sourceInput.value, sourceInput);
                return;
            }

            const serverTypeInput = event.target.closest('.type-card input[type="radio"]');
            if (serverTypeInput) {
                selectType(serverTypeInput.closest('.type-card'));
                return;
            }

            const importInput = event.target.closest('.import-card input[type="radio"]');
            if (importInput) {
                selectImport(importInput.closest('.import-card'));
                return;
            }

            const providerInput = event.target.closest('.provider-card input[type="radio"]');
            if (providerInput) {
                selectProvider(providerInput.closest('.provider-card'));
            }
        });

        document.addEventListener('input', function (event) {
            if (event.target.matches('[data-track-manual-edit]')) {
                event.target.dataset.edited = event.target.value ? '1' : '';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindSubmitLoaders();
        bindRegisterDeploymentInteractions();
        initOnboardingStreams();
    });

    window.toggleSource = toggleSource;
    window.switchTab = switchRegisterTab;
    window.selectType = selectType;
    window.selectImport = selectImport;
    window.selectProvider = function (value) {
        const card = document.getElementById('card-' + value);
        if (card) {
            selectProvider(card);
        }
    };
    window.markSelected = function (input) {
        const card = input.closest('.type-card');
        if (card) {
            selectType(card);
        }
    };
    window.markImportSelected = function (input) {
        const card = input.closest('.import-card');
        if (card) {
            selectImport(card);
        }
    };
})();