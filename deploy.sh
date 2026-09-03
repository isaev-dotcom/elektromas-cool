#!/usr/bin/env bash
#
# Deployment der statischen Seite auf den Webspace.
#
#   ./deploy.sh              hochladen
#   ./deploy.sh --dry-run    nur anzeigen, was passieren würde
#   ./deploy.sh --delete     zusätzlich Dateien auf dem Server löschen,
#                            die es lokal nicht mehr gibt (nur mit lftp)
#
# Zugangsdaten kommen aus der .env - siehe .env.example.

set -euo pipefail

cd "$(dirname "$0")"

DRY_RUN=0
DO_DELETE=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --delete)  DO_DELETE=1 ;;
    -h|--help) sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $arg" >&2; exit 2 ;;
  esac
done

# --- .env einlesen ----------------------------------------------------------

if [ ! -f .env ]; then
  echo "FEHLER: Es gibt keine .env." >&2
  echo "        cp .env.example .env   und die Werte eintragen." >&2
  exit 1
fi

# Nur KEY=VALUE-Zeilen übernehmen, Kommentare und Leerzeilen ignorieren.
set -a
# shellcheck disable=SC1091
. ./.env
set +a

: "${PROTOCOL:=sftp}"
: "${DEPLOY_PORT:=22}"
: "${REMOTE_DIR:=/httpdocs}"
: "${SSH_KEY:=}"
: "${DEPLOY_PASS:=}"

fehlt=""
[ -z "${DEPLOY_HOST:-}" ] && fehlt="$fehlt DEPLOY_HOST"
[ -z "${DEPLOY_USER:-}" ] && fehlt="$fehlt DEPLOY_USER"
if [ -n "$fehlt" ]; then
  echo "FEHLER: In der .env fehlt:$fehlt" >&2
  exit 1
fi

if [ -z "$SSH_KEY" ] && [ -z "$DEPLOY_PASS" ]; then
  echo "FEHLER: Weder SSH_KEY noch DEPLOY_PASS gesetzt." >&2
  exit 1
fi

# --- Was wird hochgeladen? --------------------------------------------------
#
# Alles im Projektordner AUSSER dieser Liste. Die .env steht hier ganz oben:
# sie enthält Zugangsdaten und darf unter keinen Umständen auf den Webserver.

AUSSCHLUSS=(
  ".env"
  ".env.example"
  ".git"
  ".gitignore"
  "deploy.sh"
  "README.md"
  "Thumbs.db"
  "desktop.ini"
  ".DS_Store"
  ".vscode"
  ".idea"
)

ist_ausgeschlossen() {
  local name="$1"
  for a in "${AUSSCHLUSS[@]}"; do
    [ "$name" = "$a" ] && return 0
  done
  case "$name" in
    *.tmp|'~$'*) return 0 ;;
  esac
  return 1
}

HOCHLADEN=()
for eintrag in * .[!.]*; do
  [ -e "$eintrag" ] || continue
  ist_ausgeschlossen "$eintrag" && continue
  HOCHLADEN+=("$eintrag")
done

