#!/usr/bin/env bash
# REL-001: guarded, reproducible first public prerelease.
set -euo pipefail
export LC_ALL=C
TAG='v0.1.0-beta.1'
ZIP_NAME='simplehtmldom-modernized-v0.1.0-beta.1.zip'
ZIP_BUILT='simplehtmldom-modernized-development.zip'
EXPECTED_SOURCE='bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25'
EXPECTED_ORIGINAL='d3fcca27ce5d1c6ddb06402d8ea22ee1b3989f62c44e2025154c9d4eef501cdd'
ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"
: "$GH_REPO"
: "$GH_TOKEN"
: "$GITHUB_SHA"
test "$GH_REPO" = 'jarifais/simplehtmldom-modernized'
test "$(git rev-parse HEAD)" = "$GITHUB_SHA"

scratch="$(mktemp -d)"
release_created=false
release_published=false
cleanup() {
  rc=$?
  if [ "$rc" -ne 0 ] && [ "$release_created" = true ] && [ "$release_published" = false ]; then
    echo 'REL-001: fallo antes de publicar; eliminando borrador y etiqueta creados.' >&2
    gh release delete "$TAG" --repo "$GH_REPO" --cleanup-tag --yes || true
  fi
  rm -rf -- "$scratch"
  exit "$rc"
}
trap cleanup EXIT

# Only the exact approved main commit may be released.
main_sha="$(gh api "repos/$GH_REPO/branches/main" --jq '.commit.sha')"
test "$main_sha" = "$GITHUB_SHA" || { echo 'main ha cambiado; abortado.' >&2; exit 1; }
if gh release view "$TAG" --repo "$GH_REPO" >/dev/null 2>&1; then
  echo 'La release ya existe, no se sobrescribe.' >&2
  exit 1
fi
test -z "$(git ls-remote origin "refs/tags/$TAG")" || { echo 'La etiqueta ya existe.' >&2; exit 1; }

test "$(sha256sum simple_html_dom.php | cut -d' ' -f1)" = "$EXPECTED_SOURCE"
test "$(sha256sum librerias/fase1/simple_html_dom.php | cut -d' ' -f1)" = "$EXPECTED_SOURCE"
test "$(sha256sum librerias/original/simple_html_dom.php | cut -d' ' -f1)" = "$EXPECTED_ORIGINAL"
cmp -s simple_html_dom.php librerias/fase1/simple_html_dom.php
php -l simple_html_dom.php
php -l librerias/fase1/simple_html_dom.php
test -s LICENSE
test -s releases/NOTAS_v0.1.0-beta.1.md

gh api "repos/$GH_REPO/actions/runs?head_sha=$GITHUB_SHA&event=push&per_page=100" > "$scratch/runs.json"
require_run() {
  local path="$1" count="$2" run status conclusion jobs
  run="$(jq -cer --arg path "$path" '[.workflow_runs[] | select(.head_branch=="main" and .head_sha==env.GITHUB_SHA and .path==$path)] | sort_by(.created_at, .id) | last' "$scratch/runs.json")"
  status="$(jq -r '.status' <<< "$run")"
  conclusion="$(jq -r '.conclusion' <<< "$run")"
  test "$status" = completed && test "$conclusion" = success || {
    echo "Workflow $path no supera pruebas en main ($status/$conclusion)." >&2
    exit 1
  }
  run="$(jq -r '.id' <<< "$run")"
  gh api "repos/$GH_REPO/actions/runs/$run/jobs?per_page=100" > "$scratch/jobs.json"
  jobs="$(jq -r '.total_count' "$scratch/jobs.json")"
  test "$jobs" = "$count" || { echo "Jobs inesperados en $path: $jobs" >&2; exit 1; }
  jq -e --argjson count "$count" '.total_count==$count and ([.jobs[] | select(.status=="completed" and .conclusion=="success")] | length)==$count' "$scratch/jobs.json" >/dev/null
  printf '%s' "$run"
}
ci_run="$(require_run '.github/workflows/ci.yml' 4)"
zip_run="$(require_run '.github/workflows/distribucion.yml' 3)"
echo "CI-001 PASS, run $ci_run. DIST-002 PASS, run $zip_run."

mkdir -p "$scratch/linux" "$scratch/windows" "$scratch/unpacked" "$scratch/assets" "$scratch/downloaded"
gh run download "$zip_run" --repo "$GH_REPO" --name paquete-ubuntu-24.04 --dir "$scratch/linux"
gh run download "$zip_run" --repo "$GH_REPO" --name paquete-windows-2025 --dir "$scratch/windows"
for platform in linux windows; do
  test -s "$scratch/$platform/$ZIP_BUILT"
  test -s "$scratch/$platform/$ZIP_BUILT.sha256"
  (cd "$scratch/$platform" && sha256sum --check "$ZIP_BUILT.sha256")
done
cmp -s "$scratch/linux/$ZIP_BUILT" "$scratch/windows/$ZIP_BUILT"
cmp -s "$scratch/linux/$ZIP_BUILT.sha256" "$scratch/windows/$ZIP_BUILT.sha256"

