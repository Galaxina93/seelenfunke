import { WebSocketServer } from 'ws';
import http from 'http';
import dotenv from 'dotenv';
import WebSocket from 'ws';
import wavefile from 'wavefile';
const { WaveFile } = wavefile; 

import fs from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

// Zuerst dotenv konfigurieren, damit APP_URL sofort verfügbar ist
dotenv.config();

const BRIDGE_VERSION = 'v1.3-stable';
const bridgeStartedAt = new Date().toISOString();
let activeLiveSessions = 0;
let activeTwilioCalls = 0;
let totalLiveSessions = 0;
let totalErrors = 0;
let lastIssue = null;

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Automatische Ursachenanalyse für WebSocket Close-Codes & Fehlermeldungen
function diagnoseWsIssue(code, reason, source = 'google') {
    const r = (reason || '').toString();
    const rLower = r.toLowerCase();
    const numCode = Number(code);

    if (numCode === 1000 || numCode === 1001) {
        return {
            category: 'normal_close',
            probable_cause: 'Reguläres Sitzungsende durch Client oder Server.',
            recommended_action: 'Keine Aktion erforderlich.',
            severity: 'info'
        };
    }

    if (numCode === 1008) {
        if (rLower.includes('requested entity was not found') || rLower.includes('not found')) {
            return {
                category: 'model_or_protocol_entity_not_found',
                probable_cause: 'Google Live API lehnte Modellnamen, leeren/ungültigen Turn-Frame oder nicht unterstützte Live-Konfiguration ab (Code 1008: Requested entity was not found).',
                recommended_action: 'Prüfe setup.model (muss models/gemini-3.1-flash-live-preview sein) sowie clientContent/realtimeInput-Frames in recent_events.',
                severity: 'warning'
            };
        }
        if (rLower.includes('api key') || rLower.includes('permission') || rLower.includes('unauthenticated')) {
            return {
                category: 'auth_or_api_key_rejected',
                probable_cause: 'Google API-Key ungültig, abgelaufen oder Referer/Origin-Restriktion blockiert die Verbindung.',
                recommended_action: 'Prüfe GEMINI_API_KEY in .env sowie die Domain-Freigaben in der Google Cloud Console.',
                severity: 'error'
            };
        }
        return {
            category: 'policy_violation_1008',
            probable_cause: `Google Live API hat die Verbindung wegen einer Richtlinien-/Protokollverletzung geschlossen (${r || 'Code 1008'}).`,
            recommended_action: 'Prüfe recent_events und die zuletzt gesendeten Tool-/Audio-Pakete im Payload.',
            severity: 'warning'
        };
    }

    if (numCode === 1007) {
        if (rLower.includes('content_type_audio') || rLower.includes('mediachunks')) {
            return {
                category: 'deprecated_audio_payload_1007',
                probable_cause: 'Veraltetes Audio-Format (realtimeInput.mediaChunks statt realtimeInput.audio) wurde an Gemini 3.1 gesendet.',
                recommended_action: 'Sicherstellen, dass die Audio-Normalisierung in server-twilio.js aktiv ist und Clients realtimeInput.audio nutzen.',
                severity: 'warning'
            };
        }
        return {
            category: 'invalid_frame_payload_1007',
            probable_cause: `Ungültige JSON-/Schema-Struktur in einer WebSocket-Nachricht (${r || 'Code 1007'}).`,
            recommended_action: 'Prüfe recent_events auf fehlerhafte Tool-Parameter oder korrupte Base64-Audio-Frames.',
            severity: 'warning'
        };
    }

    if (numCode === 1009) {
        return {
            category: 'message_too_big_1009',
            probable_cause: 'Ein einzelnes WebSocket-Paket (z. B. großes Bild, Kamera-Frame oder zu große Tool-Antwort) hat das Limit überschritten.',
            recommended_action: 'Bild-/Audio-Chunkgröße oder Tool-Response-Payload reduzieren.',
            severity: 'warning'
        };
    }

    if (numCode === 1011) {
        if (rLower.includes('quota') || rLower.includes('resource_exhausted') || rLower.includes('rate')) {
            return {
                category: 'google_quota_exceeded_1011',
                probable_cause: 'Google Gemini API Rate-Limit oder Kontingent (Quota) für gleichzeitige Live-Sessions erreicht.',
                recommended_action: 'Kontingente in Google AI Studio / Cloud Console prüfen und parallele Sessions begrenzen.',
                severity: 'error'
            };
        }
        return {
            category: 'upstream_internal_error_1011',
            probable_cause: `Interner Serverfehler bei ${source === 'google' ? 'Google Gemini Live API' : 'Client'} (${r || 'Code 1011'}).`,
            recommended_action: 'Meist temporär seitens Google; bei Häufung Systemstatus von Google AI Studio prüfen.',
            severity: 'warning'
        };
    }

    if (numCode === 4001 || numCode === 4003) {
        return {
            category: 'bridge_token_auth_failed',
            probable_cause: `Token-Verifizierung zwischen Client und Laravel-Backend fehlgeschlagen (${r || 'Code ' + numCode}).`,
            recommended_action: 'Prüfe APP_URL in .env, Erreichbarkeit von /api/ai/verify-token sowie Cache-/Redis-Status auf dem Server.',
            severity: 'warning'
        };
    }

    return {
        category: `ws_close_${numCode || 'unknown'}`,
        probable_cause: `Ungewöhnlicher Verbindungsabbruch auf ${source}-Seite (Code: ${code}, Grund: ${r || 'Kein Grund angegeben'}).`,
        recommended_action: 'Prüfe recent_events, Netzwerkstabilität und die letzten Einträge in bridge.log.',
        severity: 'warning'
    };
}

// Sende Log-Einträge an das Laravel System-Log
function sendLogToBackend(type, message, actionId, payload = {}) {
    if (type === 'error' || type === 'crash' || type === 'warning') {
        totalErrors++;
        lastIssue = {
            timestamp: new Date().toISOString(),
            type,
            message,
            action_id: actionId,
            diagnosis: payload?.diagnosis || null
        };
    }

    const enrichedPayload = {
        bridge_version: BRIDGE_VERSION,
        bridge_uptime_sec: Math.floor(process.uptime()),
        active_live_sessions: activeLiveSessions,
        active_twilio_calls: activeTwilioCalls,
        memory_rss_mb: Math.round(process.memoryUsage().rss / 1024 / 1024),
        timestamp_iso: new Date().toISOString(),
        ...payload
    };

    const backendUrl = process.env.APP_URL || 'http://localhost';
    fetch(`${backendUrl}/api/log/websocket`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            type: type, // 'crash', 'error', 'info', 'warning'
            message: message,
            action_id: actionId || `ws_${type}_${Date.now()}`,
            payload: enrichedPayload
        })
    }).catch(e => console.error("Error sending log to backend:", e.message));
}

