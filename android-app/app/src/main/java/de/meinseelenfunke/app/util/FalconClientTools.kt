package de.meinseelenfunke.app.util

import android.content.Context
import com.google.gson.Gson
import com.google.gson.JsonArray
import com.google.gson.JsonElement
import com.google.gson.JsonObject
import de.meinseelenfunke.app.data.api.AgDirectApi
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import java.util.Locale

data class FalconMusicMatchResult(
    val tracks: List<MusicTrackDto>,
    val playlistId: String,
    val playlistName: String?,
    val shuffle: Boolean,
    val usedFallbackFavorites: Boolean
)

data class FalconResolvedDestination(
    val target: NavigationBridge.NavTarget,
    val organizerSubTab: Int = 0,
    val openReceiptScanner: Boolean = false,
    val label: String
)

object FalconClientTools {
    private val gson = Gson()

    val SPORT_KEYWORDS = listOf(
        "sport", "workout", "fitness", "gym", "power",
        "training", "energy", "cardio", "laufen", "motivation", "hype", "pump"
    )

    val VALID_EMOTIONS = setOf(
        "idle", "listening", "thinking", "speaking", "happy", "proud", "focused"
    )

    val LOCAL_TOOL_NAMES = setOf(
        "falcon_music_control",
        "falcon_camera_control",
        "falcon_send_to_antigravity",
        "falcon_check_antigravity_status",
        "falcon_app_navigate",
        "falcon_set_emotion"
    )

    fun isLocalFalconTool(name: String?): Boolean {
        return name != null && name in LOCAL_TOOL_NAMES
    }

    fun normalizeEmotion(raw: String?): String {
        val clean = raw?.trim()?.lowercase(Locale.ROOT).orEmpty()
        return when {
            clean in VALID_EMOTIONS -> clean
            clean == "neutral" || clean == "calm" -> "idle"
            clean == "excited" || clean == "smile" || clean == "joy" -> "happy"
            clean == "concentrated" || clean == "serious" -> "focused"
            else -> "idle"
        }
    }

    fun resolveFalcon3dMode(
        isConnecting: Boolean,
        isConnected: Boolean,
        isAiSpeaking: Boolean,
        isRecording: Boolean
    ): String {
        return when {
            isConnecting -> "connecting"
            isConnected && isAiSpeaking -> "speaking"
            isConnected && isRecording -> "listening"
            isConnected -> "thinking"
            else -> "idle"
        }
    }

    fun buildFalconSceneStateJson(
        mode: String,
        audioLevel: Float,
        emotion: String,
        isDark: Boolean,
        cameraActive: Boolean
    ): String {
        val clampedAudio = audioLevel.coerceIn(0f, 1f)
        val roundedAudio = kotlin.math.round(clampedAudio * 1000f) / 1000f
        val obj = JsonObject().apply {
            addProperty("mode", mode)
            addProperty("audioLevel", roundedAudio)
            addProperty("emotion", normalizeEmotion(emotion))
            addProperty("isDark", isDark)
            addProperty("cameraActive", cameraActive)
        }
        return gson.toJson(obj)
    }

    fun buildRealtimeVideoFramePacket(base64Jpeg: String): JsonObject {
        return JsonObject().apply {
            add("realtimeInput", JsonObject().apply {
                add("video", JsonObject().apply {
                    addProperty("mimeType", "image/jpeg")
                    addProperty("data", base64Jpeg)
                })
            })
        }
    }

    fun shouldAttachCapturedPhoto(
        explicitAttachFlag: Boolean?,
        photo: FalconCapturedPhoto?,
        currentSessionId: String?,
        nowMs: Long = System.currentTimeMillis()
    ): Boolean {
        if (photo == null) return false
        if (explicitAttachFlag == false) return false
        if (explicitAttachFlag == true) return true
        if (photo.attachedToInbox) return false
        val sameSession = !currentSessionId.isNullOrBlank() && photo.sessionId == currentSessionId
        val recentCapture = (nowMs - photo.capturedAtMs) in 0L..180_000L
        return sameSession || recentCapture
    }

