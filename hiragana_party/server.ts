// Deno Deploy用 ひらがなパーティー WebSocketサーバー
// 最大40人同時接続対応

interface Player {
  id: string;
  name: string;
  score: number;
  socket: WebSocket;
  isHost: boolean;
}

interface Room {
  code: string;
  hostKey: string;
  players: Map<string, Player>;
  phase: 'lobby' | 'countdown' | 'play' | 'recap' | 'results';
  gameId: string | null;
  questionIndex: number;
  totalQuestions: number;
  sequence: number[];
  timePerQuestion: number;
  countdownStartAt: number | null;
  questionStartAt: number | null;
  countdownDuration: number;
  recapDuration: number;
  createdAt: number;
}

const rooms = new Map<string, Room>();
const playerToRoom = new Map<string, string>();
const MAX_PLAYERS = 40;

// コード整形
function sanitizeRoomCode(raw: unknown): string {
  if (typeof raw !== 'string' && typeof raw !== 'number') return '';
  const trimmed = String(raw).trim();
  return /^\d{6}$/.test(trimmed) ? trimmed : '';
}

// ルームコード生成（6桁数字）
function generateRoomCode(): string {
  const digits = '0123456789';
  let code = '';
  for (let i = 0; i < 6; i++) {
    code += digits.charAt(Math.floor(Math.random() * digits.length));
  }
  return sanitizeRoomCode(code); // 念のため整形
}

// ホストキー生成
function generateHostKey(): string {
  return crypto.randomUUID();
}

// 現在時刻（ミリ秒）
function now(): number {
  return Date.now();
}

// 全プレイヤーに状態をブロードキャスト
function broadcastState(room: Room) {
  const state = {
    type: 'state',
    serverTime: now(),
    phase: room.phase,
    gameId: room.gameId,
    questionIndex: room.questionIndex,
    totalQuestions: room.totalQuestions,
    sequence: room.sequence,
    timePerQuestion: room.timePerQuestion,
    countdownStartAt: room.countdownStartAt,
    questionStartAt: room.questionStartAt,
    countdownDuration: room.countdownDuration,
    recapDuration: room.recapDuration,
    players: Array.from(room.players.values()).map(p => ({
      id: p.id,
      name: p.name,
      score: p.score,
      isHost: p.isHost
    }))
  };

  const message = JSON.stringify(state);
  room.players.forEach(player => {
    if (player.socket.readyState === WebSocket.OPEN) {
      player.socket.send(message);
    }
  });
}

// プレイヤーリストをブロードキャスト
function broadcastPlayers(room: Room) {
  const players = Array.from(room.players.values()).map(p => ({
    id: p.id,
    name: p.name,
    score: p.score,
    isHost: p.isHost
  }));

  const message = JSON.stringify({
    type: 'players',
    players
  });

  room.players.forEach(player => {
    if (player.socket.readyState === WebSocket.OPEN) {
      player.socket.send(message);
    }
  });
}

// ルーム作成
function createRoom(clientId: string): { code: string; hostKey: string } {
  const code = generateRoomCode();
  const hostKey = generateHostKey();

  rooms.set(code, {
    code,
    hostKey,
    players: new Map(),
    phase: 'lobby',
    gameId: null,
    questionIndex: 0,
    totalQuestions: 20,
    sequence: [],
    timePerQuestion: 10000,
    countdownStartAt: null,
    questionStartAt: null,
    countdownDuration: 3000,
    recapDuration: 3000,
    createdAt: now()
  });

  return { code, hostKey };
}

// プレイヤー参加
function joinRoom(code: string, playerId: string, name: string, socket: WebSocket, isHost = false): boolean {
  const room = rooms.get(code);
  if (!room) return false;
  if (room.players.size >= MAX_PLAYERS) return false;

  room.players.set(playerId, {
    id: playerId,
    name,
    score: 0,
    socket,
    isHost
  });

  playerToRoom.set(playerId, code);
  broadcastPlayers(room);
  return true;
}

// プレイヤー退出
function leaveRoom(playerId: string) {
  const roomCode = playerToRoom.get(playerId);
  if (!roomCode) return;

  const room = rooms.get(roomCode);
  if (!room) return;

  room.players.delete(playerId);
  playerToRoom.delete(playerId);

  if (room.players.size === 0) {
    // 全員退出したらルーム削除
    rooms.delete(roomCode);
  } else {
    broadcastPlayers(room);
  }
}

// ゲーム開始
function startGame(room: Room, totalQuestions: number, sequence: number[], timePerQuestion: number) {
  room.gameId = crypto.randomUUID();
  room.totalQuestions = totalQuestions;
  room.sequence = sequence;
  room.timePerQuestion = timePerQuestion;
  room.questionIndex = 0;
  room.phase = 'countdown';
  room.countdownStartAt = now();
  room.questionStartAt = null;

  // スコアリセット
  room.players.forEach(p => p.score = 0);

  broadcastState(room);

  // カウントダウン後にplay開始
  setTimeout(() => {
    if (room.phase === 'countdown' && room.questionIndex === 0) {
      room.phase = 'play';
      room.questionStartAt = now();
      broadcastState(room);

      // タイムリミットがある場合、自動で次へ
      if (timePerQuestion > 0) {
        setTimeout(() => {
          if (room.questionIndex === 0 && room.phase === 'play') {
            nextQuestion(room);
          }
        }, timePerQuestion);
      }
    }
  }, room.countdownDuration);
}