process.on('uncaughtException', (err) => {
    const stack = err.stack || err.toString();
    fs.writeFileSync(join(__dirname, 'crash.log'), stack);
    console.error('CRITICAL UNCAUGHT EXCEPTION:', stack);
    
    // Crash-Log an Laravel senden
    sendLogToBackend('crash', `WebSocket-Server Absturz (uncaughtException): ${err.message || err}`, 'websocket:crash', { stack: stack });
    
    setTimeout(() => {
        process.exit(1);
    }, 800);
});

process.on('unhandledRejection', (reason, promise) => {
    const stack = (reason && reason.stack) || String(reason);
    debugLog('Unhandled Rejection at promise:', reason);
    sendLogToBackend('error', `Unhandled Rejection: ${reason}`, 'websocket:unhandled_rejection', { stack: stack });
});

function safeSend(ws, message) {
    if (!ws || ws.readyState !== WebSocket.OPEN) return;
    try {
        ws.send(message, (err) => {
            if (err) {
                debugLog('WebSocket safeSend callback error:', err);
            }
        });
    } catch (e) {
        debugLog('WebSocket safeSend catch error:', e);
    }
}

function debugLog(msg, err) {
    const timestamp = new Date().toISOString();
    let formattedMsg = msg;
    if (err) {
        formattedMsg += ` | Error: ${err.message || err}`;
        if (err.stack) {
            formattedMsg += `\n${err.stack}`;
        }
    }
    
    // In Konsole ausgeben
    console.log(formattedMsg);
    
    // In Datei schreiben
    try {
        fs.appendFileSync(join(__dirname, 'bridge.log'), `[${timestamp}] ${formattedMsg}\n`);
    } catch (e) {
        console.error('Logging to bridge.log failed:', e.message);
    }

    // Integriere Fehlermeldungen direkt ins globale System-Log
    if (err) {
        sendLogToBackend('error', formattedMsg, 'websocket:error', { msg: msg, error: err.toString(), stack: err.stack });
    }
}

// Log-Rotation beim Start
try {
    const logPath = join(__dirname, 'bridge.log');
    if (fs.existsSync(logPath)) {
        const stats = fs.statSync(logPath);
        if (stats.size > 5 * 1024 * 1024) { // 5MB limit
            fs.writeFileSync(logPath, `--- Log rotierte am ${new Date().toISOString()} ---\n`, 'utf8');
        }
    }
} catch (e) {
    console.error('Fehler bei Log-Rotation:', e.message);
}

debugLog(`--- NODE JS SERVER RESTARTED (VERSION: ${BRIDGE_VERSION}) ---`);
sendLogToBackend('info', 'Node.js Audio-Brücke erfolgreich gestartet.', 'websocket:start');

const PORT = process.env.PORT || process.env.TWILIO_WS_PORT || 8081;
const GOOGLE_API_KEY = process.env.GEMINI_API_KEY || process.env.GOOGLE_API_KEY;

if (!GOOGLE_API_KEY) {
    console.error("❌ GEMINI_API_KEY is missing in .env!");
    process.exit(1);
}

const server = http.createServer((req, res) => {
    if (req.url && (req.url === '/health' || req.url.startsWith('/health?'))) {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'ok',
            bridge_version: BRIDGE_VERSION,
            started_at: bridgeStartedAt,
            uptime_seconds: Math.floor(process.uptime()),
            active_live_sessions: activeLiveSessions,
            active_twilio_calls: activeTwilioCalls,
            total_live_sessions: totalLiveSessions,
            total_errors: totalErrors,
            memory_mb: Math.round(process.memoryUsage().rss / 1024 / 1024),
            last_issue: lastIssue
        }, null, 2));
        return;
    }
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('Seelenfunke Twilio-Gemini Audio Bridge is running.\n');
});

const wss = new WebSocketServer({ server });

function isSendableCloseCode(code) {
    if (typeof code !== 'number' || isNaN(code)) return false;
    if (code === 1000 || code === 1001 || code === 1002 || code === 1003 || code === 1007 || code === 1008 || code === 1009 || code === 1010 || code === 1011) {
        return true;
    }
    if (code >= 3000 && code <= 4999) {
        return true;
    }
    return false;
}

function safeClose(ws, code, reason) {
    if (!ws || ws.readyState === WebSocket.CLOSED || ws.readyState === WebSocket.CLOSING) return;
    try {
        if (isSendableCloseCode(code)) {
            let cleanReason = undefined;
            if (reason) {
                cleanReason = reason.toString();
                // Close reason must not be longer than 123 bytes UTF-8 encoded
                if (Buffer.byteLength(cleanReason, 'utf8') > 123) {
                    cleanReason = cleanReason.substring(0, 100) + '...';
                }
            }
            ws.close(code, cleanReason);
        } else {
            ws.close();
        }
    } catch (e) {
        debugLog('Fehler beim sicheren Schließen des WebSockets:', e);
        try {
            ws.close();
        } catch (err) {}
    }
}

