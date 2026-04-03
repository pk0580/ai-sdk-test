<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI SDK Test Frontend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1>AI SDK Testing</h1>
    <hr>
    <div class="mb-3">
        <label for="message" class="form-label">Сообщение для агента</label>
        <textarea class="form-control" id="message" rows="3">Расскажи о преимуществах Laravel</textarea>
    </div>

    <div class="mb-3 d-flex gap-2">
        <button id="btn-chat" class="btn btn-primary">Обычный (ask)</button>
        <button id="btn-stream" class="btn btn-success">Стрим (stream)</button>
        <button id="btn-queue" class="btn btn-warning">Очередь (queue)</button>
        <button id="btn-broadcast" class="btn btn-info">Вещание (broadcast)</button>
    </div>

    <div class="mb-3">
        <h5>Результат:</h5>
        <div id="output"></div>
    </div>
</div>

<script>
    const output = document.getElementById('output');
    const messageInput = document.getElementById('message');
    let currentSessionId = null;

    function appendOutput(text, clear = false) {
        if (clear) output.innerText = '';
        output.innerText += text;
        output.scrollTop = output.scrollHeight;
        if (currentSessionId) {
             const sessionInfo = document.getElementById('session-info') || createSessionInfo();
             sessionInfo.innerText = 'Active Session: ' + currentSessionId;
        }
    }

    function createSessionInfo() {
        const div = document.createElement('div');
        div.id = 'session-info';
        div.className = 'text-muted small mb-2';
        output.parentNode.insertBefore(div, output);
        return div;
    }


    // --- Обычный запрос ---
    document.getElementById('btn-chat').addEventListener('click', async () => {
        appendOutput('Отправка обычного запроса...\n', true);
        try {
            const response = await fetch('/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message: messageInput.value })
            });
            const data = await response.json();
            appendOutput('\n--- Запуск (Sync) ---\n');
            appendOutput(`Статус: ${data.message || 'Started'}\n`);
            appendOutput(`Session ID: ${data.session_id}\n`);
            appendOutput(`Ввод: ${data.input}\n`);
            appendOutput('\n[Примечание] Результаты в синхронном режиме не возвращаются напрямую. Используйте "Стрим" для получения ответа в реальном времени.');
        } catch (err) {
            console.error('Chat request failed:', err);
            appendOutput('\n[Система] Ошибка: ' + err.message + '\n');
        }
    });

    // --- Стриминг ---
    document.getElementById('btn-stream').addEventListener('click', () => {
        appendOutput('Инициализация соединения...\n', true);
        const btns = ['btn-chat', 'btn-stream', 'btn-queue', 'btn-broadcast'];
        btns.forEach(id => document.getElementById(id).setAttribute('disabled', 'true'));

        currentSessionId = null; // Сбрасываем старый ID перед началом нового стрима
        const msg = encodeURIComponent(messageInput.value);
        const eventSource = new EventSource(`/stream?message=${msg}`);

        eventSource.onopen = function() {
            appendOutput('[Система] Соединение установлено. Ожидание ответа сервера...\n');
        };

        const cleanup = () => {
            eventSource.close();
            btns.forEach(id => document.getElementById(id).removeAttribute('disabled'));
        };

        eventSource.onmessage = function(e) {
            if (e.data === '[DONE]') {
                cleanup();
                appendOutput('\n--- Процесс завершен ---');
                if (document.getElementById('session-info')) {
                    document.getElementById('session-info').innerText = 'Session finished: ' + currentSessionId;
                }
                currentSessionId = null;
            } else {
                try {
                    const data = JSON.parse(e.data);

                    // Сохраняем sessionId для возможности отмены
                    let sessionIdFound = false;
                    if (data.type === 'session_id') {
                        currentSessionId = data.content;
                        sessionIdFound = true;
                        console.log('Session ID received (type: session_id):', currentSessionId);
                    } else if (data.session_id) {
                        currentSessionId = data.session_id;
                        sessionIdFound = true;
                        console.log('Session ID received (data.session_id):', currentSessionId);
                    } else if (data.content && data.content.session_id) {
                         currentSessionId = data.content.session_id;
                         sessionIdFound = true;
                         console.log('Session ID received (data.content.session_id):', currentSessionId);
                    }

                    if (sessionIdFound && currentSessionId) {
                        appendOutput(`[Система] Сессия: ${currentSessionId}\n`);
                    }

                    handleStreamEvent(data);
                } catch (err) {
                    console.error('Error parsing JSON:', err, e.data);
                }
            }
        };

        eventSource.onerror = function(e) {
            console.error('EventSource failed:', e);
            cleanup();
            currentSessionId = null;
            appendOutput('\n--- Ошибка стрима (возможно соединение разорвано) ---');
        };
    });

    function handleStreamEvent(data) {
        const { type, content } = data;

        if (content === undefined || content === null) return;

        switch (type) {
            case 'supervisor_decision':
                appendOutput(`\n[Supervisor] Решение: ${content.type === 'chain' ? 'Цепочка агентов' : 'Один агент'}\n`);
                if (content.agents && Array.isArray(content.agents)) {
                    appendOutput(`План: ${content.agents.map(a => (a && a.agent) || 'unknown').join(' -> ')}\n`);
                }
                break;
            case 'plan_created':
                if (content.steps && Array.isArray(content.steps)) {
                    appendOutput(`\n[Planner] Создан план действий (${content.steps.length} шагов):\n`);
                    content.steps.forEach((s, i) => {
                        const toolName = (s && s.tool) || 'unknown tool';
                        const description = (s && s.description) || '';
                        appendOutput(`${i+1}. ${toolName}: ${description}\n`);
                    });
                }
                break;
            case 'tool_called':
                appendOutput(`\n[Action] Вызов инструмента: ${(content && content.tool) || 'unknown'}...\n`);
                break;
            case 'tool_result':
                const resultStr = typeof content === 'string' ? content : JSON.stringify(content);
                const len = resultStr ? resultStr.length : 0;
                appendOutput(`[Result] Получен результат (длина: ${len} симв.)\n`);
                break;
            case 'reflection':
                appendOutput(`\n[Reflector] Анализ: ${(content && content.thought) || ''}\n`);
                appendOutput(`Решение: ${(content && content.decision) || ''}\n`);
                break;
            case 'final_result':
                appendOutput(`\n--- Финальный ответ ---\n${content || ''}\n`);
                break;
            case 'text': // Fallback для старого формата
                appendOutput(content || '');
                break;
            case 'session_id':
                console.log('Processed session_id event:', content);
                break;
            default:
                console.warn('Unknown event type:', type, content);
        }
    }

    // --- Очередь ---
    document.getElementById('btn-queue').addEventListener('click', async () => {
        appendOutput('Постановка в очередь...\n', true);
        const btn = document.getElementById('btn-queue');
        btn.setAttribute('disabled', 'true');
        try {
            const response = await fetch('/queue', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message: messageInput.value })
            });
            const data = await response.json();
            appendOutput(`Статус: ${data.message}\nJob ID: ${data.job_id}\n`);
        } catch (err) {
            appendOutput(`\n[Система] Ошибка: ${err.message}\n`);
        } finally {
            btn.removeAttribute('disabled');
        }
    });

    // --- Вещание ---
    document.getElementById('btn-broadcast').addEventListener('click', async () => {
        appendOutput('Запуск вещания (broadcastNow)...\n', true);
        appendOutput('Внимание: Для реального получения через Broadcast нужен Echo/Pusher.\nЗдесь мы просто инициируем событие.\n\n');

        const btn = document.getElementById('btn-broadcast');
        btn.setAttribute('disabled', 'true');
        try {
            const response = await fetch('/broadcast', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message: messageInput.value })
            });
            const data = await response.json();
            appendOutput(`Статус: ${data.message}\n`);
        } catch (err) {
            appendOutput(`\n[Система] Ошибка: ${err.message}\n`);
        } finally {
            btn.removeAttribute('disabled');
        }
    });
</script>
</body>
</html>
