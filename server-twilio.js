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
    let shouldEndCall = false;

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
- Unwichtige Anfragen: Freundlich abfangen, ohne Zeit zu verschwenden.
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
                        geminiWs.send(JSON.stringify(toolResponse));
                    }
                }
            }

            if (response.serverContent?.turnComplete) {
                if (shouldEndCall && ws.readyState === WebSocket.OPEN) {
                    console.log('☎️ KI ist fertig mit Sprechen. Sende Mark-Event an Twilio.');
                    ws.send(JSON.stringify({
                        event: "mark",
                        streamSid: streamSid,
                        mark: {
                            name: "end_of_call"
                        }
                    }));
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
                // KI wartet nun durch den System-Prompt nativ auf die Audio-Eingabe (Hallo) des Angerufenen.
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
                            audio: {
                                mimeType: "audio/pcm;rate=16000",
                                data: pcmBase64
                            }
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
        } else if (msg.event === 'mark' && msg.mark.name === 'end_of_call') {
            console.log('🏁 Twilio hat das Mark-Event erreicht. Schließe Verbindung.');
            ws.close();
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
