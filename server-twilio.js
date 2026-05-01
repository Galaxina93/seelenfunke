import { WebSocketServer } from 'ws';
import http from 'http';
import dotenv from 'dotenv';
import WebSocket from 'ws';
import wavefile from 'wavefile';
const { WaveFile } = wavefile; 

import fs from 'fs';

process.on('uncaughtException', (err) => {
    fs.writeFileSync('crash.log', err.stack || err.toString());
    process.exit(1);
});
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

function debugLog(msg) {
    try {
        fs.appendFileSync(join(__dirname, 'audio-debug.log'), new Date().toISOString() + ' - ' + msg + '\n');
    } catch(e) {}
}

debugLog("--- NODE JS SERVER RESTARTED (VERSION: AUDIO FIX V3) ---");

dotenv.config();

const PORT = process.env.PORT || process.env.TWILIO_WS_PORT || 8081;
const GOOGLE_API_KEY = process.env.GEMINI_API_KEY || process.env.GOOGLE_API_KEY;

if (!GOOGLE_API_KEY) {
    console.error("❌ GEMINI_API_KEY is missing in .env!");
    process.exit(1);
}

const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('Seelenfunke Twilio-Gemini Audio Bridge is running.\n');
});

const wss = new WebSocketServer({ server });

wss.on('connection', (ws) => {
    console.log('📞 Neues Twilio Gespräch (WebSocket) verbunden.');
    
    let streamSid = null;
    let geminiWs = null;
    let callContext = {};
    let callTranscript = [];
    let callStartTime = null;
    let callLogged = false;

    const saveCallLog = () => {
        if (callLogged) return;
        callLogged = true;
        console.log('⏹️ Call beendet. Sende Log an Backend...');
        
        const duration = callStartTime ? Math.floor((Date.now() - callStartTime) / 1000) : 0;
        const payload = JSON.stringify({
            twilio_sid: streamSid,
            contact_name: callContext.contact_name,
            objective: callContext.objective,
            transcript: callTranscript,
            duration_seconds: duration
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
        const WS_URL = `wss://${HOST}/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContent?key=${GOOGLE_API_KEY}`;
        
        geminiWs = new WebSocket(WS_URL);

        geminiWs.on('open', () => {
            console.log('🧠 Mit Google Gemini Live API verbunden.');
            debugLog('Gemini WebSocket connected!');
            
            // Sende den initialen System-Prompt (Kontext & Objective)
            const systemPrompt = `
Du bist ein KI-Sprachagent für die Seelenfunke Plattform.
Dein Name ist ${callContext.agent_name || 'Alina Steinhauer'}.
Deine Rolle/Profil: ${callContext.agent_profile || 'Du bist eine freundliche und professionelle Assistentin.'}

Du führst gerade ein Telefonat mit: ${callContext.contact_name || 'Unbekannt'}.
Dein explizites Ziel für dieses Telefonat: ${callContext.objective || 'Führe ein nettes, hilfreiches Gespräch.'}
Kontext zur angerufenen Person: ${callContext.system_instructions || ''}
Gelernte Fakten zur Person: ${callContext.ai_learned_facts || ''}

WICHTIG - KALENDER VON ALINA STEINHAUER:
Falls du einen Termin für Alina vereinbaren sollst, richte dich strikt nach ihrem folgenden Kalender:
${callContext.calendar_events || 'Keine anstehenden Termine.'}
Achte darauf, dass Termine sich nicht überschneiden!

Regeln:
- Antworte extrem kurz und bündig, genau wie in einem echten, menschlichen Telefonat.
- Keine Formatierungen, keine Emojis, sprich natürlich.
- Höre dem Kunden kurz zu, aber unterbinde energisch jeden Smalltalk oder Zeitverschwendung. 
- Das Telefonat kostet sekündlich Geld. Dein Ziel ist absolute Effizienz.
- Wenn die Person vom Thema abschweift, weise sie darauf hin, dass du nur für das spezifische Ziel angerufen hast und keine Zeit für andere Themen hast.
- Sobald dein Ziel erreicht ist, verabschiede dich freundlich und rufe das Tool 'end_call' auf, um aufzulegen.
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
                    }
                }
            };
            geminiWs.send(JSON.stringify(setupMessage));
        });

        geminiWs.on('message', (data) => {
            const responseStr = data.toString();
            // debugLog("RAW GEMINI MSG: " + responseStr.substring(0, 200));
            const response = JSON.parse(responseStr);
            
            if (response.toolCall) {
                const functionCalls = response.toolCall.functionCalls;
                for (const call of functionCalls) {
                    if (call.name === 'end_call') {
                        console.log('☎️ KI hat aufgelegt (end_call). Schließe Twilio Websocket.');
                        if (ws.readyState === WebSocket.OPEN) {
                            ws.close();
                        }
                    }
                }
            }

            if (response.serverContent?.inputTranscription?.text) {
                const userText = response.serverContent.inputTranscription.text;
                debugLog('User audio text: ' + userText);
                callTranscript.push(`Anrufer: ${userText}`);
            }

            // Wenn Gemini Text zurückgibt (als Transcript), speichern wir ihn
            if (response.serverContent?.modelTurn?.parts) {
                const parts = response.serverContent.modelTurn.parts;
                for (const part of parts) {
                    if (part.text) {
                        debugLog('Gemini text response: ' + part.text);
                        callTranscript.push(`KI: ${part.text}`);
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
                            ws.send(JSON.stringify(twilioMessage));
                            debugLog('Sent mu-law to Twilio. Payload length: ' + payloadBase64.length);
                            
                        } catch (e) {
                            console.error("Audio Encoding Error:", e);
                            debugLog("Audio Encoding Error: " + e.toString());
                        }
                    }
                }
            } else if (response.serverContent?.interrupted) {
                console.log('🛑 User hat KI unterbrochen. Leere Twilio Audio-Puffer.');
                ws.send(JSON.stringify({
                    event: 'clear',
                    streamSid: streamSid
                }));
            } else if (response.setupComplete) {
                debugLog("Gemini Setup erfolgreich bestätigt!");
                // Zwinge Gemini sofort etwas zu sagen, ohne auf den Anrufer zu warten!
                // Durch clientContent + turnComplete: true antwortet die AI sofort proaktiv.
                const initialPrompt = {
                    clientContent: {
                        turns: [{
                            role: "user",
                            parts: [{ text: "Die Verbindung wurde hergestellt. Bitte eröffne das Gespräch sofort mit einem Satz wie: 'Hallo, hier ist der KI-Agent von " + (callContext.agent_name || "Alina Steinhauer") + ". Ich rufe wegen eines Anliegens an.'" }]
                        }],
                        turnComplete: true
                    }
                };
                geminiWs.send(JSON.stringify(initialPrompt));
                debugLog("Initialer Sprach-Prompt an Gemini gesendet.");
            } else {
                debugLog("Gemini Response: " + JSON.stringify(response));
            }
        });

        geminiWs.on('close', (code, reason) => {
            debugLog(`🧠 Gemini Verbindung getrennt. Code: ${code}, Reason: ${reason.toString()}`);
        });
        geminiWs.on('error', (err) => {
            debugLog('Gemini WS Error: ' + err.toString());
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
                            mediaChunks: [{
                                mimeType: "audio/pcm;rate=16000",
                                data: pcmBase64
                            }]
                        }
                    };
                    geminiWs.send(JSON.stringify(audioMessage));
                    debugLog(`Processed incoming audio. Twilio Payload: ${payloadLength} -> Sent to Gemini PCM 16kHz: ${pcmBase64.length}`);
                } catch (e) {
                    // Audio conversion error
                    debugLog("Audio input conversion error: " + e.toString());
                }
            } else {
                debugLog(`Ignored media event. Gemini readyState is ${geminiWs ? geminiWs.readyState : 'null'}`);
            }
        } else if (msg.event === 'stop') {
            if (geminiWs) geminiWs.close();
            saveCallLog();
        }
    });

    ws.on('close', () => {
        console.log('Twilio WebSocket getrennt.');
        if (geminiWs && geminiWs.readyState === WebSocket.OPEN) {
            geminiWs.close();
        }
        // Falls der Anruf plötzlich abbrach und kein 'stop' Event gesendet wurde:
        if (typeof saveCallLog === 'function') {
            saveCallLog();
        }
    });
});

debugLog(`Versuche auf Port ${PORT} zu lauschen... (process.env.PORT=${process.env.PORT})`);
server.listen(PORT, '0.0.0.0', () => {
    console.log(`🚀 Seelenfunke Twilio-Gemini Bridge lauscht auf Port ${PORT}`);
    debugLog(`Erfolgreich gebunden auf Port ${PORT}`);
});