    fun isSportQuery(query: String?): Boolean {
        if (query.isNullOrBlank()) return false
        val lower = query.lowercase(Locale.ROOT)
        return SPORT_KEYWORDS.any { kw -> lower.contains(kw) }
    }

    fun matchMusicForFalcon(
        playlists: List<MusicPlaylistDto>,
        allTracks: List<MusicTrackDto>,
        action: String,
        query: String?
    ): FalconMusicMatchResult {
        val visiblePlaylists = playlists.filterNot {
            it.name.equals("__FUNKENWERK_FAV_SYNC__", ignoreCase = true)
        }
        val consolidatedTracks = if (allTracks.isNotEmpty()) {
            allTracks
        } else {
            visiblePlaylists.flatMap { it.tracks }.distinctBy { it.id }
        }

        val cleanAction = action.trim().lowercase(Locale.ROOT)
        val cleanQuery = query?.trim().orEmpty()
        val treatAsSport = cleanAction == "play_sport" || isSportQuery(cleanQuery)

        if (treatAsSport) {
            val sportPlaylists = visiblePlaylists.filter { pl ->
                val hay = "${pl.name} ${pl.description}".lowercase(Locale.ROOT)
                SPORT_KEYWORDS.any { kw -> hay.contains(kw) } && pl.tracks.isNotEmpty()
            }
            if (sportPlaylists.isNotEmpty()) {
                val chosenPl = sportPlaylists.first()
                return FalconMusicMatchResult(
                    tracks = chosenPl.tracks,
                    playlistId = chosenPl.id,
                    playlistName = chosenPl.name,
                    shuffle = true,
                    usedFallbackFavorites = false
                )
            }

            val sportTracks = consolidatedTracks.filter { tr ->
                val hay = "${tr.title} ${tr.artist}".lowercase(Locale.ROOT)
                SPORT_KEYWORDS.any { kw -> hay.contains(kw) }
            }
            if (sportTracks.isNotEmpty()) {
                return FalconMusicMatchResult(
                    tracks = sportTracks,
                    playlistId = "all",
                    playlistName = "Sport-Auswahl",
                    shuffle = true,
                    usedFallbackFavorites = false
                )
            }
        } else if (cleanQuery.isNotEmpty()) {
            val tokens = cleanQuery
                .lowercase(Locale.ROOT)
                .split(Regex("\\s+"))
                .filter { it.length >= 2 }
                .ifEmpty { listOf(cleanQuery.lowercase(Locale.ROOT)) }

            val matchedPl = visiblePlaylists.firstOrNull { pl ->
                val hay = "${pl.name} ${pl.description}".lowercase(Locale.ROOT)
                pl.tracks.isNotEmpty() && (hay.contains(cleanQuery.lowercase(Locale.ROOT)) || tokens.all { hay.contains(it) })
            }
            if (matchedPl != null) {
                return FalconMusicMatchResult(
                    tracks = matchedPl.tracks,
                    playlistId = matchedPl.id,
                    playlistName = matchedPl.name,
                    shuffle = false,
                    usedFallbackFavorites = false
                )
            }

            val matchedTracks = consolidatedTracks.filter { tr ->
                val hay = "${tr.title} ${tr.artist}".lowercase(Locale.ROOT)
                hay.contains(cleanQuery.lowercase(Locale.ROOT)) || tokens.all { hay.contains(it) }
            }
            if (matchedTracks.isNotEmpty()) {
                return FalconMusicMatchResult(
                    tracks = matchedTracks,
                    playlistId = "all",
                    playlistName = cleanQuery,
                    shuffle = false,
                    usedFallbackFavorites = false
                )
            }
        }

        // Guaranteed immediate music fallback: Shuffle over favorites (or full library)
        val favorites = consolidatedTracks.filter { it.isFavorite }
        val fallbackPool = favorites.ifEmpty { consolidatedTracks }
        return FalconMusicMatchResult(
            tracks = fallbackPool,
            playlistId = if (favorites.isNotEmpty()) "favorites" else "all",
            playlistName = if (favorites.isNotEmpty()) "Lieblingssongs" else "Alle Songs",
            shuffle = true,
            usedFallbackFavorites = true
        )
    }

