package de.meinseelenfunke.app.services

import android.content.Context
import com.google.gson.Gson
import com.google.gson.JsonArray
import com.google.gson.JsonObject
import de.meinseelenfunke.app.data.api.CallDiscrepancyDto
import de.meinseelenfunke.app.data.api.CallFactCheckDto
import de.meinseelenfunke.app.data.api.CallVerificationReportDto
import de.meinseelenfunke.app.util.PhoneConciergeManager
import de.meinseelenfunke.app.util.VertexClientTokenCache
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.time.Instant
import java.time.ZoneId
import java.time.ZonedDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale
import java.util.concurrent.TimeUnit

data class CallVerificationInput(
    val callerName: String,
    val greetingName: String,
    val transcriptLines: List<String>,
    val understoodSummary: String,
    val understoodRahmenbedingungen: List<String>,
    val understoodActionRequired: String,
    val understoodUrgency: String,
    val understoodCallbackRequested: Boolean
)

/**
 * KI-gestützte Gegenprüfung des Verstandenen (Notiz, Rahmenbedingungen, Aufgabe)
 * gegen das tatsächlich Gesagte (Wort-für-Wort-Transkript), um Hör- und Zusammenfassungsfehler
 * sofort zu erkennen, zu korrigieren oder transparent zu markieren.
 */
object CallNoteVerificationEngine {

    const val STATUS_VERIFIED = "verified"
    const val STATUS_CORRECTED = "corrected"
    const val STATUS_UNCERTAIN = "uncertain"
    const val STATUS_PENDING = "pending"
    const val STATUS_USER_CONFIRMED = "user_confirmed"

