import { Chess } from 'chess.js';

/** Glifos rellenos para ambos colores (el color lo da el CSS). */
const GLYPHS = { k: '♚', q: '♛', r: '♜', b: '♝', n: '♞', p: '♟' };
const FILES = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
const START_COUNTS = { p: 8, n: 2, b: 2, r: 2, q: 1 };
const PIECE_WORDS = { k: 'rey', q: 'dama', r: 'torre', b: 'alfil', n: 'caballo', p: 'peón' };
const DRAG_THRESHOLD = 4;

function clientId() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `c-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 9)}`;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function initChessGame() {
    const app = document.getElementById('chess-app');
    const configEl = document.getElementById('chess-config');
    const boardEl = document.getElementById('board');

    if (!app || !configEl || !boardEl) {
        return;
    }

    const config = JSON.parse(configEl.textContent);
    const statusEl = document.getElementById('status');
    const playersEl = document.getElementById('players');
    const historyEl = document.getElementById('history');
    const capturedEl = document.getElementById('captured');
    const hintEl = document.getElementById('hint');
    const resetBtn = document.getElementById('reset');
    const copyBtn = document.getElementById('copy-room');
    const chatListEl = document.getElementById('chat-messages');
    const chatFormEl = document.getElementById('chat-form');
    const chatInputEl = document.getElementById('chat-input');
    const chatNoteEl = document.getElementById('chat-note');
    const syncStatusEl = document.getElementById('sync-status');

    const chess = new Chess();
    const id = clientId();
    const myColor = config.color === 'white' ? 'w' : config.color === 'black' ? 'b' : null;
    const presence = { white: config.whiteJoined === true, black: config.blackJoined === true };

    let orientation = myColor === 'b' ? 'black' : 'white';
    let selected = null;
    let legalMoves = [];
    let lastMove = null;
    let drag = null;
    let lastSyncedMoves = JSON.stringify(config.moves ?? []);
    let stateSyncInProgress = false;
    let stateSyncFailed = false;
    let pendingWrites = 0;

    /** Reconstruye la partida a partir de la lista de movimientos del servidor. */
    function applyMove(move) {
        try {
            chess.move({
                from: move.from,
                to: move.to,
                promotion: move.promotion || undefined,
            });
            lastMove = { from: move.from, to: move.to };

            return true;
        } catch (error) {
            return false;
        }
    }

    (config.moves ?? []).forEach(applyMove);

    function buildMap() {
        const map = {};

        for (const row of chess.board()) {
            for (const cell of row) {
                if (cell) {
                    map[cell.square] = cell;
                }
            }
        }

        return map;
    }

    function findKing(color) {
        for (const row of chess.board()) {
            for (const cell of row) {
                if (cell && cell.type === 'k' && cell.color === color) {
                    return cell.square;
                }
            }
        }

        return null;
    }

    function ranks() {
        return orientation === 'white' ? ['8', '7', '6', '5', '4', '3', '2', '1'] : ['1', '2', '3', '4', '5', '6', '7', '8'];
    }

    function files() {
        return orientation === 'white' ? [...FILES] : [...FILES].reverse();
    }

    function squareEl(square) {
        return boardEl.querySelector(`.cb-square[data-square="${square}"]`);
    }

    function squareFromPoint(x, y) {
        return document.elementFromPoint(x, y)?.closest?.('.cb-square')?.dataset.square ?? null;
    }

    function render() {
        const map = buildMap();
        const targets = new Set(legalMoves.map((move) => move.to));
        const checkedKing = chess.isCheck() ? findKing(chess.turn()) : null;
        const rowRanks = ranks();
        const colFiles = files();
        const fragment = document.createDocumentFragment();

        rowRanks.forEach((rank, rowIndex) => {
            colFiles.forEach((file, colIndex) => {
                const square = `${file}${rank}`;
                const piece = map[square];
                const isLight = (FILES.indexOf(file) + Number(rank)) % 2 === 0;

                const cell = document.createElement('div');
                cell.className = `cb-square ${isLight ? 'cb-light' : 'cb-dark'}`;
                cell.dataset.square = square;
                cell.setAttribute('role', 'button');
                cell.setAttribute('aria-label', piece
                    ? `${square}, ${PIECE_WORDS[piece.type]} ${piece.color === 'w' ? 'blancas' : 'negras'}`
                    : square);

                if (lastMove && (lastMove.from === square || lastMove.to === square)) {
                    cell.classList.add('cb-last');
                }

                if (selected === square) {
                    cell.classList.add('cb-sel');
                }

                if (checkedKing === square) {
                    cell.classList.add('cb-check');
                }

                if (piece) {
                    const span = document.createElement('span');
                    span.className = `cb-piece ${piece.color === 'w' ? 'cb-w' : 'cb-b'}`;
                    span.textContent = GLYPHS[piece.type];
                    cell.appendChild(span);
                }

                if (targets.has(square)) {
                    const hint = document.createElement('span');
                    hint.className = `cb-hint ${piece ? 'cb-hint-cap' : 'cb-hint-dot'}`;
                    cell.appendChild(hint);
                }

                if (colIndex === 0) {
                    const label = document.createElement('span');
                    label.className = `cb-coord cb-coord-rank ${isLight ? 'cb-coord-dark' : 'cb-coord-light'}`;
                    label.textContent = rank;
                    cell.appendChild(label);
                }

                if (rowIndex === rowRanks.length - 1) {
                    const label = document.createElement('span');
                    label.className = `cb-coord cb-coord-file ${isLight ? 'cb-coord-dark' : 'cb-coord-light'}`;
                    label.textContent = file;
                    cell.appendChild(label);
                }

                fragment.appendChild(cell);
            });
        });

        boardEl.replaceChildren(fragment);
        updateStatus();
    }

    function statusText() {
        if (chess.isCheckmate()) {
            return { text: `¡Jaque mate! Ganaron las ${chess.turn() === 'w' ? 'negras' : 'blancas'}`, dot: '#f59e0b' };
        }

        if (chess.isStalemate()) {
            return { text: 'Ahogado: tablas', dot: '#94a3b8' };
        }

        if (chess.isInsufficientMaterial()) {
            return { text: 'Tablas por material insuficiente', dot: '#94a3b8' };
        }

        if (chess.isThreefoldRepetition()) {
            return { text: 'Tablas por repetición', dot: '#94a3b8' };
        }

        if (chess.isDraw()) {
            return { text: 'Tablas', dot: '#94a3b8' };
        }

        const side = chess.turn() === 'w' ? 'blancas' : 'negras';

        if (chess.isCheck()) {
            return { text: `¡Jaque al rey ${side}!`, dot: '#ef4444' };
        }

        return { text: `Turno de las ${side}`, dot: chess.turn() === 'w' ? '#f8fafc' : '#16202f' };
    }

    function updateStatus() {
        const status = statusText();

        statusEl.replaceChildren();

        const dot = document.createElement('span');
        dot.style.width = '10px';
        dot.style.height = '10px';
        dot.style.borderRadius = '9999px';
        dot.style.backgroundColor = status.dot;
        dot.style.boxShadow = '0 0 0 3px rgba(148,163,184,.25)';

        const label = document.createElement('span');
        label.textContent = status.text;

        statusEl.append(dot, label);
        updatePlayers();
        updateHistory();
        updateCaptured();
        updateHint();
    }

    function playerRow(title, joined, isYou) {
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between rounded-lg bg-white/5 px-3 py-2';

        const name = document.createElement('span');
        name.textContent = title;
        name.className = 'font-medium text-slate-200';

        const state = document.createElement('span');
        state.className = 'text-xs text-slate-400';

        if (isYou) {
            state.textContent = 'vos';
            state.className = 'text-xs font-semibold text-emerald-400';
        } else if (joined) {
            state.textContent = 'rival conectado';
        } else {
            state.textContent = 'esperando…';
        }

        row.append(name, state);

        return row;
    }

    function updatePlayers() {
        playersEl.replaceChildren(
            playerRow('♟ Blancas', presence.white, config.color === 'white'),
            playerRow('♟ Negras', presence.black, config.color === 'black'),
        );
    }

    function updateHistory() {
        const history = chess.history();
        historyEl.replaceChildren();

        if (history.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'text-slate-500';
            empty.textContent = 'Todavía no hay jugadas.';
            historyEl.appendChild(empty);

            return;
        }

        for (let i = 0; i < history.length; i += 2) {
            const row = document.createElement('li');
            row.className = 'flex gap-3 rounded px-1 py-0.5 odd:bg-white/5';

            const number = document.createElement('span');
            number.className = 'w-6 shrink-0 text-slate-500';
            number.textContent = `${i / 2 + 1}.`;

            const white = document.createElement('span');
            white.className = 'w-12';
            white.textContent = history[i];

            const black = document.createElement('span');
            black.className = 'w-12';
            black.textContent = history[i + 1] ?? '';

            row.append(number, white, black);
            historyEl.appendChild(row);
        }

        historyEl.scrollTop = historyEl.scrollHeight;
    }

    function updateCaptured() {
        const map = buildMap();
        const alive = { w: {}, b: {} };

        for (const square in map) {
            const piece = map[square];
            alive[piece.color][piece.type] = (alive[piece.color][piece.type] ?? 0) + 1;
        }

        const lostBySide = { w: '', b: '' };

        for (const type in START_COUNTS) {
            for (const color of ['w', 'b']) {
                const lost = START_COUNTS[type] - (alive[color][type] ?? 0);

                if (lost > 0) {
                    lostBySide[color] += GLYPHS[type].repeat(lost);
                }
            }
        }

        capturedEl.replaceChildren();

        const takenByWhite = lostBySide.b;
        const takenByBlack = lostBySide.w;

        if (!takenByWhite && !takenByBlack) {
            const empty = document.createElement('span');
            empty.textContent = '—';
            empty.className = 'text-slate-500';
            capturedEl.appendChild(empty);

            return;
        }

        const rows = [
            ['Blancas capturaron', takenByWhite],
            ['Negras capturaron', takenByBlack],
        ];

        for (const [label, glyphs] of rows) {
            if (!glyphs) {
                continue;
            }

            const row = document.createElement('div');
            row.className = 'flex items-center justify-between gap-3';

            const name = document.createElement('span');
            name.className = 'text-xs text-slate-500';
            name.textContent = label;

            const pieces = document.createElement('span');
            pieces.className = 'text-base text-slate-200';
            pieces.textContent = glyphs;

            row.append(name, pieces);
            capturedEl.appendChild(row);
        }
    }

    function updateHint() {
        let text = '';

        if (chess.isGameOver()) {
            text = 'Partida terminada. Podés reiniciar cuando quieras.';
        } else if (!config.color) {
            text = 'Modo espectador: estás siguiendo la partida en vivo.';
        } else if (chess.turn() === myColor) {
            text = 'Tu turno: arrastrá una pieza a la casilla de destino.';
        } else {
            text = 'Esperando el movimiento del rival…';
        }

        hintEl.textContent = text;
        resetBtn.hidden = config.color === null;
    }

    /* ------------------------------------------------------------------ *
     * Arrastrar y soltar
     * ------------------------------------------------------------------ */

    function beginGhost(from) {
        const originEl = squareEl(from);
        const piece = buildMap()[from];

        if (!originEl || !piece) {
            return;
        }

        const pieceEl = originEl.querySelector('.cb-piece');
        const ghost = document.createElement('div');
        ghost.className = 'cb-ghost';

        const span = document.createElement('span');
        span.className = `cb-piece ${piece.color === 'w' ? 'cb-w' : 'cb-b'}`;
        span.textContent = GLYPHS[piece.type];

        if (pieceEl) {
            span.style.fontSize = getComputedStyle(pieceEl).fontSize;
        }

        ghost.appendChild(span);
        document.body.appendChild(ghost);
        drag.ghost = ghost;
        originEl.classList.add('cb-origin');
    }

    function moveGhost(x, y) {
        if (!drag?.ghost) {
            return;
        }

        drag.ghost.style.left = `${x}px`;
        drag.ghost.style.top = `${y}px`;
    }

    function setHover(square) {
        boardEl.querySelectorAll('.cb-square.cb-hover').forEach((el) => el.classList.remove('cb-hover'));

        if (square) {
            squareEl(square)?.classList.add('cb-hover');
        }
    }

    function finishDrag() {
        drag?.ghost?.remove();
        boardEl.querySelectorAll('.cb-square.cb-hover, .cb-square.cb-origin').forEach((el) => {
            el.classList.remove('cb-hover', 'cb-origin');
        });
        drag = null;
    }

    function onPointerDown(event) {
        if (!myColor || chess.isGameOver() || chess.turn() !== myColor) {
            return;
        }

        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        const square = event.target.closest('.cb-square')?.dataset.square;
        const piece = square ? buildMap()[square] : null;

        if (!square || !piece || piece.color !== myColor) {
            return;
        }

        event.preventDefault();

        // Sólo arrastrando se elige la pieza; un toque simple no hace nada.
        legalMoves = chess.moves({ square, verbose: true });

        drag = {
            from: square,
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            moved: false,
            ghost: null,
        };

        try {
            boardEl.setPointerCapture(event.pointerId);
        } catch (error) {
            // Puntero sintético sin captura disponible: seguimos igual.
        }
    }

    function onPointerMove(event) {
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }

        if (!drag.moved) {
            const distance = Math.hypot(event.clientX - drag.startX, event.clientY - drag.startY);

            if (distance < DRAG_THRESHOLD) {
                return;
            }

            drag.moved = true;
            selected = drag.from;
            render();
            beginGhost(drag.from);
        }

        event.preventDefault();
        moveGhost(event.clientX, event.clientY);
        setHover(squareFromPoint(event.clientX, event.clientY));
    }

    function onPointerUp(event) {
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }

        const from = drag.from;
        const moved = drag.moved;
        const target = moved ? squareFromPoint(event.clientX, event.clientY) : null;

        finishDrag();

        if (!moved) {
            // Un toque sin arrastre no mueve ni selecciona nada.
            legalMoves = [];

            return;
        }

        if (target && target !== from && legalMoves.some((move) => move.to === target)) {
            attemptMove(from, target);
        } else {
            selected = null;
            legalMoves = [];
            render();
        }
    }

    function onPointerCancel() {
        if (!drag) {
            return;
        }

        finishDrag();
        selected = null;
        legalMoves = [];
        render();
    }

    boardEl.addEventListener('pointerdown', onPointerDown);
    boardEl.addEventListener('pointermove', onPointerMove);
    boardEl.addEventListener('pointerup', onPointerUp);
    boardEl.addEventListener('pointercancel', onPointerCancel);

    /* ------------------------------------------------------------------ *
     * Movimientos
     * ------------------------------------------------------------------ */

    function askPromotion() {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 backdrop-blur-sm';

            const box = document.createElement('div');
            box.className = 'flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900 p-5 text-center';

            const title = document.createElement('p');
            title.className = 'text-sm font-semibold text-slate-300';
            title.textContent = 'Elegí la pieza';

            const row = document.createElement('div');
            row.className = 'flex gap-2';

            for (const type of ['q', 'r', 'b', 'n']) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'flex h-16 w-16 items-center justify-center rounded-xl bg-slate-800 text-4xl transition hover:bg-emerald-600';
                button.style.color = myColor === 'w' ? '#f8fafc' : '#16202f';
                button.textContent = GLYPHS[type];
                button.addEventListener('click', () => {
                    overlay.remove();
                    resolve(type);
                });
                row.appendChild(button);
            }

            box.append(title, row);
            overlay.appendChild(box);
            document.body.appendChild(overlay);
        });
    }

    async function send(url, body) {
        pendingWrites += 1;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(body),
            });

            return response.ok ? await response.json() : null;
        } finally {
            pendingWrites -= 1;
        }
    }

    async function syncState() {
        if (document.hidden || stateSyncInProgress || pendingWrites > 0) {
            return;
        }

        stateSyncInProgress = true;

        try {
            const response = await fetch(`/ajedrez/${config.room}/estado`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`La sincronización respondió ${response.status}.`);
            }

            const payload = await response.json();
            const moves = payload.moves ?? [];
            const movesSignature = JSON.stringify(moves);

            if (movesSignature !== lastSyncedMoves) {
                chess.reset();
                lastMove = null;
                moves.forEach(applyMove);
                lastSyncedMoves = movesSignature;
                selected = null;
                legalMoves = [];
                render();
            }

            (payload.messages ?? []).forEach(appendMessage);

            const playersChanged = presence.white !== payload.whiteJoined
                || presence.black !== payload.blackJoined;

            presence.white = payload.whiteJoined === true;
            presence.black = payload.blackJoined === true;

            if (playersChanged) {
                updatePlayers();
            }

            stateSyncFailed = false;
            syncStatusEl.textContent = 'Sincronización automática activa';
        } catch (error) {
            if (!stateSyncFailed) {
                console.error('No se pudo sincronizar el estado de la sala.', error);
            }

            stateSyncFailed = true;
            syncStatusEl.textContent = 'No se pudo sincronizar; reintentando…';
        } finally {
            stateSyncInProgress = false;
        }
    }

    async function attemptMove(from, to) {
        const promotions = legalMoves.filter((move) => move.to === to && move.promotion);
        let promotion = null;

        if (promotions.length > 0) {
            promotion = await askPromotion();

            if (!promotion) {
                selected = null;
                legalMoves = [];
                render();

                return;
            }
        }

        selected = null;
        legalMoves = [];

        if (!applyMove({ from, to, promotion })) {
            await syncState();

            return;
        }

        render();

        const sent = await send(`/ajedrez/${config.room}/movimiento`, {
            from,
            to,
            promotion,
            clientId: id,
        });

        if (!sent) {
            await syncState();
        }
    }

    function resetLocally() {
        chess.reset();
        selected = null;
        legalMoves = [];
        lastMove = null;
        render();
    }

    /* ------------------------------------------------------------------ *
     * Chat
     * ------------------------------------------------------------------ */

    function formatTime(at) {
        try {
            return new Date(at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (error) {
            return '';
        }
    }

    function authorLabel(color) {
        if (color === 'white') {
            return 'Blancas';
        }

        if (color === 'black') {
            return 'Negras';
        }

        return 'Sistema';
    }

    const renderedMessages = new Set();

    function appendMessage(message) {
        if (!chatListEl) {
            return;
        }

        const messageKey = JSON.stringify([message.color, message.at, message.text]);

        if (renderedMessages.has(messageKey)) {
            return;
        }

        renderedMessages.add(messageKey);
        document.getElementById('chat-empty')?.remove();

        // Los propios van a la derecha; para espectadores, negras a la derecha.
        // config.color usa 'white'/'black' (igual que el servidor), no 'w'/'b'.
        const alignRight = config.color !== null ? message.color === config.color : message.color === 'black';

        const item = document.createElement('li');
        item.className = `flex flex-col ${alignRight ? 'items-end text-right' : 'items-start text-left'}`;

        const meta = document.createElement('span');
        meta.className = 'text-[11px] text-slate-500';
        meta.textContent = `${authorLabel(message.color)} · ${formatTime(message.at)}`;

        const bubble = document.createElement('span');
        bubble.className = [
            'max-w-[85%] rounded-xl px-3 py-1.5 text-sm break-words',
            alignRight ? 'bg-emerald-600/85 text-white' : 'bg-slate-700/70 text-slate-100',
        ].join(' ');
        bubble.textContent = message.text;

        item.append(meta, bubble);
        chatListEl.appendChild(item);
        chatListEl.scrollTop = chatListEl.scrollHeight;
    }

    async function sendChat(text) {
        const trimmed = text.trim();

        if (!trimmed || !myColor) {
            return;
        }

        const sent = await send(`/ajedrez/${config.room}/mensaje`, {
            text: trimmed,
            clientId: id,
        });

        if (!sent) {
            appendMessage({ color: 'system', text: 'No se pudo enviar el mensaje.', at: Date.now() });
        } else {
            appendMessage({ color: config.color, text: trimmed, at: sent.at });
        }
    }

    (config.messages ?? []).forEach(appendMessage);

    if (!myColor) {
        if (chatInputEl) {
            chatInputEl.disabled = true;
            chatInputEl.placeholder = 'Sólo los jugadores pueden escribir';
        }
        chatNoteEl?.classList.remove('hidden');
    }

    chatFormEl?.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!chatInputEl) {
            return;
        }

        const text = chatInputEl.value;
        chatInputEl.value = '';
        sendChat(text);
    });

    /* ------------------------------------------------------------------ *
     * Tiempo real
     * ------------------------------------------------------------------ */

    if (window.Echo) {
        window.Echo.channel(`chess.${config.room}`)
            .listen('.ChessUpdate', (event) => {
                if (event.type === 'reset') {
                    resetLocally();

                    return;
                }

                if (event.clientId && event.clientId === id) {
                    return;
                }

                if (event.color && presence[event.color] !== true) {
                    presence[event.color] = true;
                    updatePlayers();
                }

                if (!applyMove(event)) {
                    syncState();

                    return;
                }

                selected = null;
                legalMoves = [];
                render();
            })
            .listen('.ChessMessage', (event) => {
                // Recibir un mensaje también confirma que ese jugador está conectado.
                if (event.color && presence[event.color] !== true) {
                    presence[event.color] = true;
                    updatePlayers();
                }

                if (event.clientId && event.clientId === id) {
                    return;
                }

                appendMessage(event);
            });
    }

    syncState();
    window.setInterval(syncState, 1000);
    document.addEventListener('visibilitychange', syncState);

    resetBtn?.addEventListener('click', async () => {
        if (!config.color) {
            return;
        }

        resetLocally();
        await send(`/ajedrez/${config.room}/reiniciar`, { clientId: id });
    });

    copyBtn?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(config.room);
            copyBtn.textContent = '¡Copiado!';
            setTimeout(() => {
                copyBtn.textContent = 'Copiar código';
            }, 1800);
        } catch (error) {
            copyBtn.textContent = config.room;
        }
    });

    render();
    updatePlayers();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChessGame);
} else {
    initChessGame();
}
