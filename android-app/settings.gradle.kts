// Automatically adapt local.properties to the host OS (Windows Android Studio vs. WSL Linux CLI)
val localProps = file("local.properties")
val isWindows = System.getProperty("os.name").lowercase().contains("win")
if (isWindows) {
    val winSdk = file("C:/Users/konta/AppData/Local/Android/Sdk")
    if (winSdk.exists()) {
        localProps.writeText("sdk.dir=${winSdk.absolutePath.replace("\\", "\\\\")}\n")
    }
} else {
    val linuxSdk = file("/home/ubuntuxina/android-sdk")
    if (linuxSdk.exists()) {
        localProps.writeText("sdk.dir=${linuxSdk.absolutePath}\n")
    }
}

pluginManagement {
    repositories {
        google {
            content {
                includeGroupByRegex("com\\.android.*")
                includeGroupByRegex("com\\.google.*")
                includeGroupByRegex("androidx.*")
            }
        }
        mavenCentral()
        gradlePluginPortal()
    }
}
dependencyResolutionManagement {
    repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
    repositories {
        google()
        mavenCentral()
    }
}

rootProject.name = "Seelenfunke"
include(":app")
