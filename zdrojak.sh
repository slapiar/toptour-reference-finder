#!/usr/bin/env bash
set -euo pipefail

# Create a full source archive including .git history.
# Also exports a git bundle containing complete history and refs.
#
# Usage:
#   ./zdrojak.sh [OUTPUT_DIR]
#
# Example:
#   ./zdrojak.sh ./dist

repo_root="$(git rev-parse --show-toplevel 2>/dev/null || true)"

if [[ -z "${repo_root}" ]]; then
  echo "ERROR: This script must be run inside a git repository."
  exit 1
fi

repo_name="$(basename "${repo_root}")"
out_dir="${1:-${repo_root}/dist}"
mkdir -p "${out_dir}"

timestamp="$(date +%Y%m%d-%H%M%S)"
base_name="${repo_name}-zdrojak-${timestamp}"

bundle_path="${out_dir}/${base_name}.bundle"
tar_path="${out_dir}/${base_name}.tar.gz"
sha_path="${out_dir}/${base_name}.sha256"

echo "Repo:    ${repo_root}"
echo "Output:  ${out_dir}"
echo "Prefix:  ${base_name}"

# 1) Full git history as bundle (all refs)
echo "[1/3] Creating git bundle..."
git -C "${repo_root}" bundle create "${bundle_path}" --all

# 2) Full source snapshot with .git directory
# Use a temp file outside repo to avoid tar self-inclusion issues.
echo "[2/3] Creating full source tar.gz (including .git)..."
tmp_tar="$(mktemp "/tmp/${base_name}.XXXXXX.tar.gz")"

# If output is inside repository, exclude the final output directory path.
out_real="$(cd "${out_dir}" && pwd)"
repo_real="$(cd "${repo_root}" && pwd)"

if [[ "${out_real}" == "${repo_real}"* ]]; then
  rel_out="${out_real#${repo_real}/}"
  tar -C "${repo_root}" \
    --exclude="./${rel_out}" \
    -czf "${tmp_tar}" .
else
  tar -C "${repo_root}" -czf "${tmp_tar}" .
fi

mv "${tmp_tar}" "${tar_path}"

# 3) Checksums

echo "[3/3] Writing checksums..."
sha256sum "${bundle_path}" > "${sha_path}"
sha256sum "${tar_path}" >> "${sha_path}"

echo
echo "Hotovo."
echo "Bundle:    ${bundle_path}"
echo "Tarball:   ${tar_path}"
echo "Checksums: ${sha_path}"
echo
echo "Obnova z bundle (kompletná história):"
echo "  git clone \"${bundle_path}\" ${repo_name}-restore"
