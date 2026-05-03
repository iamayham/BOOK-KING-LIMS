#!/bin/zsh
set -e
cd "$(dirname "$0")"
exec /Applications/XAMPP/xamppfiles/bin/php \
  -d "auto_prepend_file=$(pwd)/helpers/request_guard.php" \
  -S localhost:8000
