# v1.1.9.13 Clean Login Renderer Installer Fix

This installer replaces only `View::authExperience()` and preserves the POST `/login` authentication flow.

It backs up `app/Core/View.php`, detects the auth function parameter order, writes the isolated `epx-*` renderer, verifies required markers, and syntax-checks only with a genuine PHP CLI binary. Any executable whose basename contains `fpm` is explicitly rejected as a linter.

If a real CLI binary cannot be found, the installer does not falsely fail: it reports that CLI lint was unavailable after structural verification. If a genuine CLI lint runs and fails, the original View.php backup is restored.
