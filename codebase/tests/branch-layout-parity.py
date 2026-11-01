#!/usr/bin/env python3
"""Guard the two deployable ePaper layouts in this repository.

main keeps the application beneath codebase/ while cw-epaper deploys from the
repository root. The executable/runtime sources MUST be byte-identical in the
same release commit; never solve this by replacing one tree and losing features.
"""
from __future__ import annotations
import hashlib
import pathlib
import subprocess
import sys

root=pathlib.Path(__file__).resolve().parents[2]
prefixes=("app/","public_html/","config/","bin/","database/","ai_processing/")
metadata={"VERSION","manifest.json","composer.json","composer.lock"}
tracked=set(filter(None,subprocess.check_output(["git","ls-files","-z"],cwd=root).decode().split("\0")))
app_files={p for p in tracked if p.startswith(prefixes) or p in metadata}
code_files={p[len("codebase/"):] for p in tracked if p.startswith("codebase/") and (p[len("codebase/"):].startswith(prefixes) or p[len("codebase/"):] in metadata)}
only_root=sorted(app_files-code_files)
only_codebase=sorted(code_files-app_files)
different=[]
for path in sorted(app_files & code_files):
    p=root/path
    q=root/"codebase"/path
    if not p.is_file() or not q.is_file() or hashlib.sha256(p.read_bytes()).digest()!=hashlib.sha256(q.read_bytes()).digest():
        different.append(path)

print(f"ePaper deploy layouts: root={len(app_files)} codebase={len(code_files)} common={len(app_files&code_files)}")
if only_root or only_codebase or different:
    for title,items in (("only root",only_root),("only codebase",only_codebase),("different",different)):
        if items:
            print(f"PARITY BLOCK: {title}={len(items)}",file=sys.stderr)
            for p in items[:12]:print("  "+p,file=sys.stderr)
    print("Both branches must promote one reviewed commit with byte-identical runtime sources.",file=sys.stderr)
    sys.exit(1)
if not app_files:
    raise SystemExit("PARITY BLOCK: no deployable source files in either layout")
print("PASS: deployable ePaper runtime source trees are byte-identical.")