function initGeminiLiveProxy(clientWs, creds, reqMeta = {}) {
    debugLog('🧠 Gemini Live Proxy: Verbinde zu Google Gemini Live API...');
    activeLiveSessions++;
    totalLiveSessions++;
    const sessionStartedAt = Date.now();
    const sessionStartedIso = new Date().toISOString();
    let clientAudioPacketsReceived = 0;
    let googleMessagesReceived = 0;
    let googleAudioChunksReceived = 0;
    let toolCallsCount = 0;
    let lastToolCall = null;
    let requestedModel = null;
    let effectiveModel = 'models/gemini-3.1-flash-live-preview';
    const normalizationsApplied = {
        model_rewritten: false,
        media_chunks_converted: 0,
        empty_interrupts_dropped: 0
    };
    const recentEvents = [];
    const recordEvent = (event, detail = '') => {
        recentEvents.push({
            t_ms: Date.now() - sessionStartedAt,
            event,
            detail: typeof detail === 'string' ? detail.substring(0, 160) : JSON.stringify(detail).substring(0, 160)
        });
        if (recentEvents.length > 15) {
            recentEvents.shift();
        }
    };
    recordEvent('proxy_init', `agent=${creds.agent_name || 'Funkira'}, user=${creds.user_id || 'guest'}, ua=${reqMeta.user_agent || 'unknown'}`);

    let currentSessionId = creds.chat_session_id;
    let aiTurnTranscript = "";
    let userTurnTranscript = "";
    let lastPersistedAiText = "";
    
    const HOST = "generativelanguage.googleapis.com";
    const WS_URL = `wss://${HOST}/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=${creds.api_key}`;
    
    const googleWs = new WebSocket(WS_URL, {
        headers: {
            'Referer': process.env.APP_URL || 'https://stage.mein-seelenfunke.de'
        },
        origin: process.env.APP_URL || 'https://stage.mein-seelenfunke.de'
    });
    const googleQueue = [];
    let isGoogleSetupComplete = false;

    let isCleanedUp = false;
    let sessionResumptionUpdatesCount = 0;
    const googlePingInterval = setInterval(() => {
        if (googleWs.readyState === WebSocket.OPEN) {
            try { googleWs.ping(); } catch (e) {}
        }
        if (clientWs && clientWs.readyState === WebSocket.OPEN) {
            try { clientWs.ping(); } catch (e) {}
        }
    }, 15000);

    function cleanup() {
        if (isCleanedUp) return;
        isCleanedUp = true;
        activeLiveSessions = Math.max(0, activeLiveSessions - 1);
        clearInterval(googlePingInterval);
        debugLog('🧠 Gemini Live Proxy: Keep-Alive/Ping Intervall gestoppt.');
    }

    function buildSessionDiagnostics(code, reason, source = 'google') {
        const cleanReason = reason ? reason.toString() : null;
        const ua = reqMeta.user_agent || 'unknown';
        return {
            code: code ?? null,
            reason: cleanReason,
            source,
            diagnosis: diagnoseWsIssue(code, cleanReason, source),
            session_telemetry: {
                session_started_at: sessionStartedIso,
                session_duration_sec: Math.round((Date.now() - sessionStartedAt) / 1000),
                agent_name: creds.agent_name || 'Funkira',
                agent_id: creds.agent_id || null,
                user_id: creds.user_id || null,
                chat_session_id: currentSessionId || creds.chat_session_id || null,
                client_ip: reqMeta.client_ip || 'unknown',
                user_agent: ua,
                client_type: ua.toLowerCase().includes('okhttp') ? 'Android App (OkHttp)' : 'Web Browser',
                requested_model: requestedModel,
                effective_model: effectiveModel,
                is_google_setup_complete: isGoogleSetupComplete,
                queued_messages_pending: googleQueue.length,
                client_audio_packets_sent: clientAudioPacketsReceived,
                google_messages_received: googleMessagesReceived,
                google_audio_chunks_received: googleAudioChunksReceived,
                tool_calls_count: toolCallsCount,
                last_tool_call: lastToolCall,
                normalizations_applied: normalizationsApplied
            },
            recent_events: recentEvents
        };
    }

    const persistTranscript = (role, text) => {
        const cleanText = (text || "").trim();
        if (!cleanText) return;
        if (role === 'assistant' && cleanText === lastPersistedAiText) return;

        const targetSessionId = currentSessionId || creds.chat_session_id;
        if (!targetSessionId) {
            debugLog(`⚠️ Kann Live-Transkript (${role}) nicht speichern: Keine chat_session_id vorhanden.`);
            return;
        }

        const backendUrl = process.env.APP_URL || 'http://localhost';
        const payload = {
            session_id: targetSessionId,
            role: role,
            content: cleanText,
            agent_id: creds.agent_id || null,
            agent_name: creds.agent_name || 'Funkira',
            user_id: creds.user_id || null
        };

        debugLog(`💾 Sende Live-Transkript (${role}) an Backend: "${cleanText.substring(0, 60)}..." (Session: ${targetSessionId})`);

        fetch(`${backendUrl}/api/ai/save-live-transcript`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(async res => {
            if (!res.ok) {
                const errText = await res.text();
                throw new Error(`HTTP ${res.status}: ${errText.substring(0, 100)}`);
            }
            return res.json();
        })
        .then(resData => {
            debugLog(`✅ Live-Transkript (${role}) im Backend gespeichert: status=${resData.status}, id=${resData.id}`);
            if (role === 'assistant') {
                lastPersistedAiText = cleanText;
            }
            safeSend(clientWs, JSON.stringify({
                type: 'liveTranscriptPersisted',
                role: role,
                content: cleanText,
                status: resData.status,
                id: resData.id
            }));
        })
        .catch(err => {
            debugLog(`❌ Fehler beim Speichern des Live-Transkripts (${role})`, err);
        });
    };
    
    googleWs.on('open', () => {
        debugLog('🧠 Gemini Live Proxy: Verbindung zu Google hergestellt.');
        recordEvent('google_ws_open', 'Connected to BidiGenerateContent v1beta');
        // Nur das Setup senden! Alle anderen Nachrichten müssen warten, bis setupComplete da ist.
        for (let i = 0; i < googleQueue.length; i++) {
            const rawMsg = googleQueue[i];
            try {
                const parsed = JSON.parse(rawMsg.toString());
                if (parsed.setup) {
                    debugLog('🧠 Gemini Live Proxy: Sende Setup an Google...');
                    recordEvent('google_setup_sent', `model=${parsed.setup.model}`);
                    safeSend(googleWs, rawMsg);
                    googleQueue.splice(i, 1);
                    break;
                }
            } catch(e) {}
        }
    });
    
    googleWs.on('message', (message) => {
        googleMessagesReceived++;
        try {
            const data = JSON.parse(message.toString());
            if (data.setupComplete) {
                isGoogleSetupComplete = true;
                recordEvent('google_setup_complete', `flushing_queue=${googleQueue.length}`);
                debugLog('🧠 Gemini Live Proxy: Setup Complete von Google empfangen.');
                if (googleQueue.length > 0) {
                    debugLog(`📦 Sende ${googleQueue.length} nach Setup gepufferte Client-Nachrichten an Google...`);
                    while (googleQueue.length > 0) {
                        const msg = googleQueue.shift();
                        safeSend(googleWs, msg);
                    }
                }
            } else if (data.toolCall) {
                const fnNames = (data.toolCall.functionCalls || []).map(fc => fc.name).join(', ');
                toolCallsCount++;
                lastToolCall = fnNames || 'unknown';
                recordEvent('google_tool_call', fnNames);
                debugLog(`🛠️ Gemini Live Proxy: ToolCall von Google empfangen: ${fnNames}`);
            } else if (data.serverContent) {
                let scLog = '';
                if (data.serverContent.outputTranscription && data.serverContent.outputTranscription.text) {
                    scLog += ' [OT: "' + data.serverContent.outputTranscription.text + '"]';
                    aiTurnTranscript += data.serverContent.outputTranscription.text;
                } else if (data.serverContent.outputAudioTranscription && data.serverContent.outputAudioTranscription.text) {
                    scLog += ' [OAT: "' + data.serverContent.outputAudioTranscription.text + '"]';
                    aiTurnTranscript += data.serverContent.outputAudioTranscription.text;
                }

                if (data.serverContent.inputTranscription && data.serverContent.inputTranscription.text) {
                    scLog += ' [IT: "' + data.serverContent.inputTranscription.text + '"]';
                    userTurnTranscript += data.serverContent.inputTranscription.text;
                }

                // Sobald die KI antwortet, sichern wir eventuell per Google empfangenes User-Audio
                if ((data.serverContent.modelTurn || data.serverContent.outputTranscription) && userTurnTranscript.trim()) {
                    persistTranscript('user', userTurnTranscript.trim());
                    userTurnTranscript = "";
                }

                if (data.serverContent.interrupted) {
                    scLog += ' [INTERRUPTED]';
                    recordEvent('google_interrupted');
                    if (aiTurnTranscript.trim()) {
                        persistTranscript('assistant', aiTurnTranscript.trim());
                        aiTurnTranscript = "";
                    }
                }
                if (data.serverContent.generationComplete) {
                    scLog += ' [GEN_COMPLETE]';
                    if (aiTurnTranscript.trim()) {
                        persistTranscript('assistant', aiTurnTranscript.trim());
                        aiTurnTranscript = "";
                    }
                }
                if (data.serverContent.turnComplete) {
                    scLog += ' [TURN_COMPLETE]';
                    recordEvent('google_turn_complete');
                    if (aiTurnTranscript.trim()) {
                        persistTranscript('assistant', aiTurnTranscript.trim());
                        aiTurnTranscript = "";
                    }
                }
                if (data.serverContent.modelTurn) {
                    const audioParts = (data.serverContent.modelTurn.parts || []).filter(p => p.inlineData).length;
                    googleAudioChunksReceived += audioParts;
                    scLog += ' [AudioParts: ' + audioParts + ']';
                }
                debugLog('🧠 Gemini Live Proxy: Server Content von Google empfangen:' + (scLog || (' ' + JSON.stringify(data.serverContent).substring(0, 150))));
            } else if (data.sessionResumptionUpdate && Object.keys(data).length === 1) {
                sessionResumptionUpdatesCount++;
                if (sessionResumptionUpdatesCount === 1) {
                    recordEvent('google_session_resumption', 'Session resumption handle active');
                }
            } else {
                recordEvent('google_other_msg', Object.keys(data).join(','));
                debugLog('🧠 Gemini Live Proxy: Sonstige Nachricht von Google: ' + JSON.stringify(data).substring(0, 150));
            }
        } catch (e) {
            debugLog('🧠 Gemini Live Proxy: Fehler beim Parsen der Google-Nachricht oder Binärdaten: ' + e.message);
        }

        safeSend(clientWs, message);
    });
    
    googleWs.on('close', (code, reason) => {
        const wasAlreadyCleanedUp = isCleanedUp;
        const cleanReason = reason ? reason.toString() : 'Keine';
        recordEvent('google_ws_close', `code=${code}, reason=${cleanReason}`);
        cleanup();
        if (aiTurnTranscript.trim()) {
            debugLog('🧠 Gemini Live Proxy: Google Verbindung getrennt mit verbleibendem KI-Text. Persistiere...');
            persistTranscript('assistant', aiTurnTranscript.trim());
            aiTurnTranscript = "";
        }
        if (userTurnTranscript.trim()) {
            persistTranscript('user', userTurnTranscript.trim());
            userTurnTranscript = "";
        }
        debugLog(`🧠 Gemini Live Proxy: Google Verbindung geschlossen (${code}): ${cleanReason}`);
        
        // Nur loggen, wenn Google die Verbindung tatsächlich zuerst geschlossen hat
        // (verhindert doppelte Folge-Logs mit Code 1000, nachdem der Client die Sitzung beendet hat)
        if (!wasAlreadyCleanedUp) {
            sendLogToBackend(
                code === 1000 || code === 1001 || code === 1005 || code === 1006 ? 'info' : 'warning',
                `Google Live API Verbindung geschlossen. Code: ${code}. Reason: ${cleanReason}`,
                'websocket:disconnect:google',
                buildSessionDiagnostics(code, reason ? reason.toString() : null, 'google')
            );
        }
        
        safeClose(clientWs, code, reason);
    });
    
    googleWs.on('error', (err) => {
        recordEvent('google_ws_error', err.message || String(err));
        cleanup();
        if (aiTurnTranscript.trim()) {
            persistTranscript('assistant', aiTurnTranscript.trim());
            aiTurnTranscript = "";
        }
        debugLog('🧠 Gemini Live Proxy: Google WebSocket Fehler', err);
        sendLogToBackend(
            'error',
            `Google Live API WebSocket-Fehler: ${err.message || err}`,
            'websocket:error:google',
            buildSessionDiagnostics(1011, err.message || String(err), 'google')
        );
        safeSend(clientWs, JSON.stringify({ error: 'Google Gemini connection error' }));
    });
    
    clientWs.on('message', (message) => {
        if (isCleanedUp || googleWs.readyState === WebSocket.CLOSING || googleWs.readyState === WebSocket.CLOSED) {
            return;
        }

        let data = null;
        let outgoingMessage = message;
        try {
            data = JSON.parse(message.toString());
            if (data && data.type === 'ping') {
                safeSend(clientWs, JSON.stringify({ type: 'pong' }));
                return;
            }
            if (data && data.type === 'setSessionId' && data.session_id) {
                currentSessionId = data.session_id;
                recordEvent('client_set_session_id', String(currentSessionId));
                debugLog('🧠 Gemini Live Proxy: Client aktualisiert chat_session_id auf: ' + currentSessionId);
                return;
            }
            if (data && data.setup) {
                requestedModel = data.setup.model || null;
                const DEFAULT_LIVE_MODEL = 'models/gemini-3.1-flash-live-preview';
                const VALID_LIVE_MODELS = [
                    'models/gemini-3.1-flash-live-preview',
                    'models/gemini-2.0-flash-live-001'
                ];
                let setupModified = false;
                if (!data.setup.model || !VALID_LIVE_MODELS.includes(data.setup.model)) {
                    debugLog(`⚠️ Gemini Live Proxy: Client sendete veraltetes/inkompatibles Modell "${data.setup.model}". Normalisiere auf "${DEFAULT_LIVE_MODEL}".`);
                    data.setup.model = DEFAULT_LIVE_MODEL;
                    normalizationsApplied.model_rewritten = true;
                    setupModified = true;
                    recordEvent('model_normalized', `${requestedModel} -> ${DEFAULT_LIVE_MODEL}`);
                }
                effectiveModel = data.setup.model;
                if (!data.setup.inputAudioTranscription) {
                    data.setup.inputAudioTranscription = {};
                    setupModified = true;
                }
                if (!data.setup.outputAudioTranscription) {
                    data.setup.outputAudioTranscription = {};
                    setupModified = true;
                }
                if (setupModified) {
                    outgoingMessage = JSON.stringify(data);
                }
                recordEvent('client_setup', `model=${effectiveModel}`);
                debugLog('🧠 Gemini Live Proxy: Client sendet Setup: ' + JSON.stringify(data.setup).substring(0, 150));
            } else if (data && data.realtimeInput) {
                // Normalisiere veraltetes mediaChunks-Audio/Video-Format (z.B. von älteren Android-Clients) auf realtimeInput.audio / realtimeInput.video,
                // um Google Fehler 1007 (CONTENT_TYPE_AUDIO not supported) und 1008 zu verhindern.
                if (Array.isArray(data.realtimeInput.mediaChunks)) {
                    let chunksModified = false;
                    const audioChunkIdx = data.realtimeInput.mediaChunks.findIndex(
                        c => c && typeof c.mimeType === 'string' && c.mimeType.startsWith('audio/pcm')
                    );
                    if (audioChunkIdx !== -1 && !data.realtimeInput.audio) {
                        const audioChunk = data.realtimeInput.mediaChunks[audioChunkIdx];
                        data.realtimeInput.audio = {
                            mimeType: audioChunk.mimeType,
                            data: audioChunk.data
                        };
                        data.realtimeInput.mediaChunks.splice(audioChunkIdx, 1);
                        chunksModified = true;
                    }
                    const imageChunkIdx = data.realtimeInput.mediaChunks.findIndex(
                        c => c && typeof c.mimeType === 'string' && c.mimeType.startsWith('image/')
                    );
                    if (imageChunkIdx !== -1 && !data.realtimeInput.video) {
                        const imageChunk = data.realtimeInput.mediaChunks[imageChunkIdx];
                        data.realtimeInput.video = {
                            mimeType: imageChunk.mimeType,
                            data: imageChunk.data
                        };
                        data.realtimeInput.mediaChunks.splice(imageChunkIdx, 1);
                        chunksModified = true;
                    }
                    if (chunksModified) {
                        if (data.realtimeInput.mediaChunks.length === 0) {
                            delete data.realtimeInput.mediaChunks;
                        }
                        normalizationsApplied.media_chunks_converted++;
                        if (normalizationsApplied.media_chunks_converted === 1) {
                            recordEvent('media_chunks_normalized', 'Converted legacy mediaChunks to realtimeInput.audio/video');
                        }
                        outgoingMessage = JSON.stringify(data);
                    }
                }
                clientAudioPacketsReceived++;
                if (clientAudioPacketsReceived === 1) {
                    recordEvent('first_client_audio', `mime=${data.realtimeInput.audio?.mimeType || 'pcm'}`);
                }
                if (clientAudioPacketsReceived <= 5 || clientAudioPacketsReceived % 100 === 0) {
                    const audioLength = data.realtimeInput.audio?.data?.length 
                        || data.realtimeInput.mediaChunks?.[0]?.data?.length 
                        || 0;
                    debugLog(`🧠 Gemini Live Proxy: Client sendet Audio (Paket #${clientAudioPacketsReceived}, Base64-Länge: ${audioLength})`);
                }
            } else if (data && data.clientContent) {
                // Blockiere fehlerhafte leere Interrupt-Frames (turnComplete: false mit leerem Text " "),
                // die bei manchen Clients (z.B. Android) zu Google Code 1008 (Requested entity was not found) führen.
                const turns = Array.isArray(data.clientContent.turns) ? data.clientContent.turns : [];
                const isWhitespaceOnlyInterrupt = data.clientContent.turnComplete === false
                    && turns.length > 0
                    && turns.every(t => Array.isArray(t.parts) && t.parts.every(p => typeof p.text === 'string' && p.text.trim() === '' && !p.inlineData));
                if (isWhitespaceOnlyInterrupt) {
                    normalizationsApplied.empty_interrupts_dropped++;
                    recordEvent('empty_interrupt_blocked', `dropped=#${normalizationsApplied.empty_interrupts_dropped}`);
                    debugLog('🛡️ Gemini Live Proxy: Ignoriere leeres clientContent-Interrupt-Paket (verhindert Code 1008/1007 Abbruch).');
                    return;
                }
                recordEvent('client_content', `turnComplete=${data.clientContent.turnComplete}`);
                debugLog('🧠 Gemini Live Proxy: Client sendet clientContent: ' + JSON.stringify(data).substring(0, 150));
            } else if (data && data.toolResponse) {
                const respNames = (data.toolResponse.functionResponses || []).map(r => r.name).join(', ');
                recordEvent('client_tool_response', respNames);
                debugLog('🧠 Gemini Live Proxy: Client sendet toolResponse: ' + respNames);
            } else {
                recordEvent('client_other_msg', data ? Object.keys(data).join(',') : 'raw');
                debugLog('🧠 Gemini Live Proxy: Client sendet sonstige Nachricht: ' + JSON.stringify(data).substring(0, 150));
            }
        } catch (e) {
            recordEvent('client_parse_error', e.message);
            debugLog('🧠 Gemini Live Proxy: Fehler beim Parsen der Client-Nachricht', e);
        }

        if (googleWs.readyState === WebSocket.OPEN && (isGoogleSetupComplete || (data && data.setup))) {
            safeSend(googleWs, outgoingMessage);
        } else if (googleWs.readyState === WebSocket.CONNECTING || (googleWs.readyState === WebSocket.OPEN && !isGoogleSetupComplete)) {
            debugLog('🧠 Gemini Live Proxy: Google WS noch nicht bereit für Nutzdaten (SetupComplete=' + isGoogleSetupComplete + '). Nachricht gepuffert. Queue: ' + (googleQueue.length + 1));
            googleQueue.push(outgoingMessage);
        }
    });
    
    clientWs.on('close', (code, reason) => {
        const wasAlreadyCleanedUp = isCleanedUp;
        const cleanReason = reason ? reason.toString() : 'Keine';
        recordEvent('client_ws_close', `code=${code}, reason=${cleanReason}`);
        cleanup();
        if (aiTurnTranscript.trim()) {
            debugLog('🧠 Gemini Live Proxy: Client Verbindung getrennt mit verbleibendem KI-Text. Persistiere...');
            persistTranscript('assistant', aiTurnTranscript.trim());
            aiTurnTranscript = "";
        }
        if (userTurnTranscript.trim()) {
            persistTranscript('user', userTurnTranscript.trim());
            userTurnTranscript = "";
        }
        debugLog(`🧠 Gemini Live Proxy: Client Verbindung geschlossen (${code})`);
        
        // Nur loggen, wenn der Client die Verbindung zuerst geschlossen hat und es kein regulärer User-Toggle war
        if (!wasAlreadyCleanedUp && cleanReason !== 'user_stopped_live_mode') {
            sendLogToBackend(
                code === 1000 || code === 1001 || code === 1005 || code === 1006 ? 'info' : 'warning',
                `Client-WebSocket-Verbindung geschlossen. Code: ${code}. Reason: ${cleanReason}`,
                'websocket:disconnect:client',
                buildSessionDiagnostics(code, reason ? reason.toString() : null, 'client')
            );
        }
        
        safeClose(googleWs, code, reason);
    });
    
    clientWs.on('error', (err) => {
        recordEvent('client_ws_error', err.message || String(err));
        cleanup();
        if (aiTurnTranscript.trim()) {
            persistTranscript('assistant', aiTurnTranscript.trim());
            aiTurnTranscript = "";
        }
        debugLog('🧠 Gemini Live Proxy: Client WebSocket Fehler', err);
        sendLogToBackend(
            'error',
            `Client-WebSocket-Fehler: ${err.message || err}`,
            'websocket:error:client',
            buildSessionDiagnostics(1011, err.message || String(err), 'client')
        );
        safeClose(googleWs, 1011, 'Client error');
    });
}

function handleTwilioConnection(ws) {
    console.log('📞 Neues Twilio Gespräch (WebSocket) verbunden.');
    
    let streamSid = null;
    let geminiWs = null;
    let geminiPingInterval = null;
    let callContext = {};
    let callTranscript = [];
    let currentAiTurn = "";
    let currentUserTurn = "";
    let callStartTime = null;
    let callLogged = false;
    let shouldEndCall = false;

    const saveCallLog = () => {
        if (callLogged) return;
        callLogged = true;
        console.log('⏹️ Call beendet. Sende Log an Backend...');
        
        if (currentUserTurn.trim()) {
            callTranscript.push(`Anrufer: ${currentUserTurn.trim()}`);
            currentUserTurn = "";
        }
        if (currentAiTurn.trim()) {
            callTranscript.push(`KI: ${currentAiTurn.trim()}`);
            currentAiTurn = "";
        }

        const duration = callStartTime ? Math.floor((Date.now() - callStartTime) / 1000) : 0;
        const payload = JSON.stringify({
            twilio_sid: streamSid,
            contact_name: callContext.contact_name,
            objective: callContext.objective,
            transcript: callTranscript,
            duration_seconds: duration,
            planned_call_id: callContext.planned_call_id
        });

        const backendUrl = process.env.APP_URL || 'http://localhost';
        fetch(`${backendUrl}/api/twilio/call-log`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payload
        }).catch(e => console.error("Error sending call log to backend:", e));
    };

    // 1. Initialisiere die Gemini Live API Verbindung
    const initGemini = () => {
        const HOST = "generativelanguage.googleapis.com";
        const WS_URL = `wss://${HOST}/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=${GOOGLE_API_KEY}`;
        
        geminiWs = new WebSocket(WS_URL, {
            headers: {
                'Referer': process.env.APP_URL || 'https://stage.mein-seelenfunke.de'
            },
            origin: process.env.APP_URL || 'https://stage.mein-seelenfunke.de'
        });

        geminiPingInterval = setInterval(() => {
            if (geminiWs && geminiWs.readyState === WebSocket.OPEN) {
                geminiWs.ping();
            }
        }, 20000);

        geminiWs.on('open', () => {
            console.log('🧠 Mit Google Gemini Live API verbunden.');
            debugLog('Gemini WebSocket connected!');
            
            // Sende den initialen System-Prompt (Kontext & Objective)
            const systemPrompt = `
Du bist die persönliche Telefon-Sekretärin von ${callContext.agent_name || 'Alina Steinhauer'}.
Dein Name ist Mira.
Deine Rolle: Persönliche Telefon-Sekretärin & rechte Hand von ${callContext.agent_name || 'Alina Steinhauer'}.
Dein Charakter: lieb, freundlich, ruhig, professionell, zielorientiert, zuverlässig.

🎯 Mission
Ich vertrete ${callContext.agent_name || 'Alina Steinhauer'} telefonisch nach außen mit höchster Professionalität, Klarheit und Freundlichkeit.
Ich sorge dafür, dass jede Anfrage effizient geklärt, jeder Termin sauber koordiniert und jedes Anliegen strukturiert weitergeleitet wird.
Ich bin die erste Stimme, die Menschen hören – und sorge dafür, dass sie sich sofort gut aufgehoben fühlen.

Du führst gerade ein ausgehendes Telefonat mit: ${callContext.contact_name || 'Unbekannt'}.
Dein explizites Ziel für dieses Telefonat: ${callContext.objective || 'Führe ein nettes, hilfreiches Gespräch.'}
Kontext zur angerufenen Person: ${callContext.system_instructions || ''}
Gelernte Fakten zur Person: ${callContext.ai_learned_facts || ''}

WICHTIG - KALENDER VON ${callContext.agent_name || 'ALINA STEINHAUER'}:
Falls du einen Termin vereinbaren sollst, richte dich nach diesem Kalender:
${callContext.calendar_events || 'Keine anstehenden Termine.'}

🧠 Kernaufgaben
- Klärung von Anliegen, Vorqualifizierung von Anrufern
- Entgegennahme und Strukturierung von Anfragen
- Informationsweitergabe im Namen von ${callContext.agent_name || 'Alina Steinhauer'}
- Freundliche, lösungsorientierte Gesprächsführung

🗣️ Gesprächsstil
- Warm, freundlich, ruhig. Klar strukturiert und zielorientiert.
- Niemals gestresst oder genervt. Immer lösungsorientiert.
- Spricht wertschätzend und professionell. Führt Gespräche aktiv und sicher.
- Antworte extrem kurz und bündig, genau wie in einem echten, schnellen Telefonat. Keine Formatierungen, keine Emojis.

🧭 Gesprächsführung (internes Verhalten)
- Zuhören -> Anliegen verstehen -> Ziel definieren -> Kurz zusammenfassen -> Nächsten Schritt verbindlich festlegen.
- Jemand ist unklar: Durch gezielte Fragen Klarheit schaffen.
- Jemand ist ungeduldig: Ruhig bleiben, Sicherheit ausstrahlen.
- Unwichtige Anfragen: Freundlich abfangen, ohne Zeit zu wissenschaftlich verschwenden.
- Lass dich nicht aus der Ruhe bringen! Wenn der Angerufene dich unterbricht, stoppe kurz, höre zu, aber halte danach konsequent an deinem Ziel fest und führe das Gespräch zurück zum Thema.

WICHTIG - GESPRÄCHSERÖFFNUNG (WARTEPFLICHT):
1. Du bist der Anrufer. Du DARFST NICHT als Erste sprechen!
2. Warte absolut still ab, bis der Angerufene (${callContext.contact_name || 'Unbekannt'}) sich am Telefon meldet (z.B. mit "Hallo", "Ja?" oder seinem Namen).
3. ACHTUNG: Ignoriere Hintergrundrauschen, Knacken oder leere Transkripte beim Gesprächsaufbau. Bleibe stumm, bis du ein klares, menschliches Wort hörst!
4. Sobald er sich klar gemeldet hat, antwortest du als allererstes klipp und klar mit: "Guten Tag, hier ist Mira aus dem Büro von ${callContext.agent_name || 'Alina Steinhauer'}. Ich rufe wegen eines Anliegens an."

🧾 Gesprächsabschluss
Wenn dein Ziel erfüllt ist, schließe ab mit z.B.: "Perfekt, ich habe alles notiert und kümmere mich darum. Vielen Dank für das Gespräch und einen schönen Tag für Sie."
Sobald du dich verabschiedet hast, MUSST du sofort das Tool 'end_call' aufrufen, um das Gespräch aufzulegen!
            `.trim();

            const setupMessage = {
                setup: {
                    model: 'models/gemini-3.1-flash-live-preview',
                    systemInstruction: {
                        parts: [{ text: systemPrompt }]
                    },
                    tools: [{
                        functionDeclarations: [{
                            name: "end_call",
                            description: "Beendet den Anruf. Rufe diese Funktion auf, wenn du alle Fragen gestellt hast, alle Antworten erhalten hast und dich verabschiedet hast, oder wenn der Kunde das Gespräch beenden möchte."
                        }]
                    }],
                    generationConfig: {
                        responseModalities: ["audio"],
                        speechConfig: {
                            voiceConfig: {
                                prebuiltVoiceConfig: {
                                    voiceName: "Aoede" // Weibliche, angenehme Stimme
                                }
                            }
                        }
                    },
                    inputAudioTranscription: {},
                    outputAudioTranscription: {}
                }
            };
            safeSend(geminiWs, JSON.stringify(setupMessage));
        });

        geminiWs.on('message', (data) => {
            const responseStr = data.toString();
            // debugLog("RAW GEMINI MSG: " + responseStr.substring(0, 200));
            const response = JSON.parse(responseStr);
            
            if (response.toolCall) {
                const functionCalls = response.toolCall.functionCalls;
                for (const call of functionCalls) {
                    if (call.name === 'end_call') {
                        console.log('☎️ KI möchte auflegen. Warte auf turnComplete...');
                        shouldEndCall = true; // wait for turnComplete
                        
                        const toolResponse = {
                            toolResponse: {
                                functionResponses: [{
                                    id: call.id,
                                    name: "end_call",
                                    response: { success: true }
                                }]
                            }
                        };
                        safeSend(geminiWs, JSON.stringify(toolResponse));
                    }
                }
            }

            if (response.serverContent?.outputTranscription?.text) {
                const aiDelta = response.serverContent.outputTranscription.text;
                debugLog('Gemini output transcription delta: ' + aiDelta);
                currentAiTurn += aiDelta;
            }

            if (response.serverContent?.inputTranscription?.text) {
                const userDelta = response.serverContent.inputTranscription.text;
                debugLog('User audio text delta: ' + userDelta);
                currentUserTurn += userDelta;
            }

            if (response.serverContent?.turnComplete) {
                if (currentUserTurn.trim()) {
                    callTranscript.push(`Anrufer: ${currentUserTurn.trim()}`);
                    currentUserTurn = "";
                }
                if (currentAiTurn.trim()) {
                    callTranscript.push(`KI: ${currentAiTurn.trim()}`);
                    currentAiTurn = "";
                }

                if (shouldEndCall) {
                    console.log('☎️ KI ist fertig mit Sprechen. Sende Mark-Event an Twilio.');
                    safeSend(ws, JSON.stringify({
                        event: "mark",
                        streamSid: streamSid,
                        mark: {
                            name: "end_of_call"
                        }
                    }));
                }
            }

            // Wenn Gemini Text zurückgibt (als Transcript), speichern wir ihn
            if (response.serverContent?.modelTurn?.parts) {
                const parts = response.serverContent.modelTurn.parts;
                for (const part of parts) {
                    if (part.text) {
                        debugLog('Gemini text response: ' + part.text);
                        currentAiTurn += part.text;
                    }
                    if (part.inlineData && part.inlineData.mimeType.startsWith('audio/pcm')) {
                        debugLog('Gemini sent audio chunk. Base64 length: ' + part.inlineData.data.length);
                        // Twilio erwartet 8kHz mulaw (Base64). 
                        // Gemini sendet i.d.R. 24kHz PCM.
                        // HIER: Eine Resampling-Logik mittels WaveFile, um 24kHz PCM zu 8kHz mulaw zu wandeln.
                        try {
                            const pcmBuffer = Buffer.from(part.inlineData.data, 'base64');
                            
                            // 1. Lese das Gemini PCM in WaveFile ein
                            // WICHTIG: WaveFile erwartet bei 16-bit ein Int16Array, kein Buffer(byte) Array,
                            // sonst wird jedes Byte als eigener 16-bit Sample interpretiert (Rauschen!)
                            const int16Data = new Int16Array(pcmBuffer.buffer, pcmBuffer.byteOffset, pcmBuffer.length / 2);
                            let wav = new WaveFile();
                            wav.fromScratch(1, 24000, '16', int16Data);
                            
                            // 2. Resample auf 8000Hz (für Telefon)
                            wav.toSampleRate(8000);
                            
                            // 3. Konvertiere zu mu-Law
                            wav.toMuLaw();
                            
                            // 4. Extrahiere die rohen mu-Law Daten (ohne WAV-Header)
                            const mulawData = wav.data.samples;
                            const payloadBase64 = Buffer.from(mulawData).toString('base64');

                            // 5. Sende an Twilio
                            const twilioMessage = {
                                event: 'media',
                                streamSid: streamSid,
                                media: { payload: payloadBase64 }
                            };
                            safeSend(ws, JSON.stringify(twilioMessage));
                            debugLog('Sent mu-law to Twilio. Payload length: ' + payloadBase64.length);
                            
                        } catch (e) {
                            console.error("Audio Encoding Error:", e);
                            debugLog("Audio Encoding Error", e);
                        }
                    }
                }
            } else if (response.serverContent?.interrupted) {
                console.log('🛑 User hat KI unterbrochen. Leere Twilio Audio-Puffer.');
                safeSend(ws, JSON.stringify({
                    event: 'clear',
                    streamSid: streamSid
                }));
                if (currentAiTurn.trim()) {
                    callTranscript.push(`KI: ${currentAiTurn.trim()} (unterbrochen)`);
                    currentAiTurn = "";
                }
            } else if (response.setupComplete) {
                debugLog("Gemini Setup erfolgreich bestätigt!");
                // KI wartet nun durch den System-Prompt nativ auf die Audio-Eingabe (Hallo) des Angerufenen.
            } else {
                debugLog("Gemini Response: " + JSON.stringify(response));
            }
        });

        geminiWs.on('close', (code, reason) => {
            if (geminiPingInterval) {
                clearInterval(geminiPingInterval);
                geminiPingInterval = null;
            }
            const cleanReason = reason ? reason.toString() : 'Keine';
            debugLog(`🧠 Gemini Verbindung getrennt (Twilio Call). Code: ${code}, Reason: ${cleanReason}`);
            if (code !== 1000 && code !== 1001 && code !== 1005 && code !== 1006) {
                sendLogToBackend(
                    'warning',
                    `Twilio-Gemini Live API Verbindung geschlossen. Code: ${code}. Reason: ${cleanReason}`,
                    'websocket:disconnect:twilio_gemini',
                    {
                        code,
                        reason: cleanReason,
                        source: 'twilio_gemini',
                        diagnosis: diagnoseWsIssue(code, cleanReason, 'google'),
                        call_context: {
                            stream_sid: streamSid,
                            contact_name: callContext.contact_name || null,
                            objective: callContext.objective || null,
                            planned_call_id: callContext.planned_call_id || null,
                            duration_sec: callStartTime ? Math.floor((Date.now() - callStartTime) / 1000) : 0
                        }
                    }
                );
            }
        });
        geminiWs.on('error', (err) => {
            if (geminiPingInterval) {
                clearInterval(geminiPingInterval);
                geminiPingInterval = null;
            }
            debugLog('Gemini WS Error: ' + err.toString());
            sendLogToBackend(
                'error',
                `Twilio-Gemini WebSocket-Fehler: ${err.message || err}`,
                'websocket:error:twilio_gemini',
                {
                    code: 1011,
                    reason: err.message || String(err),
                    source: 'twilio_gemini',
                    diagnosis: diagnoseWsIssue(1011, err.message || String(err), 'google')
                }
            );
        });
    };

    // 2. Höre auf Nachrichten von Twilio
    ws.on('message', (message) => {
        const msg = JSON.parse(message);

        if (msg.event === 'start') {
            debugLog('Twilio stream started. StreamSid: ' + msg.start.streamSid);
            streamSid = msg.start.streamSid;
            // Extrahiere unsere Custom Parameters aus dem Twilio Webhook
            callContext = msg.start.customParameters || {};
            callStartTime = Date.now();
            console.log(`🎬 Call Start! Ziel: ${callContext.objective}`);
            
            // Jetzt wo wir den Kontext haben, starten wir die Gemini Verbindung
            initGemini();
            
        } else if (msg.event === 'media') {
            // Twilio sendet uns Audio vom Anrufer (mulaw 8kHz base64)
            if (geminiWs && geminiWs.readyState === WebSocket.OPEN) {
                try {
                    const payloadLength = msg.media.payload.length;
                    
                    const twilioMulawBuffer = Buffer.from(msg.media.payload, 'base64');
                    
                    // Wandle 8kHz mulaw zu 16kHz PCM (Gemini Live API erfordert 16kHz)
                    let wav = new WaveFile();
                    wav.fromScratch(1, 8000, '8m', twilioMulawBuffer);
                    wav.fromMuLaw(); // Entpacke mu-Law zu 16-bit PCM (8kHz PCM)
                    wav.toSampleRate(16000); // WICHTIG: Upsampling für Gemini API
                    
                    const pcm16Data = wav.data.samples;
                    const pcmBase64 = Buffer.from(pcm16Data.buffer).toString('base64');

                    const audioMessage = {
                        realtimeInput: {
                            audio: {
                                mimeType: "audio/pcm;rate=16000",
                                data: pcmBase64
                            }
                        }
                    };
                    safeSend(geminiWs, JSON.stringify(audioMessage));
                    debugLog(`Processed incoming audio. Twilio Payload: ${payloadLength} -> Sent to Gemini PCM 16kHz: ${pcmBase64.length}`);
                } catch (e) {
                    // Audio conversion error
                    debugLog("Audio input conversion error", e);
                }
            } else {
                debugLog(`Ignored media event. Gemini readyState is ${geminiWs ? geminiWs.readyState : 'null'}`);
            }
        } else if (msg.event === 'mark' && msg.mark.name === 'end_of_call') {
            console.log('🏁 Twilio hat das Mark-Event erreicht. Schließe Verbindung.');
            ws.close();
        } else if (msg.event === 'stop') {
            if (geminiPingInterval) {
                clearInterval(geminiPingInterval);
                geminiPingInterval = null;
            }
            if (geminiWs) geminiWs.close();
            saveCallLog();
        }
    });

    ws.on('close', () => {
        console.log('Twilio WebSocket getrennt.');
        if (geminiPingInterval) {
            clearInterval(geminiPingInterval);
            geminiPingInterval = null;
        }
        if (geminiWs && geminiWs.readyState === WebSocket.OPEN) {
            geminiWs.close();
        }
        // Falls der Anruf plötzlich abbrach und kein 'stop' Event gesendet wurde:
        if (typeof saveCallLog === 'function') {
            saveCallLog();
        }
    });
}