// 次の問題へ
function nextQuestion(room: Room) {
  const nextIndex = room.questionIndex + 1;

  if (nextIndex >= room.totalQuestions) {
    // ゲーム終了
    room.phase = 'results';
    room.questionStartAt = null;
    room.countdownStartAt = null;
    broadcastState(room);
    return;
  }

  // 次の問題へ
  room.questionIndex = nextIndex;
  room.phase = 'recap';
  room.questionStartAt = null;
  room.countdownStartAt = null;
  broadcastState(room);

  // recap後にcountdown
  setTimeout(() => {
    if (room.questionIndex === nextIndex && room.phase === 'recap') {
      room.phase = 'countdown';
      room.countdownStartAt = now();
      broadcastState(room);

      // countdown後にplay
      setTimeout(() => {
        if (room.questionIndex === nextIndex && room.phase === 'countdown') {
          room.phase = 'play';
          room.questionStartAt = now();
          broadcastState(room);

          // タイムリミットがある場合、自動で次へ
          if (room.timePerQuestion > 0) {
            setTimeout(() => {
              if (room.questionIndex === nextIndex && room.phase === 'play') {
                nextQuestion(room);
              }
            }, room.timePerQuestion);
          }
        }
      }, room.countdownDuration);
    }
  }, room.recapDuration);
}

// 問題完了（スコア加算）
function completeQuestion(room: Room, playerId: string, questionIndex: number) {
  if (room.questionIndex !== questionIndex) return;
  if (room.phase !== 'play') return;

  const player = room.players.get(playerId);
  if (!player) return;

  player.score++;
  broadcastState(room);
}

// WebSocketハンドラー
async function handleWebSocket(request: Request): Promise<Response> {
  const upgrade = request.headers.get("upgrade") || "";
  if (upgrade.toLowerCase() !== "websocket") {
    return new Response("Expected websocket", { status: 400 });
  }

  const { socket, response } = Deno.upgradeWebSocket(request);
  let playerId: string | null = null;

  socket.onopen = () => {
    console.log("WebSocket connected");
  };

  socket.onmessage = (event) => {
    try {
      const data = JSON.parse(event.data);

      switch (data.type) {
        case 'create': {
          // ルーム作成
          const { code, hostKey } = createRoom(data.clientId);
          playerId = data.clientId;
          socket.send(JSON.stringify({
            type: 'created',
            code,
            hostKey
          }));
          break;
        }

        case 'join': {
          // ルーム参加
          const { code, name, clientId, isHost } = data;
          const normalizedCode = sanitizeRoomCode(code);
          if (normalizedCode.length !== 6) {
            socket.send(JSON.stringify({
              type: 'error',
              message: '6桁の数字コードを入力してください'
            }));
            break;
          }
          const success = joinRoom(normalizedCode, clientId, name, socket, isHost || false);
          if (success) {
            playerId = clientId;
            socket.send(JSON.stringify({
              type: 'joined',
              playerId: clientId,
              code: normalizedCode
            }));

            const room = rooms.get(normalizedCode);
            if (room) {
              socket.send(JSON.stringify({
                type: 'state',
                serverTime: now(),
                phase: room.phase,
                gameId: room.gameId,
                questionIndex: room.questionIndex,
                totalQuestions: room.totalQuestions,
                sequence: room.sequence,
                timePerQuestion: room.timePerQuestion,
                countdownStartAt: room.countdownStartAt,
                questionStartAt: room.questionStartAt,
                countdownDuration: room.countdownDuration,
                recapDuration: room.recapDuration,
                players: Array.from(room.players.values()).map(p => ({
                  id: p.id,
                  name: p.name,
                  score: p.score,
                  isHost: p.isHost
                }))
              }));
            }
          } else {
            socket.send(JSON.stringify({
              type: 'error',
              message: 'ルームが見つからないか、満員です'
            }));
          }
          break;
        }

        case 'start': {
          // ゲーム開始
          const { code, hostKey, totalQuestions, sequence, timePerQuestion } = data;
          const room = rooms.get(code);
          if (room && room.hostKey === hostKey) {
            startGame(room, totalQuestions, sequence, timePerQuestion);
            socket.send(JSON.stringify({
              type: 'started',
              gameId: room.gameId
            }));
          }
          break;
        }

        case 'complete': {
          // 問題完了
          const { code, playerId: pid, questionIndex } = data;
          const room = rooms.get(code);
          if (room) {
            completeQuestion(room, pid, questionIndex);
          }
          break;
        }

        case 'next': {
          // 次の問題へ（ホストのみ）
          const { code, hostKey } = data;
          const room = rooms.get(code);
          if (room && room.hostKey === hostKey) {
            nextQuestion(room);
          }
          break;
        }

        case 'ping': {
          // Ping/Pong
          socket.send(JSON.stringify({
            type: 'pong',
            serverTime: now()
          }));
          break;
        }
      }
    } catch (error) {
      console.error("WebSocket message error:", error);
    }
  };

  socket.onclose = () => {
    console.log("WebSocket closed");
    if (playerId) {
      leaveRoom(playerId);
    }
  };

  socket.onerror = (error) => {
    console.error("WebSocket error:", error);
  };

  return response;
}

// HTTPハンドラー
async function handleRequest(request: Request): Promise<Response> {
  const url = new URL(request.url);

  // WebSocketアップグレード
  if (url.pathname === "/ws") {
    return handleWebSocket(request);
  }

  // ヘルスチェック
  if (url.pathname === "/") {
    return new Response("Hiragana Party Server - Running", { status: 200 });
  }

  // 統計情報
  if (url.pathname === "/stats") {
    return new Response(JSON.stringify({
      rooms: rooms.size,
      totalPlayers: Array.from(rooms.values()).reduce((sum, room) => sum + room.players.size, 0)
    }), {
      headers: { "Content-Type": "application/json" }
    });
  }

  return new Response("Not found", { status: 404 });
}

// サーバー起動
Deno.serve(handleRequest);