    fun resolveFalconAppDestination(rawDestination: String?): FalconResolvedDestination {
        return when (rawDestination?.trim()?.lowercase(Locale.ROOT)) {
            "dashboard", "zentrum", "home" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ZENTRUM,
                label = "Dashboard"
            )
            "finances", "finance", "finanzen", "buchhaltung" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.FINANCES,
                label = "Finanzen"
            )
            "receipt_scanner", "beleg", "belegscanner", "scanner" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ZENTRUM,
                openReceiptScanner = true,
                label = "Beleg-Scanner"
            )
            "orders", "bestellungen", "auftraege" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORDERS,
                label = "Bestellungen"
            )
            "tasks", "aufgaben", "todo" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 0,
                label = "Aufgaben"
            )
            "calendar", "kalender", "termine" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 1,
                label = "Kalender"
            )
            "routine", "tagesroutine" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 2,
                label = "Routine"
            )
            "shopping", "einkauf", "einkaufsliste" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 3,
                label = "Einkaufsliste"
            )
            "mail", "email", "postfach" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 4,
                label = "E-Mail"
            )
            "music", "musik", "player" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 5,
                label = "Musik"
            )
            "alarm", "wecker", "timer" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 6,
                label = "Wecker"
            )
            "phone", "telefon", "anrufbeantworter" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 7,
                label = "Anrufbeantworter"
            )
            "settings", "einstellungen" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.SETTINGS,
                label = "Einstellungen"
            )
            else -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ZENTRUM,
                label = "Dashboard"
            )
        }
    }

    fun appendFalconToolDeclarations(decls: JsonArray) {
        val existing = mutableSetOf<String>()
        for (i in 0 until decls.size()) {
            val n = decls.get(i)?.takeIf { it.isJsonObject }?.asJsonObject?.get("name")?.asString
            if (!n.isNullOrBlank()) existing.add(n)
        }

        if ("falcon_music_control" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_music_control")
                addProperty(
                    "description",
                    "Steuert sofort den lokalen Musikplayer auf Alinas Smartphone (Sportmusik, Playlist/Song-Suche, Pause, Weiter, Zurück, Shuffle, Lautstärke)."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("action", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Aktion: play_sport, play_query, pause, resume, next, previous, shuffle, set_volume"
                            )
                        })
                        add("query", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Optionaler Suchbegriff für Playlist, Genre, Song oder Künstler.")
                        })
                        add("volume_percent", JsonObject().apply {
                            addProperty("type", "INTEGER")
                            addProperty("description", "Optionale Lautstärke von 0 bis 100 bei set_volume.")
                        })
                    })
                    add("required", JsonArray().apply { add("action") })
                })
            })
        }

        if ("falcon_camera_control" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_camera_control")
                addProperty(
                    "description",
                    "Steuert Falcons Kamera-Augen auf dem Smartphone (Live-Sicht ein-/ausschalten, Kamera wechseln oder scharfes hochauflösendes Foto schießen)."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("action", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Aktion: enable_vision, disable_vision, switch_camera, take_photo")
                        })
                        add("lens", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Optionale Linse: back oder front")
                        })
                    })
                    add("required", JsonArray().apply { add("action") })
                })
            })
        }

        if ("falcon_send_to_antigravity" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_send_to_antigravity")
                addProperty(
                    "description",
                    "Sendet einen Entwicklungsauftrag, eine Idee oder einen Bug-Bericht (optional inklusive des gerade geschossenen Kamera-Fotos) direkt in den Antigravity Master-Chat."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("title", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Kurzer klarer Haupttitel des Auftrags.")
                        })
                        add("body", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Optionale detaillierte Beschreibung.")
                        })
                        add("checklist", JsonObject().apply {
                            addProperty("type", "ARRAY")
                            add("items", JsonObject().apply { addProperty("type", "STRING") })
                            addProperty("description", "Optionale Liste konkreter Unterpunkte.")
                        })
                        add("execution_mode", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Modus: concept (Erst Konzept) oder direct (Direkt umsetzen).")
                        })
                        add("attach_captured_photo", JsonObject().apply {
                            addProperty("type", "BOOLEAN")
                            addProperty("description", "True, wenn das zuletzt mit take_photo aufgenommene Foto angehängt werden soll.")
                        })
                    })
                    add("required", JsonArray().apply { add("title") })
                })
            })
        }

        if ("falcon_check_antigravity_status" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_check_antigravity_status")
                addProperty(
                    "description",
                    "Prüft den aktuellen Live-Status der letzten Aufträge im Antigravity-Entwicklungsleitstand inklusive Antworten."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject())
                })
            })
        }

        if ("falcon_app_navigate" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_app_navigate")
                addProperty(
                    "description",
                    "Öffnet sofort den gewünschten Bereich in Alinas Funkenwerk Android-App."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("destination", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Ziel: dashboard, finances, receipt_scanner, orders, tasks, calendar, routine, mail, music, phone, settings"
                            )
                        })
                    })
                    add("required", JsonArray().apply { add("destination") })
                })
            })
        }

        if ("falcon_set_emotion" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_set_emotion")
                addProperty(
                    "description",
                    "Setzt Falcons 3D-Gesichtsausdruck und Körperhaltung passend zur Gesprächssituation."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("emotion", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Emotion: idle, listening, thinking, speaking, happy, proud, focused")
                        })
                    })
                    add("required", JsonArray().apply { add("emotion") })
                })
            })
        }
    }

    suspend fun executeLocalFalconTool(
        context: Context,
        toolName: String,
        argsElement: JsonElement?,
        chatSessionId: String,
        onEmotionChanged: (String) -> Unit,
        onNavigate: (FalconResolvedDestination) -> Unit,
        agApiOverride: AgDirectApi? = null
    ): JsonObject {
        val appCtx = context.applicationContext
        val args = if (argsElement != null && argsElement.isJsonObject) argsElement.asJsonObject else JsonObject()

        return when (toolName) {
            "falcon_set_emotion" -> {
                val emo = normalizeEmotion(args.optString("emotion"))
                onEmotionChanged(emo)
                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("emotion", emo)
                }
            }

            "falcon_app_navigate" -> {
                val destRaw = args.optString("destination")
                val resolved = resolveFalconAppDestination(destRaw)
                withContext(Dispatchers.Main.immediate) {
                    if (resolved.openReceiptScanner) {
                        NavigationBridge.pendingOpenQuickEntry = true
                    }
                    NavigationBridge.triggerNavigation(resolved.target, resolved.organizerSubTab)
                    onNavigate(resolved)
                }
                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("destination", resolved.label)
                }
            }

            "falcon_music_control" -> {
                val action = args.optString("action").ifBlank { "resume" }.lowercase(Locale.ROOT)
                val query = args.optString("query")
                val volPercent = args.optInt("volume_percent", -1)

                withContext(Dispatchers.Main.immediate) {
                    MusicPlayerManager.ensureCatalogLoadedFast(appCtx)
                }

                when (action) {
                    "pause", "stop" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.pauseIfPlaying(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "pause")
                            addProperty("message", "Musik pausiert.")
                        }
                    }

                    "resume", "play" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.resumeOrStart(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "resume")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    "next", "skip" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.playNext(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "next")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    "previous", "prev", "back" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.playPrevious(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "previous")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    "shuffle" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.setShuffleEnabled(appCtx, true)
                            if (!MusicPlayerManager.isPlaying.value) {
                                MusicPlayerManager.resumeOrStart(appCtx)
                            } else {
                                MusicPlayerManager.playNext(appCtx)
                            }
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "shuffle")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    "set_volume", "volume" -> {
                        val applied = MusicPlayerManager.setUserVolumePercent(
                            appCtx,
                            if (volPercent in 0..100) volPercent else 70
                        )
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "set_volume")
                            addProperty("volume_percent", applied)
                        }
                    }

                    else -> {
                        // play_sport or play_query
                        var playlists = MusicPlayerManager.playlists.value
                        var allTracks = MusicPlayerManager.allTracks.value

                        if (allTracks.isEmpty()) {
                            val loaded = kotlinx.coroutines.CompletableDeferred<Unit>()
                            withContext(Dispatchers.Main.immediate) {
                                MusicPlayerManager.fetchPlaylists(appCtx) {
                                    loaded.complete(Unit)
                                }
                            }
                            withTimeoutOrNull(1800L) { loaded.await() }
                            playlists = MusicPlayerManager.playlists.value
                            allTracks = MusicPlayerManager.allTracks.value
                        } else {
                            withContext(Dispatchers.Main.immediate) {
                                MusicPlayerManager.fetchPlaylists(appCtx)
                            }
                        }

                        val match = matchMusicForFalcon(playlists, allTracks, action, query)
                        val startedTrack = if (match.tracks.isNotEmpty()) {
                            withContext(Dispatchers.Main.immediate) {
                                MusicPlayerManager.playQueueFromFalcon(
                                    context = appCtx,
                                    tracks = match.tracks,
                                    shuffle = match.shuffle,
                                    playlistId = match.playlistId
                                )
                            }
                        } else {
                            null
                        }

                        JsonObject().apply {
                            addProperty("status", if (startedTrack != null) "success" else "empty_library")
                            addProperty("action", action)
                            addProperty("playlist", match.playlistName ?: "")
                            addProperty("track", startedTrack?.title ?: "")
                            addProperty("artist", startedTrack?.artist ?: "")
                            addProperty("shuffle", match.shuffle)
                            addProperty("used_favorites_fallback", match.usedFallbackFavorites)
                        }
                    }
                }
            }

            "falcon_camera_control" -> {
                val action = args.optString("action").ifBlank { "enable_vision" }.lowercase(Locale.ROOT)
                val lens = args.optString("lens").takeIf { it.isNotBlank() }

                if (action != "disable_vision" && !FalconCameraController.hasCameraPermission(appCtx)) {
                    FalconCameraController.requestPermissionForAction(action, lens)
                    return JsonObject().apply {
                        addProperty("status", "permission_requested")
                        addProperty("action", action)
                        addProperty(
                            "message",
                            "Kamera-Berechtigung wurde auf dem Smartphone angefragt. Sobald Alina bestätigt, wird die Kamera automatisch aktiviert."
                        )
                    }
                }

                when (action) {
                    "disable_vision", "off", "stop" -> {
                        FalconCameraController.stopCamera()
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("vision_active", false)
                            addProperty("message", "Kamera-Sicht ausgeschaltet.")
                        }
                    }

                    "switch_camera", "flip" -> {
                        FalconCameraController.switchCameraAsync(appCtx, lens)
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("vision_active", FalconCameraController.isVisionActive.value)
                            addProperty("lens", lens ?: "switched")
                        }
                    }

                    "take_photo", "photo", "snapshot" -> {
                        val photo = FalconCameraController.captureHighResPhoto(
                            context = appCtx,
                            lens = lens,
                            sessionId = chatSessionId
                        )
                        if (photo != null) {
                            JsonObject().apply {
                                addProperty("status", "success")
                                addProperty("photo_captured", true)
                                addProperty("file_name", photo.fileName)
                                addProperty("width", photo.width)
                                addProperty("height", photo.height)
                                addProperty(
                                    "message",
                                    "Scharfes Foto (${photo.width}x${photo.height}) erfolgreich geschossen und für Anhänge bereitgelegt."
                                )
                            }
                        } else {
                            JsonObject().apply {
                                addProperty("status", "error")
                                addProperty("photo_captured", false)
                                addProperty("message", "Foto konnte nicht aufgenommen werden.")
                            }
                        }
                    }

                    else -> {
                        val started = FalconCameraController.startVision(appCtx, lens ?: "back")
                        JsonObject().apply {
                            addProperty("status", if (started) "success" else "error")
                            addProperty("vision_active", started)
                            addProperty("lens", FalconCameraController.activeLens.value)
                        }
                    }
                }
            }

            "falcon_send_to_antigravity" -> {
                val title = args.optString("title").trim()
                val body = args.optString("body").trim()
                val checklist = args.optStringList("checklist")
                val mode = AgDirectInboxManager.normalizeExecutionMode(args.optString("execution_mode"))
                val explicitAttach = args.optNullableBoolean("attach_captured_photo")

                val currentPhoto = FalconCameraController.lastCapturedPhoto.value
                val attachPhoto = shouldAttachCapturedPhoto(
                    explicitAttachFlag = explicitAttach,
                    photo = currentPhoto,
                    currentSessionId = chatSessionId
                )
                val preparedAttachment = if (attachPhoto && currentPhoto != null) {
                    currentPhoto.toPreparedAgAttachment()
                } else {
                    null
                }

                val combinedText = when {
                    title.isNotBlank() && body.isNotBlank() -> "$title\n$body"
                    title.isNotBlank() -> title
                    else -> body
                }

                val sentLive = AgDirectInboxManager.submitNewEntry(
                    context = appCtx,
                    rawText = combinedText,
                    checklist = checklist,
                    executionMode = mode,
                    attachment = preparedAttachment,
                    apiOverride = agApiOverride
                )
                if (preparedAttachment != null) {
                    FalconCameraController.markLastPhotoAttached()
                }

                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("sent_live", sentLive)
                    addProperty("queued_offline", !sentLive)
                    addProperty("execution_mode", mode)
                    addProperty("photo_attached", preparedAttachment != null)
                    addProperty(
                        "message",
                        if (sentLive) {
                            "Auftrag erfolgreich live an den Antigravity-Leitstand übermittelt."
                        } else {
                            "Auftrag verschlüsselt in der Offline-Warteschlange gesichert."
                        }
                    )
                }
            }

            "falcon_check_antigravity_status" -> {
                withTimeoutOrNull(4500L) {
                    AgDirectInboxManager.syncAll(appCtx, apiOverride = agApiOverride)
                } ?: AgDirectInboxManager.hydrateLocalState(appCtx)

                val recent = AgDirectInboxManager.itemsFlow.value.take(5)
                val itemsArr = JsonArray()
                recent.forEach { item ->
                    itemsArr.add(JsonObject().apply {
                        addProperty("id", item.id)
                        addProperty("title", item.title)
                        addProperty("status", item.status.badgeLabel)
                        addProperty("execution_mode", item.executionMode)
                        addProperty("has_attachment", item.hasAttachment)
                        addProperty("ag_response_summary", item.agResponseSummary ?: "")
                    })
                }
                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("open_count", AgDirectInboxManager.openCountFlow.value)
                    add("items", itemsArr)
                }
            }

            else -> JsonObject().apply {
                addProperty("status", "error")
                addProperty("message", "Unbekanntes lokales Tool: $toolName")
            }
        }
    }

    private fun JsonObject.optString(key: String): String {
        val el = this.get(key) ?: return ""
        return if (el.isJsonPrimitive) el.asString else ""
    }

    private fun JsonObject.optInt(key: String, default: Int): Int {
        val el = this.get(key) ?: return default
        return try {
            if (el.isJsonPrimitive) el.asInt else default
        } catch (_: Throwable) {
            default
        }
    }

    private fun JsonObject.optNullableBoolean(key: String): Boolean? {
        val el = this.get(key) ?: return null
        return try {
            if (el.isJsonPrimitive) el.asBoolean else null
        } catch (_: Throwable) {
            null
        }
    }

    private fun JsonObject.optStringList(key: String): List<String> {
        val el = this.get(key) ?: return emptyList()
        if (!el.isJsonArray) return emptyList()
        val out = mutableListOf<String>()
        el.asJsonArray.forEach { item ->
            if (item != null && item.isJsonPrimitive) {
                val s = item.asString.trim()
                if (s.isNotEmpty()) out.add(s)
            }
        }
        return out
    }
}
