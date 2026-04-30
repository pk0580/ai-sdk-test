#!/usr/bin/env bash
# Автоматическая настройка Claude Code в WSL
# Запуск: bash fix-claude-wsl.sh

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${BLUE}[*]${NC} $1"; }
ok()   { echo -e "${GREEN}[OK]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[X]${NC} $1"; }

echo "================================================"
echo "  Настройка Claude Code в WSL"
echo "================================================"
echo

# 1. Проверка, что мы в WSL
if ! grep -qi microsoft /proc/version 2>/dev/null; then
  err "Скрипт нужно запускать внутри WSL."
  exit 1
fi
ok "WSL обнаружен"

# 2. Проверка/исправление PATH — убрать Windows-версию claude.exe
log "Проверяю текущую установку claude..."
CURRENT_CLAUDE="$(command -v claude 2>/dev/null || true)"
if [[ -n "$CURRENT_CLAUDE" ]]; then
  if [[ "$CURRENT_CLAUDE" == /mnt/c/* ]] || [[ "$CURRENT_CLAUDE" == *.exe ]]; then
    warn "Найдена Windows-версия: $CURRENT_CLAUDE"
    warn "Она не подходит для WSL — нужна нативная Linux-установка."
  else
    ok "Найдена Linux-версия: $CURRENT_CLAUDE"
  fi
else
  warn "claude не установлен в WSL — поставлю сейчас"
fi

# 3. Обновление apt и установка зависимостей
log "Обновляю apt и ставлю зависимости (curl, wslu, ca-certificates)..."
sudo apt-get update -y
sudo apt-get install -y curl ca-certificates wslu
ok "Зависимости установлены"

# 4. Настройка BROWSER -> wslview (чтобы OAuth открывался в Windows-браузере)
SHELL_RC="$HOME/.bashrc"
[[ -n "$ZSH_VERSION" || "$SHELL" == *"zsh"* ]] && SHELL_RC="$HOME/.zshrc"

if ! grep -q 'export BROWSER=wslview' "$SHELL_RC" 2>/dev/null; then
  log "Добавляю export BROWSER=wslview в $SHELL_RC"
  {
    echo ''
    echo '# Claude Code: открывать ссылки в Windows-браузере'
    echo 'export BROWSER=wslview'
  } >> "$SHELL_RC"
  ok "BROWSER=wslview добавлен в $SHELL_RC"
else
  ok "BROWSER=wslview уже настроен"
fi
export BROWSER=wslview

# 5. Установка Claude Code (официальный установщик)
log "Устанавливаю Claude Code через официальный installer..."
if curl -fsSL https://claude.ai/install.sh | bash; then
  ok "Claude Code установлен"
else
  warn "Официальный installer не сработал — пробую через npm"
  if ! command -v node >/dev/null 2>&1; then
    log "Ставлю Node.js (NodeSource LTS)..."
    curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
    sudo apt-get install -y nodejs
  fi
  npm install -g @anthropic-ai/claude-code
  ok "Claude Code установлен через npm"
fi

# 6. Убедиться, что ~/.local/bin в PATH (туда ставится официальный installer)
if [[ -d "$HOME/.local/bin" ]] && ! echo "$PATH" | grep -q "$HOME/.local/bin"; then
  if ! grep -q '.local/bin' "$SHELL_RC" 2>/dev/null; then
    echo 'export PATH="$HOME/.local/bin:$PATH"' >> "$SHELL_RC"
    ok "Добавил ~/.local/bin в PATH"
  fi
  export PATH="$HOME/.local/bin:$PATH"
fi

# 7. Проверка результата
echo
echo "================================================"
log "Проверяю установку..."
NEW_CLAUDE="$(command -v claude 2>/dev/null || true)"
if [[ -n "$NEW_CLAUDE" ]] && [[ "$NEW_CLAUDE" != /mnt/c/* ]] && [[ "$NEW_CLAUDE" != *.exe ]]; then
  ok "claude доступен: $NEW_CLAUDE"
  claude --version 2>/dev/null || true
else
  err "claude всё ещё не доступен как Linux-команда. Перезапусти терминал и попробуй снова."
  exit 1
fi

echo
echo "================================================"
echo -e "${GREEN}  Готово!${NC}"
echo "================================================"
echo
echo "Дальнейшие шаги:"
echo "  1) Перезапусти WSL-терминал (или выполни:  source $SHELL_RC )"
echo "  2) Запусти:  claude"
echo "  3) При первом запуске откроется ссылка для логина."
echo "     Она должна открыться в Windows-браузере автоматически."
echo "     Если нет — скопируй URL вручную и открой в браузере."
echo
echo "Если OAuth всё равно не работает, можно использовать API-ключ:"
echo "  echo 'export ANTHROPIC_API_KEY=\"sk-ant-...\"' >> $SHELL_RC"
echo "  source $SHELL_RC"
echo