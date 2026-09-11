#!/bin/bash
# setup-enpii-assistant-auth.sh
# Setup composer auth for private enpii-assistant package.
# Usage: ./setup-enpii-assistant-auth.sh
#
# Steps for the new developer:
# 1. Go to https://github.com/settings/tokens?type=beta
# 2. Generate new token (fine-grained, classic also works)
# 3. Resource owner: your-org-or-self
# 4. Repository access: Only select repositories → its-enpii/enpii-assistant
# 5. Repository permissions: Contents = Read-only
# 6. Copy the token (starts with github_pat_...)
# 7. Run this script and paste the token

set -euo pipefail

echo "=== enpii/assistant — Composer Auth Setup ==="
echo ""
echo "Need a GitHub Personal Access Token (classic) or"
echo "Fine-Grained Token with Contents: Read on its-enpii/enpii-assistant."
echo ""
echo "Create one at: https://github.com/settings/tokens"
echo "  - Classic: scope 'repo' is enough"
echo "  - Fine-grained: Repository access = only 'enpii-assistant',"
echo "    Permissions → Repository permissions → Contents = Read-only"
echo ""
read -r -p "Paste your GitHub token (will be stored in ~/.composer/auth.json): " TOKEN

if [ -z "$TOKEN" ]; then
  echo "No token provided, aborting." >&2
  exit 1
fi

mkdir -p ~/.composer
chmod 700 ~/.composer

# Preserve existing tokens
if [ -f ~/.composer/auth.json ]; then
  echo "Existing auth.json found, merging tokens..."
  python3 <<EOF
import json, os
existing = {}
if os.path.exists(os.path.expanduser("~/.composer/auth.json")):
    with open(os.path.expanduser("~/.composer/auth.json")) as f:
        existing = json.load(f)
existing.setdefault("github-oauth", {})["github.com"] = "$TOKEN"
with open(os.path.expanduser("~/.composer/auth.json"), "w") as f:
    json.dump(existing, f, indent=2)
os.chmod(os.path.expanduser("~/.composer/auth.json"), 0o600)
EOF
else
  cat > ~/.composer/auth.json <<EOF
{
    "github-oauth": {
        "github.com": "$TOKEN"
    }
}
EOF
  chmod 600 ~/.composer/auth.json
fi

echo ""
echo "✓ Auth saved to ~/.composer/auth.json (chmod 600)"
echo ""
echo "Test it:"
echo "  composer create-project enpii/assistant-stub /tmp/test \\"
echo "    --repository='{\"type\":\"vcs\",\"url\":\"https://github.com/its-enpii/enpii-assistant\"}'"
echo ""
echo "Or add to existing project's composer.json:"
cat <<'JSON'
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/its-enpii/enpii-assistant" }
    ],
    "require": {
        "enpii/assistant": "^0.1"
    },
    "minimum-stability": "dev"
}
JSON
