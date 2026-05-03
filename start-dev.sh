#!/bin/zsh
set -e
cd "$(dirname "$0")"
exec /Applications/XAMPP/xamppfiles/bin/php -S localhost:8000
