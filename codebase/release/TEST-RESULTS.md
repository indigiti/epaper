# v1.1.9.13 Test Results

PASS:
- Installer PHP syntax.
- Historical signature: `authExperience(string $formBody,string $brand)`.
- Historical reversed signature: `authExperience(string $brand,string $formBody)`.
- Patched View.php PHP syntax for both signatures.
- Rendered output contains real `<form>` and `data-auth-version="clean-renderer-v5"`.
- Hostile legacy CSS test at 1440x900: card ~578px, form ~298px, security→quote 12px, quote→bottom 23px.
- Hostile legacy CSS test at 390x844: card ~568px, form ~287px, security→quote 12px, quote→bottom 19px.
- Hostile legacy CSS test at 320x720: card ~600px, form ~287px, security→quote 12px, quote→bottom 19px.
- Installer self-deletes after successful simulated install.
- Service-worker cache version changes after successful simulated install.
