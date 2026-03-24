# Prompt Rules for AI

При генерации кода:

1. Всегда:
    - соблюдай слои (Domain, Application, Infrastructure)
    - используй интерфейсы
    - разделяй ответственность

2. Если создаётся фича:
    - создавай Entity
    - Repository interface
    - UseCase
    - Controller

3. Код должен быть:
    - чистым
    - тестируемым
    - без Laravel-зависимостей в Domain

4. Всегда сначала думай:
    - это Domain логика?
    - это Application?
    - это Infrastructure?