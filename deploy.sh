#!/usr/bin/env bash
#
# Deployment der statischen Seite auf den Webspace.
#
#   ./deploy.sh --test       nur den Zugang prüfen, nichts übertragen
#   ./deploy.sh              hochladen
#   ./deploy.sh --dry-run    nur anzeigen, was hochgeladen würde
#   ./deploy.sh --delete     zusätzlich Dateien auf dem Server löschen,
#                            die es lokal nicht mehr gibt (nur mit lftp)
#
# Zugangsdaten kommen aus der .env - siehe .env.example.

set -euo pipefail

# dotglob: versteckte Dateien wie .htaccess werden mit erfasst.
# nullglob: ein Muster ohne Treffer verschwindet, statt literal stehenzubleiben
#           - sonst bricht ein Ordner ohne sichtbare Dateien das Skript ab.
shopt -s dotglob nullglob

cd "$(dirname "$0")"

DRY_RUN=0
DO_DELETE=0
NUR_TEST=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --delete)  DO_DELETE=1 ;;
    --test)    NUR_TEST=1 ;;
    -h|--help) sed -n '2,11p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $arg" >&2; exit 2 ;;
  esac
done

# --- .env einlesen ----------------------------------------------------------

if [ ! -f .env ]; then
  echo "FEHLER: Es gibt keine .env." >&2
  echo "        cp .env.example .env   und die Werte eintragen." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
. ./.env
set +a

: "${PROTOCOL:=ftps}"
: "${REMOTE_DIR:=/httpdocs}"
: "${SSH_KEY:=}"
: "${DEPLOY_PASS:=}"

case "$PROTOCOL" in
  ftps|ftp) : "${DEPLOY_PORT:=21}" ;;
  sftp)     : "${DEPLOY_PORT:=22}" ;;
  *) echo "FEHLER: PROTOCOL muss ftps, ftp oder sftp sein (ist: $PROTOCOL)" >&2
     exit 1 ;;
esac

fehlt=""
[ -z "${DEPLOY_HOST:-}" ] && fehlt="$fehlt DEPLOY_HOST"
[ -z "${DEPLOY_USER:-}" ] && fehlt="$fehlt DEPLOY_USER"
if [ -n "$fehlt" ]; then
  echo "FEHLER: In der .env fehlt:$fehlt" >&2
  exit 1
fi

if [ "$PROTOCOL" = "sftp" ]; then
  if [ -z "$SSH_KEY" ] && [ -z "$DEPLOY_PASS" ]; then
    echo "FEHLER: Für sftp wird SSH_KEY oder DEPLOY_PASS gebraucht." >&2
    exit 1
  fi
elif [ -z "$DEPLOY_PASS" ]; then
  echo "FEHLER: Für $PROTOCOL wird DEPLOY_PASS gebraucht." >&2
  exit 1
fi

# --- Verbindungstest --------------------------------------------------------
#
# Prüft Anmeldung und Zielverzeichnis, ohne irgendetwas zu übertragen.

if [ $NUR_TEST -eq 1 ]; then
  echo "Teste Zugang: $DEPLOY_USER@$DEPLOY_HOST:$DEPLOY_PORT ($PROTOCOL)"
  echo "Zielverzeichnis: $REMOTE_DIR"
  echo

  if [ "$PROTOCOL" = "sftp" ]; then
    if [ -z "$SSH_KEY" ] || [ ! -f "$SSH_KEY" ]; then
      echo "FEHLER: SSH_KEY fehlt oder ist nicht lesbar: ${SSH_KEY:-<leer>}" >&2
      exit 1
    fi
    printf 'cd %s\nls\nbye\n' "$REMOTE_DIR" > "$(dirname "$0")/.sftp-test"
    if sftp -o StrictHostKeyChecking=accept-new -o BatchMode=yes \
            -i "$SSH_KEY" -P "$DEPLOY_PORT" -b "$(dirname "$0")/.sftp-test" \
            "$DEPLOY_USER@$DEPLOY_HOST"; then
      rm -f "$(dirname "$0")/.sftp-test"
      echo; echo "Zugang funktioniert."
      exit 0
    else
      rm -f "$(dirname "$0")/.sftp-test"
      echo; echo "Zugang fehlgeschlagen - siehe Meldung oben." >&2
      exit 1
    fi
  fi

  CURL_SSL=()
  [ "$PROTOCOL" = "ftps" ] && CURL_SSL=(--ssl-reqd)

  echo "--- Inhalt von $REMOTE_DIR auf dem Server ---"
  if printf 'user = %s:%s\n' "$DEPLOY_USER" "$DEPLOY_PASS" \
     | curl -sS --fail --connect-timeout 20 "${CURL_SSL[@]}" \
            -K - --list-only "ftp://$DEPLOY_HOST:$DEPLOY_PORT$REMOTE_DIR/"; then
    echo "---"
    echo
    echo "Zugang funktioniert. Hochladen mit:  ./deploy.sh"
    exit 0
  else
    echo
    echo "Zugang fehlgeschlagen." >&2
    echo "Häufige Ursachen:" >&2
    echo "  - Benutzername oder Passwort falsch" >&2
    echo "  - REMOTE_DIR existiert nicht (bestätigt ist /httpdocs)" >&2
    echo "  - Hoster kann kein FTPS: dann PROTOCOL=ftp probieren" >&2
    echo "  - falscher Hostname in DEPLOY_HOST" >&2
    exit 1
  fi
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
  # Vorlagenordner, gehört nicht auf den Server: seine .htaccess enthält einen
  # Platzhalter statt eines echten AuthUserFile-Pfads. Apache würde sie lesen
  # und für dieses Verzeichnis mit einem 500er antworten.
  # Interne Anleitung - beschreibt Serverpfade und den Passwortschutz und
  # gehört daher nicht ins Web.
  "Schulungen/ANLEITUNG.md"
  # Konfiguration, Programmbibliothek und Schulungsinhalte. Kommen NICHT ins
  # Web-Verzeichnis, sondern eine Ebene darüber - siehe privat_hochladen().
  "privat"
)

