#!/usr/bin/env bash
# Gera <id>-sbs.html a partir de cada <id>-sbs.md (e README.html) com pandoc.
set -euo pipefail
cd "$(dirname "$0")"
for md in *.md; do
    html="${md%.md}.html"
    pandoc "$md" -f gfm -t html5 -s --metadata pagetitle="${md%.md}" \
        --metadata lang=pt-BR -o "$html"
done
echo "ok: $(ls *.html | wc -l | tr -d ' ') html"
