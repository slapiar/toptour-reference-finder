#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="toptour-reference-finder"
PLUGIN_FILE="toptour-reference-finder.php"
BUILD_DIR="build"
DIST_DIR="dist"

if [[ ! -f "$PLUGIN_FILE" ]]; then
  echo "Chyba: súbor $PLUGIN_FILE neexistuje."
  exit 1
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Chyba: nie si v git repozitári."
  exit 1
fi

CURRENT_BRANCH="$(git branch --show-current)"
if [[ "$CURRENT_BRANCH" == "main" ]]; then
  echo "Chyba: release nerob priamo na main."
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  echo "Chyba: workspace nie je čistý. Commitni alebo stashni zmeny pred release."
  exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
  echo "Chyba: rsync nie je dostupný."
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "Chyba: zip nie je dostupný."
  exit 1
fi

CURRENT_VERSION="$(grep -E "^[[:space:]]*\*[[:space:]]+Version:" "$PLUGIN_FILE" | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"

if [[ -z "${CURRENT_VERSION:-}" ]]; then
  echo "Chyba: nepodarilo sa zistiť aktuálnu verziu z $PLUGIN_FILE."
  exit 1
fi

increment_version() {
  local version="$1"
  local part="${2:-patch}"
  IFS='.' read -r major minor patch <<< "$version"

  case "$part" in
    major)
      major=$((major + 1))
      minor=0
      patch=0
      ;;
    minor)
      minor=$((minor + 1))
      patch=0
      ;;
    patch)
      patch=$((patch + 1))
      ;;
    *)
      echo "Chyba: neznámy typ inkrementácie: $part"
      echo "Použitie: ./release.sh [patch|minor|major] [db]"
      exit 1
      ;;
  esac

  echo "${major}.${minor}.${patch}"
}

RELEASE_TYPE="${1:-patch}"
BUMP_DB_VERSION="${2:-no}"

NEW_VERSION="$(increment_version "$CURRENT_VERSION" "$RELEASE_TYPE")"

echo "Aktuálna verzia pluginu: $CURRENT_VERSION"
echo "Nová verzia pluginu:     $NEW_VERSION"

if [[ "$BUMP_DB_VERSION" == "db" ]]; then
  echo "DB verzia bude tiež bumpnutá na: $NEW_VERSION"
else
  echo "DB verzia ostáva nezmenená."
fi

# Plugin header Version.
sed -i -E "s/^([[:space:]]*\*[[:space:]]+Version:[[:space:]]*).*/\1$NEW_VERSION/" "$PLUGIN_FILE"

# @version v docblocku, ak existuje.
sed -i -E "s/^([[:space:]]*\*[[:space:]]+@version[[:space:]]+)[0-9]+\.[0-9]+\.[0-9]+/\1$NEW_VERSION/" "$PLUGIN_FILE" || true

# Runtime plugin version constant.
sed -i -E "s/(define\('TOPTOUR_REF_VERSION',[[:space:]]*')[0-9]+\.[0-9]+\.[0-9]+('.*)/\1$NEW_VERSION\2/" "$PLUGIN_FILE"

# Optional DB version bump.
if [[ "$BUMP_DB_VERSION" == "db" ]]; then
  sed -i -E "s/(define\('TOPTOUR_REF_DB_VERSION',[[:space:]]*')[0-9]+\.[0-9]+\.[0-9]+('.*)/\1$NEW_VERSION\2/" "$PLUGIN_FILE"
elif [[ "$BUMP_DB_VERSION" != "no" ]]; then
  echo "Chyba: druhý parameter môže byť iba 'db'."
  echo "Použitie: ./release.sh [patch|minor|major] [db]"
  exit 1
fi

git add "$PLUGIN_FILE"
git commit -m "chore(release): bump version to $NEW_VERSION"

mkdir -p "$BUILD_DIR" "$DIST_DIR"
rm -rf "$BUILD_DIR/$PLUGIN_SLUG"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

rsync -av \
  --exclude=".git" \
  --exclude=".github" \
  --exclude=".vscode" \
  --exclude="$BUILD_DIR" \
  --exclude="$DIST_DIR" \
  --exclude="*.zip" \
  --exclude="*.tar.gz" \
  --exclude="*.sha256" \
  --exclude="*.log" \
  --exclude=".DS_Store" \
  --exclude="node_modules" \
  --exclude="vendor" \
  --exclude="release.sh" \
  --exclude="zdrojak.sh" \
  ./ "$BUILD_DIR/$PLUGIN_SLUG/"

ZIP_FILE="$DIST_DIR/${PLUGIN_SLUG}-${NEW_VERSION}.zip"
SHA_FILE="$ZIP_FILE.sha256"

rm -f "$ZIP_FILE" "$SHA_FILE"

(
  cd "$BUILD_DIR"
  zip -r "../$ZIP_FILE" "$PLUGIN_SLUG" >/dev/null
)

sha256sum "$ZIP_FILE" > "$SHA_FILE"

git tag "v$NEW_VERSION"

echo
echo "Hotovo."
echo "ZIP:    $ZIP_FILE"
echo "SHA256: $SHA_FILE"
echo "Tag:    v$NEW_VERSION"
echo
echo "Ďalšie kroky:"
echo "  git push"
echo "  git push --tags"