wss.on('connection', (ws, req) => {
    let urlString = req.url || '/';
    // Fallback if URL is relative or lacks host
    const parsedUrl = new URL(urlString, 'http://localhost');
    const pathname = parsedUrl.pathname;
    const reqMeta = {
        client_ip: (req.headers['x-forwarded-for'] || req.socket?.remoteAddress || 'unknown').toString().split(',')[0].trim(),
        user_agent: req.headers['user-agent'] || 'unknown',
        origin: req.headers['origin'] || null,
        path: pathname
    };
    
    debugLog(`🔌 Neue WebSocket-Verbindung auf Pfad: ${pathname} (Client: ${reqMeta.client_ip}, UA: ${reqMeta.user_agent})`);
    
    if (pathname === '/gemini-live') {
        const token = parsedUrl.searchParams.get('token');
        if (!token) {
            debugLog('❌ Verbindung abgelehnt: Kein Token übergeben.');
            sendLogToBackend(
                'warning',
                'WebSocket-Verbindung abgelehnt: Kein Token übergeben (Code 4001).',
                'websocket:auth:token_missing',
                {
                    code: 4001,
                    reason: 'Token missing',
                    source: 'bridge_auth',
                    diagnosis: diagnoseWsIssue(4001, 'Token missing', 'bridge'),
                    client_meta: reqMeta
                }
            );
            ws.close(4001, 'Token missing');
            return;
        }
        
        // Frühzeitiges Buffern der Client-Nachrichten, um Race-Conditions während der Token-Verifizierung zu verhindern
        const messageQueue = [];
        const handleEarlyMessage = (data) => {
            messageQueue.push(data);
        };
        ws.on('message', handleEarlyMessage);
        
        const backendUrl = process.env.APP_URL || 'http://localhost';
        debugLog(`🔑 Verifiziere Token gegen Backend: ${backendUrl}/api/ai/verify-token`);
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => {
            debugLog(`⏱️ Fetch-Timeout erreicht für Token-Verifizierung (${backendUrl})`);
            controller.abort();
        }, 5000); // 5 seconds timeout
 
        fetch(`${backendUrl}/api/ai/verify-token`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ token: token }),
            signal: controller.signal
        })
        .then(async (res) => {
            clearTimeout(timeoutId);
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Status ${res.status}: ${text.substring(0, 100)}`);
            }
            return res.json();
        })
        .then((data) => {
            if (!data.api_key) {
                throw new Error('No API key returned from verification');
            }
            
            // Temporären Listener entfernen
            ws.off('message', handleEarlyMessage);
 
            // Proxy initialisieren (registriert neue 'message' Listener)
            initGeminiLiveProxy(ws, data, reqMeta);
 
            // Gespeicherte Nachrichten erneut abspielen
            if (messageQueue.length > 0) {
                debugLog(`📦 Replay von ${messageQueue.length} zwischengespeicherten Client-Nachrichten...`);
                for (const msg of messageQueue) {
                    ws.emit('message', msg);
                }
            }
        })
        .catch((err) => {
            clearTimeout(timeoutId);
            ws.off('message', handleEarlyMessage);
            let errMsg = err.message;
            if (err.name === 'AbortError') {
                errMsg = `Timeout connecting to ${backendUrl}`;
            }
            debugLog('❌ Token-Verifizierung fehlgeschlagen: ' + errMsg);
            const reason = `Verify failed: ${errMsg}`.substring(0, 120);
            sendLogToBackend(
                'warning',
                `WebSocket Token-Verifizierung fehlgeschlagen (Code 4003): ${errMsg}`,
                'websocket:auth:verify_failed',
                {
                    code: 4003,
                    reason,
                    source: 'bridge_auth',
                    backend_url: backendUrl,
                    diagnosis: diagnoseWsIssue(4003, reason, 'bridge'),
                    client_meta: reqMeta
                }
            );
            ws.close(4003, reason);
        });
    } else {
        handleTwilioConnection(ws);
    }
});
 
debugLog(`Versuche auf Port ${PORT} zu lauschen... (process.env.PORT=${process.env.PORT})`);
server.listen(PORT, '0.0.0.0', () => {
    debugLog(`🚀 Seelenfunke Twilio-Gemini Bridge lauscht auf Port ${PORT}`);
    debugLog(`Erfolgreich gebunden auf Port ${PORT}`);
});