    private val gson = Gson()
    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    private val httpClient: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .connectTimeout(3, TimeUnit.SECONDS)
            .readTimeout(6, TimeUnit.SECONDS)
            .writeTimeout(4, TimeUnit.SECONDS)
            .build()
    }

    private val timeColonRegex = Regex("""\b([01]?\d|2[0-3])\s*[:.]\s*([0-5]\d)\b""")
    private val timeUmRegex = Regex("""\bum\s+([01]?\d|2[0-4])(?:\s*uhr)?\b""", RegexOption.IGNORE_CASE)
    private val dateDotRegex = Regex("""\b([0-3]?\d)\.([01]?\d)\.(?:(\d{2,4}))?\b""")
    private val longNumberRegex = Regex("""\b\d{4,}\b""")

    private val weekdayWords = listOf(
        "montag", "dienstag", "mittwoch", "donnerstag", "freitag", "samstag", "sonntag",
        "heute", "morgen", "übermorgen"
    )

    private val noCallbackPatterns = listOf(
        "nicht zurückrufen",
        "kein rückruf",
        "keinen rückruf",
        "brauchst nicht anrufen",
        "musst nicht zurückrufen",
        "nur zur info",
        "nur bescheid sagen",
        "reine info"
    )

    fun sanitizeCleanText(raw: String?): String {
        return raw.orEmpty()
            .replace(" \u2013 ", ", ")
            .replace("\u2013", "-")
            .replace(Regex("\\s+"), " ")
            .trim()
    }

    fun extractCallerLines(transcriptLines: List<String>): List<String> {
        return transcriptLines
            .map { it.trim() }
            .filter { it.startsWith("Anrufer:", ignoreCase = true) }
            .map { it.substringAfter(':').trim() }
            .filter { it.isNotBlank() }
    }

    /**
     * Schnelle, offline-fähige deterministische Plausibilitäts- & Fakten-Gegenprüfung (< 2 ms).
     */
    fun runDeterministicCrossCheck(input: CallVerificationInput): Pair<List<CallDiscrepancyDto>, List<CallFactCheckDto>> {
        val discrepancies = mutableListOf<CallDiscrepancyDto>()
        val factChecks = mutableListOf<CallFactCheckDto>()

        val callerLines = extractCallerLines(input.transcriptLines)
        val callerText = callerLines.joinToString(" ").lowercase(Locale.GERMAN)
        val fullTranscriptText = input.transcriptLines.joinToString(" ").lowercase(Locale.GERMAN)
        val understoodCombined = buildString {
            append(input.understoodSummary).append(' ')
            append(input.understoodRahmenbedingungen.joinToString(" ")).append(' ')
            append(input.understoodActionRequired)
        }.lowercase(Locale.GERMAN)

        if (callerLines.isEmpty()) {
            discrepancies.add(
                CallDiscrepancyDto(
                    category = "Transkript",
                    understood = sanitizeCleanText(input.understoodSummary).ifBlank { "Anrufnotiz" },
                    spoken = "Kein gesprochener Anrufer-Text erfasst.",
                    severity = "warning",
                    resolved_by_correction = false
                )
            )
            return discrepancies to factChecks
        }

        // 1. Uhrzeiten-Abgleich (HH:MM in Verstandenes vs. Gesagtes)
        val understoodTimes = timeColonRegex.findAll(understoodCombined).map { m ->
            val h = m.groupValues[1].toIntOrNull() ?: -1
            val min = m.groupValues[2].toIntOrNull() ?: 0
            String.format(Locale.GERMAN, "%d:%02d", h, min) to h
        }.distinctBy { it.first }.toList()

        val spokenHours = buildSet {
            timeColonRegex.findAll(fullTranscriptText).forEach { m ->
                m.groupValues[1].toIntOrNull()?.let { add(it) }
            }
            timeUmRegex.findAll(fullTranscriptText).forEach { m ->
                m.groupValues[1].toIntOrNull()?.let { add(it) }
            }
        }

        for ((timeLabel, hour) in understoodTimes) {
            val spoken12hVariant = if (hour > 12) hour - 12 else hour + 12
            val matchedHour = hour in spokenHours || spoken12hVariant in spokenHours
            if (matchedHour) {
                val evidence = findEvidenceSnippet(callerLines, listOf(timeLabel, "$hour uhr", "um $hour", "$spoken12hVariant uhr"))
                if (evidence != null) {
                    factChecks.add(CallFactCheckDto(fact = "Uhrzeit $timeLabel Uhr", evidence_quote = evidence, matched = true))
                }
            } else if (spokenHours.isNotEmpty()) {
                val spokenLabel = spokenHours.joinToString(", ") { "${it} Uhr" }
                discrepancies.add(
                    CallDiscrepancyDto(
                        category = "Uhrzeit",
                        understood = "$timeLabel Uhr",
                        spoken = spokenLabel,
                        severity = "critical",
                        resolved_by_correction = false
                    )
                )
            }
        }

        // 2. Datums- & Wochentags-Abgleich
        val understoodDays = weekdayWords.filter { understoodCombined.contains(it) }
        for (day in understoodDays) {
            if (fullTranscriptText.contains(day)) {
                val evidence = findEvidenceSnippet(callerLines, listOf(day))
                if (evidence != null) {
                    factChecks.add(CallFactCheckDto(fact = "Tag: ${day.replaceFirstChar { it.uppercase() }}", evidence_quote = evidence, matched = true))
                }
            }
        }

        val understoodDates = dateDotRegex.findAll(understoodCombined).map { "${it.groupValues[1].toIntOrNull()}.${it.groupValues[2].toIntOrNull()}." }.distinct().toList()
        val spokenDates = dateDotRegex.findAll(fullTranscriptText).map { "${it.groupValues[1].toIntOrNull()}.${it.groupValues[2].toIntOrNull()}." }.toSet()
        for (d in understoodDates) {
            if (spokenDates.isNotEmpty() && d !in spokenDates) {
                discrepancies.add(
                    CallDiscrepancyDto(
                        category = "Datum",
                        understood = d,
                        spoken = spokenDates.joinToString(", "),
                        severity = "critical",
                        resolved_by_correction = false
                    )
                )
            }
        }

        // 3. Längere Zahlen / Nummern (4+ Ziffern) abgleichen
        val understoodNumbers = longNumberRegex.findAll(understoodCombined).map { it.value }.distinct().toList()
        val spokenNumbers = longNumberRegex.findAll(fullTranscriptText).map { it.value }.toSet()
        for (num in understoodNumbers) {
            if (num !in spokenNumbers) {
                discrepancies.add(
                    CallDiscrepancyDto(
                        category = "Zahl/Nummer",
                        understood = num,
                        spoken = if (spokenNumbers.isEmpty()) "Nicht im Gespräch genannt" else spokenNumbers.joinToString(", "),
                        severity = "critical",
                        resolved_by_correction = false
                    )
                )
            } else {
                val evidence = findEvidenceSnippet(callerLines, listOf(num))
                if (evidence != null) {
                    factChecks.add(CallFactCheckDto(fact = "Nummer $num", evidence_quote = evidence, matched = true))
                }
            }
        }

        // 4. Rückruf-Widerspruch prüfen
        val callerDeclinedCallback = noCallbackPatterns.any { callerText.contains(it) }
        if (callerDeclinedCallback && input.understoodCallbackRequested) {
            val evidence = findEvidenceSnippet(callerLines, noCallbackPatterns) ?: callerLines.lastOrNull().orEmpty()
            discrepancies.add(
                CallDiscrepancyDto(
                    category = "Rückruf",
                    understood = "Rückruf erbeten",
                    spoken = evidence,
                    severity = "warning",
                    resolved_by_correction = false
                )
            )
        }

        return discrepancies to factChecks
    }

    private fun findEvidenceSnippet(lines: List<String>, needles: List<String>): String? {
        for (line in lines) {
            val low = line.lowercase(Locale.GERMAN)
            if (needles.any { low.contains(it.lowercase(Locale.GERMAN)) }) {
                return sanitizeCleanText(line).take(140)
            }
        }
        return null
    }

    fun buildVerificationPrompt(input: CallVerificationInput): String {
        val nowBerlin = ZonedDateTime.now(ZoneId.of("Europe/Berlin"))
        val nowLabel = nowBerlin.format(DateTimeFormatter.ofPattern("EEEE, dd.MM.yyyy HH:mm", Locale.GERMAN))
        val transcriptText = input.transcriptLines.joinToString("\n").take(10_000)
        val rahmenText = if (input.understoodRahmenbedingungen.isEmpty()) {
            "- (keine)"
        } else {
            input.understoodRahmenbedingungen.joinToString("\n") { "- $it" }
        }

        return """
Du bist ein präziser Qualitäts- und Faktenprüfer für Alinas Telefon-Assistentin Funkira.
Aktuelle Zeit: $nowLabel Uhr (Europe/Berlin).
Anrufer: ${input.callerName} (Anrede: ${input.greetingName}).

Vergleiche STRICT das VERSTANDENE (die vorläufige Notiz) gegen das GESAGTE (Wort-für-Wort-Transkript).
Prüfe insbesondere: Datum, Wochentag, Uhrzeit, Ort, Namen, Zahlen/Beträge, konkrete Aufgaben und Rückrufwunsch.
Erfinde NIEMALS Informationen, die nicht im Transkript stehen. Verwende NIEMALS Gedankenstriche (" – ").

--- VERSTANDENES (VORLÄUFIGE NOTIZ) ---
Zusammenfassung: ${input.understoodSummary}
Rahmenbedingungen:
$rahmenText
Aufgabe für Alina: ${input.understoodActionRequired}
Dringlichkeit: ${input.understoodUrgency}
Rückruf gewünscht: ${input.understoodCallbackRequested}

--- GESAGTES (TRANSKRIPT) ---
$transcriptText

Antworte ausschließlich als valides JSON-Objekt ohne Markdown-Backticks mit exakt diesem Schema:
{
  "status": "verified|corrected|uncertain",
  "confidence_score": 0,
  "verification_summary": "1 kurzer deutscher Satz zum Prüfergebnis",
  "verified_facts": [
    {"fact": "Belegter Fakt", "evidence_quote": "Kurzes Originalzitat aus dem Transkript", "matched": true}
  ],
  "discrepancies": [
    {"category": "Uhrzeit|Datum|Name|Aufgabe|Rückruf|Zahl/Nummer|Sonstiges", "understood": "Was falsch/fehlend war", "spoken": "Was tatsächlich gesagt wurde", "severity": "warning|critical", "resolved_by_correction": true}
  ],
  "corrected_summary": "Präzise, 100% wahrheitsgetreue deutsche Zusammenfassung (1-2 Sätze)",
  "corrected_rahmenbedingungen": ["Nur belegte Eckdaten als kurze Stichpunkte"],
  "corrected_action_required": "Klare Handlungsanweisung für Alina (oder 'Zur Kenntnis nehmen.')",
  "corrected_urgency": "hoch|mittel|niedrig",
  "corrected_callback_requested": true
}
Regeln für status:
- "verified": Alles Wesentliche stimmt 1:1 mit dem Gesagten überein.
- "corrected": Du hast einen Fehler, eine Auslassung oder Ungenauigkeit gefunden und im corrected_*-Teil bereinigt.
- "uncertain": Das Gesagte ist akustisch abgebrochen, widersprüchlich oder zu unklar, sodass Alina selbst prüfen sollte.
""".trim()
    }

    fun parseVerificationJson(
        rawText: String,
        input: CallVerificationInput,
        deterministicDiscrepancies: List<CallDiscrepancyDto>,
        deterministicFacts: List<CallFactCheckDto>
    ): CallVerificationReportDto {
        val cleanedRaw = rawText
            .replace("```json", "")
            .replace("```", "")
            .trim()

        val jsonStart = cleanedRaw.indexOf('{')
        val jsonEnd = cleanedRaw.lastIndexOf('}')
        val jsonSlice = if (jsonStart >= 0 && jsonEnd > jsonStart) {
            cleanedRaw.substring(jsonStart, jsonEnd + 1)
        } else {
            cleanedRaw
        }

        val obj = try {
            gson.fromJson(jsonSlice, JsonObject::class.java)
        } catch (_: Exception) {
            null
        } ?: return buildOfflineReport(input, deterministicDiscrepancies, deterministicFacts, isOfflinePending = false)

        val aiDiscrepancies = mutableListOf<CallDiscrepancyDto>()
        if (obj.has("discrepancies") && obj.get("discrepancies").isJsonArray) {
            obj.getAsJsonArray("discrepancies").forEach { el ->
                if (el.isJsonObject) {
                    val d = el.asJsonObject
                    val cat = sanitizeCleanText(d.get("category")?.asString).ifBlank { "Prüfpunkt" }
                    val und = sanitizeCleanText(d.get("understood")?.asString)
                    val spk = sanitizeCleanText(d.get("spoken")?.asString)
                    val sev = if (d.get("severity")?.asString.equals("critical", ignoreCase = true)) "critical" else "warning"
                    val resolved = try { d.get("resolved_by_correction")?.asBoolean ?: true } catch (_: Exception) { true }
                    if (und.isNotBlank() || spk.isNotBlank()) {
                        aiDiscrepancies.add(
                            CallDiscrepancyDto(
                                category = cat,
                                understood = und,
                                spoken = spk,
                                severity = sev,
                                resolved_by_correction = resolved
                            )
                        )
                    }
                }
            }
        }

        val aiFacts = mutableListOf<CallFactCheckDto>()
        if (obj.has("verified_facts") && obj.get("verified_facts").isJsonArray) {
            obj.getAsJsonArray("verified_facts").forEach { el ->
                if (el.isJsonObject) {
                    val f = el.asJsonObject
                    val fact = sanitizeCleanText(f.get("fact")?.asString)
                    val quote = sanitizeCleanText(f.get("evidence_quote")?.asString)
                    val matched = try { f.get("matched")?.asBoolean ?: true } catch (_: Exception) { true }
                    if (fact.isNotBlank()) {
                        aiFacts.add(CallFactCheckDto(fact = fact, evidence_quote = quote, matched = matched))
                    }
                }
            }
        }
        val mergedFacts = (aiFacts + deterministicFacts)
            .distinctBy { it.fact.lowercase(Locale.GERMAN) }
            .take(8)

        val correctedSummary = sanitizeCleanText(obj.get("corrected_summary")?.asString)
            .ifBlank { sanitizeCleanText(input.understoodSummary) }

        val correctedRahmen = if (obj.has("corrected_rahmenbedingungen") && obj.get("corrected_rahmenbedingungen").isJsonArray) {
            obj.getAsJsonArray("corrected_rahmenbedingungen")
                .mapNotNull { el -> sanitizeCleanText(el.asString).takeIf { it.isNotBlank() } }
                .distinct()
        } else {
            input.understoodRahmenbedingungen.map { sanitizeCleanText(it) }.filter { it.isNotBlank() }
        }

        val correctedAction = sanitizeCleanText(obj.get("corrected_action_required")?.asString)
            .ifBlank { sanitizeCleanText(input.understoodActionRequired) }

        val correctedUrgency = PhoneConciergeManager.normalizeUrgency(
            obj.get("corrected_urgency")?.asString ?: input.understoodUrgency
        )
        val correctedCallback = if (obj.has("corrected_callback_requested")) {
            try { obj.get("corrected_callback_requested").asBoolean } catch (_: Exception) { input.understoodCallbackRequested }
        } else {
            input.understoodCallbackRequested
        }

        // Merge any deterministic critical issues not yet explained
        val mergedDiscrepancies = (aiDiscrepancies + deterministicDiscrepancies.filter { det ->
            aiDiscrepancies.none { it.category.equals(det.category, ignoreCase = true) }
        }).take(6)

        val rawStatus = obj.get("status")?.asString?.trim()?.lowercase(Locale.ROOT).orEmpty()
        val hasUnresolvedCritical = mergedDiscrepancies.any { !it.resolved_by_correction && it.severity == "critical" }
        val status = when {
            rawStatus == STATUS_UNCERTAIN || hasUnresolvedCritical -> STATUS_UNCERTAIN
            rawStatus == STATUS_CORRECTED || mergedDiscrepancies.isNotEmpty() -> STATUS_CORRECTED
            rawStatus == STATUS_VERIFIED -> STATUS_VERIFIED
            else -> STATUS_VERIFIED
        }

        val rawScore = try { obj.get("confidence_score")?.asInt ?: -1 } catch (_: Exception) { -1 }
        val score = if (rawScore in 0..100) {
            rawScore
        } else {
            when (status) {
                STATUS_VERIFIED -> 96
                STATUS_CORRECTED -> 88
                else -> 65
            }
        }

        val summaryLine = sanitizeCleanText(obj.get("verification_summary")?.asString).ifBlank {
            when (status) {
                STATUS_VERIFIED -> "Alle Angaben stimmen mit dem Gesprächsverlauf überein."
                STATUS_CORRECTED -> "Abweichungen erkannt und automatisch anhand des Gesprächs korrigiert."
                else -> "Einzelne Angaben sind unklar und sollten kurz geprüft werden."
            }
        }

        return CallVerificationReportDto(
            status = status,
            confidence_score = score,
            verification_summary = summaryLine,
            verified_facts = mergedFacts,
            discrepancies = mergedDiscrepancies,
            corrected_summary = correctedSummary,
            corrected_rahmenbedingungen = correctedRahmen,
            corrected_action_required = correctedAction,
            corrected_urgency = correctedUrgency,
            corrected_callback_requested = correctedCallback,
            checked_at_iso = Instant.now().toString()
        )
    }

    private fun buildOfflineReport(
        input: CallVerificationInput,
        deterministicDiscrepancies: List<CallDiscrepancyDto>,
        deterministicFacts: List<CallFactCheckDto>,
        isOfflinePending: Boolean
    ): CallVerificationReportDto {
        val callerLines = extractCallerLines(input.transcriptLines)
        val hasCritical = deterministicDiscrepancies.any { it.severity == "critical" }
        val status = when {
            callerLines.isEmpty() -> STATUS_UNCERTAIN
            hasCritical -> STATUS_UNCERTAIN
            isOfflinePending -> STATUS_PENDING
            deterministicDiscrepancies.isNotEmpty() -> STATUS_UNCERTAIN
            else -> STATUS_VERIFIED
        }
        val msg = when {
            callerLines.isEmpty() -> "Kein gesprochener Anrufer-Text für den Abgleich vorhanden."
            hasCritical -> "Mögliche Abweichung bei Uhrzeit, Datum oder Nummer erkannt."
            isOfflinePending -> "Vorprüfung aktiv; vollständige KI-Prüfung erfolgt bei Netzverbindung."
            else -> "Basisprüfung ohne Abweichungen abgeschlossen."
        }
        return CallVerificationReportDto(
            status = status,
            confidence_score = if (status == STATUS_VERIFIED) 85 else if (status == STATUS_PENDING) 70 else 55,
            verification_summary = msg,
            verified_facts = deterministicFacts.take(6),
            discrepancies = deterministicDiscrepancies.take(6),
            corrected_summary = sanitizeCleanText(input.understoodSummary),
            corrected_rahmenbedingungen = input.understoodRahmenbedingungen.map { sanitizeCleanText(it) }.filter { it.isNotBlank() },
            corrected_action_required = sanitizeCleanText(input.understoodActionRequired),
            corrected_urgency = PhoneConciergeManager.normalizeUrgency(input.understoodUrgency),
            corrected_callback_requested = input.understoodCallbackRequested,
            checked_at_iso = Instant.now().toString()
        )
    }

    suspend fun verifyCallNotes(
        context: Context?,
        input: CallVerificationInput,
        allowNetwork: Boolean = true
    ): CallVerificationReportDto = withContext(Dispatchers.IO) {
        val (detDiscrepancies, detFacts) = runDeterministicCrossCheck(input)
        val callerLines = extractCallerLines(input.transcriptLines)

        if (!allowNetwork || context == null || callerLines.isEmpty()) {
            return@withContext buildOfflineReport(
                input = input,
                deterministicDiscrepancies = detDiscrepancies,
                deterministicFacts = detFacts,
                isOfflinePending = callerLines.isNotEmpty() && !allowNetwork
            )
        }

        val bundle = try {
            VertexClientTokenCache.getOrFetchValidBundle(context)
        } catch (_: Exception) {
            null
        } ?: return@withContext buildOfflineReport(
            input = input,
            deterministicDiscrepancies = detDiscrepancies,
            deterministicFacts = detFacts,
            isOfflinePending = true
        )

        return@withContext try {
            val url = VertexClientTokenCache.buildModelGenerateContentUrl("gemini-3.5-flash", bundle)
            val prompt = buildVerificationPrompt(input)
            val bodyObj = JsonObject().apply {
                add("contents", JsonArray().apply {
                    add(JsonObject().apply {
                        addProperty("role", "user")
                        add("parts", JsonArray().apply {
                            add(JsonObject().apply { addProperty("text", prompt) })
                        })
                    })
                })
                add("generationConfig", JsonObject().apply {
                    addProperty("temperature", 0.1)
                    addProperty("responseMimeType", "application/json")
                })
            }

            val req = Request.Builder()
                .url(url)
                .header("Authorization", "Bearer ${bundle.accessToken}")
                .post(gson.toJson(bodyObj).toRequestBody(jsonMediaType))
                .build()

            httpClient.newCall(req).execute().use { resp ->
                if (!resp.isSuccessful) {
                    return@withContext buildOfflineReport(
                        input = input,
                        deterministicDiscrepancies = detDiscrepancies,
                        deterministicFacts = detFacts,
                        isOfflinePending = true
                    )
                }
                val rawBody = resp.body?.string().orEmpty()
                val parsedRoot = gson.fromJson(rawBody, JsonObject::class.java)
                val text = parsedRoot
                    ?.getAsJsonArray("candidates")
                    ?.firstOrNull()
                    ?.asJsonObject
                    ?.getAsJsonObject("content")
                    ?.getAsJsonArray("parts")
                    ?.firstOrNull()
                    ?.asJsonObject
                    ?.get("text")
                    ?.asString
                    .orEmpty()

                if (text.isBlank()) {
                    buildOfflineReport(input, detDiscrepancies, detFacts, isOfflinePending = true)
                } else {
                    parseVerificationJson(text, input, detDiscrepancies, detFacts)
                }
            }
        } catch (_: Exception) {
            buildOfflineReport(
                input = input,
                deterministicDiscrepancies = detDiscrepancies,
                deterministicFacts = detFacts,
                isOfflinePending = true
            )
        }
    }
}
