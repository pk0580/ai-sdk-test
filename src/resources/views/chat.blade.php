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

    function appendOutput(text, clear = false) {
        if (clear) output.innerText = '';
        output.innerText += text;
        output.scrollTop = output.scrollHeight;
    }

    // --- Обычный запрос ---
    document.getElementById('btn-chat').addEventListener('click', async () => {
        appendOutput('Отправка обычного запроса...\n', true);
        const response = await fetch('/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ message: messageInput.value })
        });
        const data = await response.json();
        appendOutput('\n--- Ответ ---\n' + data.response);
    });

    // --- Стриминг ---
    document.getElementById('btn-stream').addEventListener('click', () => {
        appendOutput('Запуск процесса (Supervisor Chain)...\n', true);
        const msg = encodeURIComponent(messageInput.value);
        const eventSource = new EventSource(`/stream?message=${msg}`);

        eventSource.onmessage = function(e) {
            if (e.data === '[DONE]') {
                eventSource.close();
                appendOutput('\n--- Процесс завершен ---');
            } else {
                try {
                    const data = JSON.parse(e.data);
                    handleStreamEvent(data);
                } catch (err) {
                    console.error('Error parsing JSON:', err, e.data);
                }
            }
        };

        eventSource.onerror = function(e) {
            console.error('EventSource failed:', e);
            eventSource.close();
            appendOutput('\n--- Ошибка стрима ---');
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
            default:
                console.warn('Unknown event type:', type, content);
        }
    }

    // --- Очередь ---
    document.getElementById('btn-queue').addEventListener('click', async () => {
        appendOutput('Постановка в очередь...\n', true);
        const response = await fetch('/queue', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ message: messageInput.value })
        });
        const data = await response.json();
        appendOutput(`Статус: ${data.message}\nJob ID: ${data.job_id}`);
    });

    // --- Вещание ---
    document.getElementById('btn-broadcast').addEventListener('click', async () => {
        appendOutput('Запуск вещания (broadcastNow)...\n', true);
        appendOutput('Внимание: Для реального получения через Broadcast нужен Echo/Pusher.\nЗдесь мы просто инициируем событие.\n\n');

        const response = await fetch('/broadcast', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ message: messageInput.value })
        });
        const data = await response.json();
        appendOutput(`Статус: ${data.message}`);
    });
</script>
</body>
</html>
