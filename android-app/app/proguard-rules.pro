# Strip verbose/debug/info logs from release builds
-assumenosideeffects class android.util.Log {
    public static int v(...);
    public static int d(...);
    public static int i(...);
}

# Kotlin metadata & reflection
-keep class kotlin.Metadata { *; }

# Retrofit & OkHttp
-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn retrofit2.**
-keep class retrofit2.** { *; }
-keepattributes Signature, InnerClasses, EnclosingMethod
-keepattributes RuntimeVisibleAnnotations, RuntimeVisibleParameterAnnotations, Exceptions
-keepclassmembers,allowobfuscation interface * {
    @retrofit2.http.* <methods>;
}

# Gson & TypeToken subclasses
-keepattributes *Annotation*
-keepclassmembers class * {
    @com.google.gson.annotations.SerializedName <fields>;
}
-keep class com.google.gson.** { *; }
-keep class * extends com.google.gson.reflect.TypeToken
-keepclassmembers class * extends com.google.gson.reflect.TypeToken { *; }

# Keep all API, Domain, Alarm, and Music Data Models to prevent JSON serialization breakage
-keep class de.meinseelenfunke.app.data.** { *; }
-keep class de.meinseelenfunke.app.util.SmartAlarmItem { *; }
-keep class de.meinseelenfunke.app.util.TimerPresetItem { *; }
-keep class de.meinseelenfunke.app.util.SmartPlugDevice { *; }
-keep class de.meinseelenfunke.app.util.SmartCameraDevice { *; }
-keep class de.meinseelenfunke.app.util.WakeUpLogEntry { *; }
-keep class de.meinseelenfunke.app.util.MusicPlaylistDto { *; }
-keep class de.meinseelenfunke.app.util.MusicTrackDto { *; }
-keep class de.meinseelenfunke.app.util.MusicImportProgress { *; }
-keep class de.meinseelenfunke.app.util.PendingPhoneSecretaryNote { *; }
-keep class de.meinseelenfunke.app.util.CachedWhitelistContact { *; }
-keep class de.meinseelenfunke.app.util.SentinelQueuedEvent { *; }
-keep class de.meinseelenfunke.app.util.OtaReleaseManifest { *; }
-keep class de.meinseelenfunke.app.ui.screens.SelectedFile { *; }

# Keep Android Manifest Components (Widgets, Services, Receivers, Activities)
-keep public class * extends android.appwidget.AppWidgetProvider
-keep public class * extends android.widget.RemoteViewsService
-keep public class * extends android.app.Service
-keep public class * extends android.content.BroadcastReceiver
-keep public class * extends android.app.Activity

# Coroutines
-keepnames class kotlinx.coroutines.internal.MainDispatcherFactory {}
-keepnames class kotlinx.coroutines.CoroutineExceptionHandler {}

# Google GenAI / Gemini & Firebase
-dontwarn com.google.genai.**
-keep class com.google.genai.** { *; }
-dontwarn com.google.firebase.**
-dontwarn com.google.android.gms.**
-dontwarn org.conscrypt.**
-dontwarn org.bouncycastle.**
-dontwarn org.openjsse.**
