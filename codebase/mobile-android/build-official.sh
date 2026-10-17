#!/usr/bin/env bash
set -euo pipefail
if ! command -v java >/dev/null 2>&1; then echo 'Java 17+ is required.' >&2; exit 1; fi
if ! command -v gradle >/dev/null 2>&1; then echo 'Gradle 8.13 is required (or build from Android Studio/GitHub Actions).' >&2; exit 1; fi
if [[ -z "${ANDROID_SDK_ROOT:-${ANDROID_HOME:-}}" ]]; then echo 'Set ANDROID_SDK_ROOT (or ANDROID_HOME) to the official Android SDK.' >&2; exit 1; fi
SDK="${ANDROID_SDK_ROOT:-${ANDROID_HOME}}"
if [[ ! -f "$SDK/platforms/android-36/android.jar" ]]; then echo 'Install platforms;android-36 with sdkmanager.' >&2; exit 1; fi
gradle --no-daemon clean lintDebug assembleDebug bundleRelease
printf '\nDebug APK: %s\nRelease AAB: %s\n' "$PWD/app/build/outputs/apk/debug/app-debug.apk" "$PWD/app/build/outputs/bundle/release/app-release.aab"