printf '%s\n' LICENSE README.md SHA256SUMS.txt ejemplos/ejemplo_basico.php simple_html_dom.php | sort > "$scratch/expected.txt"
unzip -Z -1 "$scratch/linux/$ZIP_BUILT" | sort > "$scratch/actual.txt"
cmp -s "$scratch/expected.txt" "$scratch/actual.txt"
unzip -tq "$scratch/linux/$ZIP_BUILT"
unzip -qq "$scratch/linux/$ZIP_BUILT" -d "$scratch/unpacked"
(cd "$scratch/unpacked" && sha256sum --check SHA256SUMS.txt)
cmp -s simple_html_dom.php "$scratch/unpacked/simple_html_dom.php"
cmp -s LICENSE "$scratch/unpacked/LICENSE"
php -l "$scratch/unpacked/simple_html_dom.php"
php -l "$scratch/unpacked/ejemplos/ejemplo_basico.php"
test "$(php "$scratch/unpacked/ejemplos/ejemplo_basico.php" | tr -d '\r\n')" = 'Hola mundo'

assets="$scratch/assets"
cp -p simple_html_dom.php LICENSE "$assets/"
cp -p releases/NOTAS_v0.1.0-beta.1.md "$assets/NOTAS_v0.1.0-beta.1.md"
cp -p "$scratch/linux/$ZIP_BUILT" "$assets/$ZIP_NAME"
(cd "$assets" && sha256sum "$ZIP_NAME" > "$ZIP_NAME.sha256")
(cd "$assets" && sha256sum LICENSE NOTAS_v0.1.0-beta.1.md simple_html_dom.php "$ZIP_NAME" > SHA256SUMS_RELEASE.txt)
(cd "$assets" && sha256sum --check SHA256SUMS_RELEASE.txt && sha256sum --check "$ZIP_NAME.sha256")
zip_hash="$(sha256sum "$assets/$ZIP_NAME" | cut -d' ' -f1)"
echo "ZIP validado SHA-256: $zip_hash"

test "$(gh api "repos/$GH_REPO/branches/main" --jq '.commit.sha')" = "$GITHUB_SHA"
gh release create "$TAG" \
  "$assets/simple_html_dom.php" \
  "$assets/$ZIP_NAME" \
  "$assets/$ZIP_NAME.sha256" \
  "$assets/SHA256SUMS_RELEASE.txt" \
  "$assets/LICENSE" \
  "$assets/NOTAS_v0.1.0-beta.1.md" \
  --repo "$GH_REPO" --target "$GITHUB_SHA" \
  --title 'Simple HTML DOM Modernized v0.1.0-beta.1' \
  --notes-file releases/NOTAS_v0.1.0-beta.1.md \
  --draft --prerelease --latest=false
release_created=true

release_json="$(gh release view "$TAG" --repo "$GH_REPO" --json isDraft,assets,tagName)"
jq -e '.isDraft==true and .tagName=="v0.1.0-beta.1" and (.assets | length)==6' <<< "$release_json" >/dev/null
printf '%s\n' LICENSE NOTAS_v0.1.0-beta.1.md SHA256SUMS_RELEASE.txt simple_html_dom.php "$ZIP_NAME" "$ZIP_NAME.sha256" | sort > "$scratch/expected-assets.txt"
jq -r '.assets[].name' <<< "$release_json" | sort > "$scratch/actual-assets.txt"
cmp -s "$scratch/expected-assets.txt" "$scratch/actual-assets.txt"
gh release download "$TAG" --repo "$GH_REPO" --dir "$scratch/downloaded"
for name in LICENSE NOTAS_v0.1.0-beta.1.md SHA256SUMS_RELEASE.txt simple_html_dom.php "$ZIP_NAME" "$ZIP_NAME.sha256"; do
  cmp -s "$assets/$name" "$scratch/downloaded/$name" || { echo "Asset alterado: $name" >&2; exit 1; }
done
echo 'Borrador y seis assets: SHA-256 y bytes verificados.'

test "$(gh api "repos/$GH_REPO/branches/main" --jq '.commit.sha')" = "$GITHUB_SHA"
gh release edit "$TAG" --repo "$GH_REPO" --draft=false --prerelease --latest=false
release_published=true
gh api "repos/$GH_REPO/releases/tags/$TAG" > "$scratch/published.json"
jq -e '.tag_name=="v0.1.0-beta.1" and .draft==false and .prerelease==true and (.assets|length)==6' "$scratch/published.json" >/dev/null
tag_sha="$(git ls-remote origin "refs/tags/$TAG" | cut -f1)"
test "$tag_sha" = "$GITHUB_SHA" || { echo "TAG SHA inesperado: $tag_sha" >&2; exit 1; }
echo "REL-001: PASS - beta publicada en $GITHUB_SHA; ZIP=$zip_hash; 6 assets, etiqueta y licencia verificados."