# Vergleicht sowohl den vollen Pfad ("Schulungen/login-vorlage") als auch den
# reinen Dateinamen ("Thumbs.db"), damit beide Schreibweisen in AUSSCHLUSS
# funktionieren.
ist_ausgeschlossen() {
  local pfad="$1"
  local name="${pfad##*/}"
  for a in "${AUSSCHLUSS[@]}"; do
    [ "$pfad" = "$a" ] && return 0
    [ "$name" = "$a" ] && return 0
  done
  case "$name" in
    *.tmp|'~$'*) return 0 ;;
  esac
  return 1
}

HOCHLADEN=()
for eintrag in *; do
  ist_ausgeschlossen "$eintrag" && continue
  HOCHLADEN+=("$eintrag")
done

if [ ${#HOCHLADEN[@]} -eq 0 ]; then
  echo "FEHLER: Nichts zum Hochladen gefunden." >&2
  exit 1
fi

# Sicherheitsnetz: die .env darf niemals in der Liste stehen.
for f in "${HOCHLADEN[@]}"; do
  if [ "$f" = ".env" ]; then
    echo "ABBRUCH: .env wäre hochgeladen worden." >&2
    exit 1
  fi
done

echo "Ziel:      $DEPLOY_USER@$DEPLOY_HOST:$REMOTE_DIR  (Port $DEPLOY_PORT, $PROTOCOL)"
echo "Dateien:   ${HOCHLADEN[*]}"
[ "$PROTOCOL" = "ftp" ] && echo "ACHTUNG:   unverschlüsselt - besser PROTOCOL=ftps"
[ $DRY_RUN -eq 1 ] && echo "Modus:     Testlauf, es wird nichts übertragen"
echo

# --- Übertragung ------------------------------------------------------------

if command -v lftp >/dev/null 2>&1; then
  # ---- Weg 1: lftp. Spiegelt und überträgt nur Geändertes. ----
  echo "Verwende lftp."

  case "$PROTOCOL" in
    sftp) SCHEMA="sftp" ;;
    *)    SCHEMA="ftp"  ;;   # ftps = ftp + TLS, siehe ssl-force unten
  esac

  SPIEGEL="mirror --reverse --only-newer --verbose"
  [ $DO_DELETE -eq 1 ] && SPIEGEL="$SPIEGEL --delete"
  [ $DRY_RUN  -eq 1 ] && SPIEGEL="$SPIEGEL --dry-run"
  for a in "${AUSSCHLUSS[@]}"; do
    SPIEGEL="$SPIEGEL --exclude-glob $a"
  done

  TLS="false"
  [ "$PROTOCOL" = "ftps" ] && TLS="true"

  # Passwort über "open -u", nicht in der URL - sonst brechen Sonderzeichen.
  lftp -c "set ftp:ssl-force $TLS; set ftp:ssl-protect-data true; \
           set ssl:verify-certificate no; \
           open -u '$DEPLOY_USER','$DEPLOY_PASS' -p $DEPLOY_PORT $SCHEMA://$DEPLOY_HOST; \
           $SPIEGEL . $REMOTE_DIR; bye"