if [ ${#HOCHLADEN[@]} -eq 0 ]; then
  echo "FEHLER: Nichts zum Hochladen gefunden." >&2
  exit 1
fi

echo "Ziel:      $DEPLOY_USER@$DEPLOY_HOST:$REMOTE_DIR  (Port $DEPLOY_PORT, $PROTOCOL)"
echo "Dateien:   ${HOCHLADEN[*]}"
[ $DRY_RUN -eq 1 ] && echo "Modus:     Testlauf, es wird nichts übertragen"
echo

# Sicherheitsnetz: .env darf niemals in der Liste stehen.
for f in "${HOCHLADEN[@]}"; do
  if [ "$f" = ".env" ]; then
    echo "ABBRUCH: .env wäre hochgeladen worden." >&2
    exit 1
  fi
done

# --- Übertragung ------------------------------------------------------------

if command -v lftp >/dev/null 2>&1; then
  # ---- Weg 1: lftp. Spiegelt und überträgt nur Geändertes. ----
  echo "Verwende lftp."

  if [ "$PROTOCOL" = "sftp" ]; then
    URL="sftp://$DEPLOY_USER:$DEPLOY_PASS@$DEPLOY_HOST:$DEPLOY_PORT"
  else
    URL="ftp://$DEPLOY_USER:$DEPLOY_PASS@$DEPLOY_HOST:$DEPLOY_PORT"
  fi

  SPIEGEL="mirror --reverse --only-newer --verbose"
  [ $DO_DELETE -eq 1 ] && SPIEGEL="$SPIEGEL --delete"
  [ $DRY_RUN  -eq 1 ] && SPIEGEL="$SPIEGEL --dry-run"
  for a in "${AUSSCHLUSS[@]}"; do
    SPIEGEL="$SPIEGEL --exclude-glob $a"
  done

  lftp -c "set ftp:ssl-force true; set ssl:verify-certificate no; \
           open $URL; $SPIEGEL . $REMOTE_DIR; bye"

elif [ "$PROTOCOL" = "sftp" ]; then
  # ---- Weg 2: OpenSSH-sftp. Braucht einen SSH-Schlüssel. ----
  echo "Verwende sftp (OpenSSH)."

  if [ -z "$SSH_KEY" ]; then
    echo "FEHLER: Ohne lftp braucht sftp einen SSH_KEY - ein Passwort kann" >&2
    echo "        der OpenSSH-Client nicht aus der .env lesen." >&2
    exit 1
  fi
  if [ ! -f "$SSH_KEY" ]; then
    echo "FEHLER: Schlüsseldatei nicht gefunden: $SSH_KEY" >&2
    exit 1
  fi

  BATCH=$(mktemp)
  trap 'rm -f "$BATCH"' EXIT
  {
    echo "cd $REMOTE_DIR"
    for f in "${HOCHLADEN[@]}"; do
      if [ -d "$f" ]; then
        echo "-mkdir $f"
        echo "put -r $f"
      else
        echo "put $f"
      fi
    done
    echo "bye"
  } > "$BATCH"

  if [ $DRY_RUN -eq 1 ]; then
    echo "--- Diese sftp-Befehle würden laufen ---"
    cat "$BATCH"
    exit 0
  fi

  sftp -o StrictHostKeyChecking=accept-new \
       -i "$SSH_KEY" -P "$DEPLOY_PORT" -b "$BATCH" \
       "$DEPLOY_USER@$DEPLOY_HOST"

else
  # ---- Weg 3: einfaches FTP über curl. ----
  echo "Verwende curl (FTP)."

  if [ -z "$DEPLOY_PASS" ]; then
    echo "FEHLER: Für FTP über curl wird DEPLOY_PASS benötigt." >&2
    exit 1
  fi

  hochladen_rekursiv() {
    local pfad="$1"
    if [ -d "$pfad" ]; then
      for k in "$pfad"/*; do [ -e "$k" ] && hochladen_rekursiv "$k"; done
    else
      local ziel="ftp://$DEPLOY_HOST:$DEPLOY_PORT$REMOTE_DIR/$pfad"
      if [ $DRY_RUN -eq 1 ]; then
        echo "  würde hochladen: $pfad"
      else
        echo "  $pfad"
        curl -sS --ftp-create-dirs -u "$DEPLOY_USER:$DEPLOY_PASS" \
             -T "$pfad" "$ziel"
      fi
    fi
  }

  for f in "${HOCHLADEN[@]}"; do hochladen_rekursiv "$f"; done
fi

echo
if [ $DRY_RUN -eq 1 ]; then
  echo "Testlauf beendet - es wurde nichts übertragen."
else
  echo "Fertig. Prüfen: https://elektromas.cool/"
fi