elif [ "$PROTOCOL" = "sftp" ]; then
  # ---- Weg 2: OpenSSH-sftp. Braucht einen SSH-Schlüssel. ----
  echo "Verwende sftp (OpenSSH)."

  if [ -z "$SSH_KEY" ]; then
    echo "FEHLER: Ohne lftp braucht sftp einen SSH_KEY - ein Passwort kann" >&2
    echo "        der OpenSSH-Client nicht aus der .env lesen." >&2
    echo "        Alternative: PROTOCOL=ftps in der .env." >&2
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
  # ---- Weg 3: FTP/FTPS über curl. Kein Zusatzwerkzeug nötig. ----
  echo "Verwende curl ($PROTOCOL)."

  CURL_SSL=()
  [ "$PROTOCOL" = "ftps" ] && CURL_SSL=(--ssl-reqd)

  ANZAHL=0
  FEHLER=0

  # Das Passwort wird über eine Konfiguration auf der Standardeingabe
  # übergeben, damit es nicht in der Prozessliste auftaucht.
  hochladen_datei() {
    local pfad="$1"
    local ziel="ftp://$DEPLOY_HOST:$DEPLOY_PORT$REMOTE_DIR/$pfad"

    if [ $DRY_RUN -eq 1 ]; then
      echo "  würde hochladen: $pfad"
      return 0
    fi

    if printf 'user = %s:%s\n' "$DEPLOY_USER" "$DEPLOY_PASS" \
       | curl -sS --fail --ftp-create-dirs "${CURL_SSL[@]}" \
              -K - -T "$pfad" "$ziel"; then
      echo "  ok      $pfad"
      ANZAHL=$((ANZAHL + 1))
    else
      echo "  FEHLER  $pfad" >&2
      FEHLER=$((FEHLER + 1))
    fi
  }

  hochladen_rekursiv() {
    local pfad="$1"
    ist_ausgeschlossen "$pfad" && return 0
    if [ -d "$pfad" ]; then
      local k
      for k in "$pfad"/*; do hochladen_rekursiv "$k"; done
    else
      hochladen_datei "$pfad"
    fi
    return 0
  }

  for f in "${HOCHLADEN[@]}"; do hochladen_rekursiv "$f"; done

  if [ $DRY_RUN -eq 0 ]; then
    echo
    echo "$ANZAHL Datei(en) übertragen, $FEHLER Fehler."
    [ $FEHLER -gt 0 ] && exit 1
  fi
fi

# --- Privater Ordner --------------------------------------------------------
#
# privat/ enthält Konfiguration, Programmbibliothek und die Schulungsdateien.
# Er gehört NICHT ins Web-Verzeichnis, sondern eine Ebene darüber - damit ist
# über den Browser keine dieser Dateien erreichbar, auch nicht die
# Schulungsinhalte. Ausgeliefert werden sie ausschließlich durch datei.php,
# und die prüft vorher die Anmeldung.

: "${REMOTE_PRIVAT:=$(dirname "$REMOTE_DIR")/privat}"
REMOTE_PRIVAT="${REMOTE_PRIVAT//\/\///}"

privat_hochladen() {
  [ -d privat ] || return 0

  echo
  echo "Privater Ordner -> $REMOTE_PRIVAT"

  if [ "$PROTOCOL" = "sftp" ]; then
    echo "  Hinweis: über sftp bitte einmalig von Hand übertragen." >&2
    return 0
  fi

  local anzahl=0 fehler=0

  # Sitzungsdateien sind Laufzeitdaten und bleiben lokal; das Verzeichnis
  # selbst muss auf dem Server aber existieren.
  local liste
  liste=$(find privat -type f ! -path 'privat/sessions/*' | sort)

  while IFS= read -r pfad; do
    [ -n "$pfad" ] || continue
    local rel="${pfad#privat/}"
    local ziel="ftp://$DEPLOY_HOST:$DEPLOY_PORT$REMOTE_PRIVAT/$rel"

    if [ $DRY_RUN -eq 1 ]; then
      echo "  würde hochladen: $rel"
      continue
    fi

    if printf 'user = %s:%s\n' "$DEPLOY_USER" "$DEPLOY_PASS" \
       | curl -sS --fail --ftp-create-dirs "${CURL_SSL[@]}" \
              -K - -T "$pfad" "$ziel"; then
      echo "  ok      $rel"
      anzahl=$((anzahl + 1))
    else
      echo "  FEHLER  $rel" >&2
      fehler=$((fehler + 1))
    fi
  done <<< "$liste"

  # sessions/ anlegen, indem eine Platzhalterdatei hineingelegt wird.
  if [ $DRY_RUN -eq 0 ]; then
    printf 'user = %s:%s\n' "$DEPLOY_USER" "$DEPLOY_PASS" \
      | curl -sS --ftp-create-dirs "${CURL_SSL[@]}" -K - \
             -T /dev/null "ftp://$DEPLOY_HOST:$DEPLOY_PORT$REMOTE_PRIVAT/sessions/.platzhalter" \
        >/dev/null 2>&1 || true
    echo
    echo "  $anzahl Datei(en) übertragen, $fehler Fehler."
    [ $fehler -gt 0 ] && return 1
  fi
  return 0
}

# CURL_SSL ist nur im curl-Zweig gesetzt - hier für den Fall nachziehen, dass
# lftp den Hauptteil übernommen hat.
if [ "${CURL_SSL+gesetzt}" != "gesetzt" ]; then
  CURL_SSL=()
  [ "$PROTOCOL" = "ftps" ] && CURL_SSL=(--ssl-reqd)
fi

privat_hochladen

echo
if [ $DRY_RUN -eq 1 ]; then
  echo "Testlauf beendet - es wurde nichts übertragen."
else
  echo "Fertig. Prüfen: https://elektromas.cool/"
fi
